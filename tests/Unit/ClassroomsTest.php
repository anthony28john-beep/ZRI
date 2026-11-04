<?php

namespace Tests\Unit;

use App\Exceptions\ClassroomUnavailable;
use App\Services\Classrooms;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClassroomsTest extends TestCase
{
    private const CLASS_ID = 'a0000000-0000-4000-8000-000000000001';

    private $database;

    private Classrooms $classes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Mockery::mock(Database::class);
        $this->classes = new Classrooms($this->database, $this->fullNames());
    }

    private function read(string $path, mixed $value): void
    {
        $ref = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->once()->with($path)->andReturn($ref);
        $ref->shouldReceive('getValue')->once()->andReturn($value);
    }

    public function test_teacher_index_never_grants_access_to_a_class_owned_by_another_teacher(): void
    {
        $this->read('teacher_classes/teacher-one', [self::CLASS_ID => true]);
        $this->read('classes/'.self::CLASS_ID, ['teacher_uid' => 'another-teacher', 'students' => ['secret' => true]]);
        $this->assertSame([], $this->classes->forTeacher('teacher-one'));
    }

    public function test_teacher_roster_uses_full_name_instead_of_legacy_name_or_username(): void
    {
        $this->read('teacher_classes/teacher-one', [self::CLASS_ID => true]);
        $this->read('classes/'.self::CLASS_ID, ['teacher_uid' => 'teacher-one', 'students' => ['ada' => true]]);
        $this->read('users/ada', ['role' => 'student', 'fullName' => 'Ada Lovelace', 'name' => 'gamer123', 'username' => 'gamer123']);
        $this->assertSame('Ada Lovelace', $this->classes->forTeacher('teacher-one')[self::CLASS_ID]['students']['ada']['name']);
    }

    public function test_roster_uses_only_current_student_profiles_and_minimal_public_fields(): void
    {
        $this->read('teacher_classes/teacher-one', [self::CLASS_ID => true, 'invalid-key' => true]);
        $this->read('classes/'.self::CLASS_ID, ['name' => 'Section A', 'teacher_uid' => 'teacher-one', 'students' => ['ada' => true, 'demoted' => true, 'not-assigned' => false]]);
        $this->read('users/ada', ['name' => 'Ada', 'role' => 'student', 'email' => 'private@example.com', 'password' => 'private']);
        $this->read('users/demoted', ['name' => 'Former student', 'role' => 'teacher']);
        $result = $this->classes->forTeacher('teacher-one');
        $this->assertSame(['ada' => ['uid' => 'ada', 'name' => 'Ada']], $result[self::CLASS_ID]['students']);
    }

    public function test_reassignment_atomically_removes_old_teacher_index_and_replaces_roster(): void
    {
        $this->read('classes/'.self::CLASS_ID, ['teacher_uid' => 'old-teacher', 'created_by' => 'original-admin', 'created_at' => 'original-date']);
        $this->read('users/new-teacher', ['role' => 'teacher']);
        $this->read('users/ada', ['role' => 'student']);
        $root = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->once()->withNoArgs()->andReturn($root);
        $root->shouldReceive('update')->once()->with([
            'classes/'.self::CLASS_ID => ['name' => 'Section B', 'teacher_uid' => 'new-teacher', 'students' => ['ada' => true], 'created_by' => 'original-admin', 'created_at' => 'original-date'],
            'teacher_classes/new-teacher/'.self::CLASS_ID => true,
            'teacher_classes/old-teacher/'.self::CLASS_ID => null,
        ]);
        $this->classes->save(['name' => 'Section B', 'teacher_uid' => 'new-teacher', 'students' => ['ada']], 'admin', self::CLASS_ID);
    }

    public function test_student_cannot_be_assigned_as_a_teacher(): void
    {
        $this->read('users/student', ['role' => 'student']);
        $this->expectException(ValidationException::class);
        $this->classes->save(['name' => 'Class', 'teacher_uid' => 'student'], 'admin');
    }

    public function test_invalid_member_is_rejected_before_any_write(): void
    {
        $this->read('users/teacher', ['role' => 'teacher']);
        $this->read('users/admin', ['role' => 'admin']);
        $this->expectException(ValidationException::class);
        $this->classes->save(['name' => 'Class', 'teacher_uid' => 'teacher', 'students' => ['admin']], 'admin');
    }

    public function test_database_failure_is_not_reported_as_an_empty_class_list(): void
    {
        $this->database->shouldReceive('getReference')->andThrow(new RuntimeException('Private database details'));
        $this->expectException(ClassroomUnavailable::class);
        $this->expectExceptionMessage('Classes are temporarily unavailable.');
        $this->classes->forTeacher('teacher');
    }

    public function test_block_classes_follow_current_membership_and_exclude_other_blocks_and_roles(): void
    {
        $this->read('teacher_classes/teacher-one', [self::CLASS_ID => true]);
        $this->read('classes/'.self::CLASS_ID, ['name' => 'Block A', 'teacher_uid' => 'teacher-one', 'block_id' => self::CLASS_ID, 'students' => ['outsider' => true]]);
        $this->read('users', [
            'new-student' => ['role' => 'student', 'name' => 'New Student', 'block_id' => self::CLASS_ID, 'email' => 'private@example.com'],
            'moved' => ['role' => 'student', 'name' => 'Moved Student', 'block_id' => 'another-block'],
            'teacher' => ['role' => 'teacher', 'name' => 'Teacher', 'block_id' => self::CLASS_ID],
            'legacy' => ['role' => 'student', 'name' => 'No Block'],
        ]);
        $result = $this->classes->forTeacher('teacher-one');
        $this->assertSame(['new-student' => ['uid' => 'new-student', 'name' => 'New Student']], $result[self::CLASS_ID]['students']);
    }

    public function test_block_assignment_derives_name_and_never_copies_submitted_student_ids(): void
    {
        $this->read('users/teacher', ['role' => 'teacher']);
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $root = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->once()->withNoArgs()->andReturn($root);
        $root->shouldReceive('update')->once()->with(Mockery::on(function ($updates) {
            $class = reset($updates);

            return $class['name'] === 'BSIT 1A' && $class['block_id'] === self::CLASS_ID && $class['students'] === null && $class['teacher_uid'] === 'teacher';
        }));
        $this->classes->save(['block_id' => self::CLASS_ID, 'teacher_uid' => 'teacher', 'students' => ['outsider']], 'admin');
    }

    public function test_block_catalog_exposes_only_valid_ids_and_names(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A', 'teacher' => 'private', 'students' => ['private']], 'bad-id' => ['name' => 'Ignore'], 'bad-record' => false]);
        $this->assertSame([self::CLASS_ID => 'BSIT 1A'], $this->classes->blocks());
    }

    public function test_duplicate_block_names_are_rejected(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->expectException(ValidationException::class);
        $this->classes->createBlock('bsit 1a');
    }

    public function test_existing_student_assignment_updates_only_block_fields(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->read('users/student', ['name' => 'Student', 'role' => 'student']);
        $root = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->once()->withNoArgs()->andReturn($root);
        $root->shouldReceive('update')->once()->with(['users/student/block_id' => self::CLASS_ID, 'users/student/block_application' => null]);
        $this->classes->assignBlock(self::CLASS_ID, ['student']);
    }

    public function test_existing_account_assignment_rejects_teachers_before_writing(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->read('users/teacher', ['role' => 'teacher']);
        $this->expectException(ValidationException::class);
        $this->classes->assignBlock(self::CLASS_ID, ['teacher']);
    }

    public function test_unused_block_deletion_changes_only_the_requested_block(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->read('users', ['elsewhere' => ['role' => 'student', 'block_id' => 'other-block']]);
        $this->read('classes', ['other-class' => ['block_id' => 'other-block']]);
        $root = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->once()->withNoArgs()->andReturn($root);
        $root->shouldReceive('update')->once()->with(['blocks/'.self::CLASS_ID => null]);
        $this->classes->deleteBlock(self::CLASS_ID);
    }

    public function test_block_with_students_cannot_be_deleted(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->read('users', ['student' => ['role' => 'student', 'block_id' => self::CLASS_ID]]);
        $this->read('classes', []);
        $this->database->shouldNotReceive('getReference')->withNoArgs();
        $this->expectException(ValidationException::class);
        $this->classes->deleteBlock(self::CLASS_ID);
    }

    public function test_block_with_pending_applications_cannot_be_deleted(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->read('users', ['student' => ['role' => 'student', 'block_application' => ['block_id' => self::CLASS_ID]]]);
        $this->read('classes', []);
        $this->database->shouldNotReceive('getReference')->withNoArgs();
        $this->expectException(ValidationException::class);
        $this->classes->deleteBlock(self::CLASS_ID);
    }

    public function test_block_with_a_class_cannot_be_deleted_even_when_empty(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->read('users', []);
        $this->read('classes', [self::CLASS_ID => ['block_id' => self::CLASS_ID]]);
        $this->database->shouldNotReceive('getReference')->withNoArgs();
        $this->expectException(ValidationException::class);
        $this->classes->deleteBlock(self::CLASS_ID);
    }

    public function test_block_deletion_fails_closed_when_usage_cannot_be_read(): void
    {
        $this->read('blocks', [self::CLASS_ID => ['name' => 'BSIT 1A']]);
        $this->database->shouldReceive('getReference')->with('users')->andThrow(new RuntimeException('Private failure'));
        $this->database->shouldNotReceive('getReference')->withNoArgs();
        $this->expectException(ClassroomUnavailable::class);
        $this->classes->deleteBlock(self::CLASS_ID);
    }

    public function test_unknown_block_is_not_deleted(): void
    {
        $this->read('blocks', []);
        $this->database->shouldNotReceive('getReference')->withNoArgs();
        try {
            $this->classes->deleteBlock(self::CLASS_ID);
            $this->fail('Unknown blocks must not be deleted.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
