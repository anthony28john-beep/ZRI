<?php

namespace Tests\Unit;

use App\Exceptions\ClassroomUnavailable;
use App\Services\BlockMemberships;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use Kreait\Firebase\Database\Transaction;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class BlockMembershipsTest extends TestCase
{
    private array $data;

    private BlockMemberships $memberships;

    private int $writes = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = [
            'users' => ['student' => ['role' => 'student', 'name' => 'Ada'], 'teacher' => ['role' => 'teacher'], 'other' => ['role' => 'teacher']],
            'blocks' => ['a' => ['name' => 'Block A'], 'b' => ['name' => 'Block B']],
            'classes' => ['class-a' => ['block_id' => 'a', 'teacher_uid' => 'teacher'], 'class-b' => ['block_id' => 'b', 'teacher_uid' => 'other']],
            'unrelated' => ['preserve' => true],
        ];
        $database = Mockery::mock(Database::class);
        $root = Mockery::mock(Reference::class);
        $snapshot = Mockery::mock(Snapshot::class);
        $transaction = Mockery::mock(Transaction::class);
        $database->shouldReceive('getReference')->withNoArgs()->andReturn($root);
        $root->shouldReceive('getValue')->andReturnUsing(fn () => $this->data);
        $snapshot->shouldReceive('getValue')->andReturnUsing(fn () => $this->data);
        $transaction->shouldReceive('snapshot')->with($root)->andReturn($snapshot);
        $transaction->shouldReceive('set')->with($root, Mockery::type('array'))->andReturnUsing(function ($reference, $value) {
            $this->data = $value;
            $this->writes++;
        });
        $database->shouldReceive('runTransaction')->andReturnUsing(fn ($callback) => $callback($transaction));
        $this->memberships = new BlockMemberships($database, $this->fullNames());
    }

    public function test_application_is_pending_until_the_assigned_teacher_accepts(): void
    {
        $this->memberships->apply('student', 'a');
        $this->assertArrayNotHasKey('block_id', $this->data['users']['student']);
        $this->assertSame('Block A', $this->memberships->forStudent('student')['pending']['name']);
        $this->assertSame([], $this->memberships->forTeacher('other'));
        $application = $this->memberships->forTeacher('teacher')[0];
        $this->memberships->review('teacher', 'student', $application['id'], true);
        $this->assertSame('a', $this->data['users']['student']['block_id']);
        $this->assertArrayNotHasKey('block_application', $this->data['users']['student']);
        $this->assertSame('Block A', $this->memberships->forStudent('student')['current']['name']);
        $this->assertSame(['preserve' => true], $this->data['unrelated']);
    }

    public function test_teacher_applications_show_the_student_full_name(): void
    {
        $this->data['users']['student']['fullname'] = 'Ada Lovelace';
        $this->data['users']['student']['username'] = 'gamer123';
        $this->memberships->apply('student', 'a');
        $this->assertSame('Ada Lovelace', $this->memberships->forTeacher('teacher')[0]['name']);
    }

    public function test_teacher_can_remove_a_block_member_without_deleting_the_account_or_game_records(): void
    {
        $this->data['users']['student']['block_id'] = 'a';
        $this->data['players'] = ['student' => ['levels' => ['level1' => ['highestScore' => 27]]]];
        $original = $this->data;
        $this->memberships->removeStudent('teacher', 'class-a', 'student');
        unset($original['users']['student']['block_id']);
        $this->assertSame($original, $this->data);
        $this->assertNull($this->memberships->forStudent('student')['current']);
        $this->memberships->apply('student', 'b');
        $this->assertSame('b', $this->data['users']['student']['block_application']['block_id']);
    }

    public function test_another_teacher_or_reassigned_teacher_cannot_remove_a_block_member(): void
    {
        $this->data['users']['student']['block_id'] = 'a';
        $this->assertHttpError(403, fn () => $this->memberships->removeStudent('other', 'class-a', 'student'));
        $this->data['classes']['class-a']['teacher_uid'] = 'other';
        $this->assertHttpError(403, fn () => $this->memberships->removeStudent('teacher', 'class-a', 'student'));
        $this->assertSame('a', $this->data['users']['student']['block_id']);
        $this->assertSame(0, $this->writes);
    }

    public function test_stale_removal_does_not_remove_membership_in_a_different_block(): void
    {
        $this->data['users']['student']['block_id'] = 'b';
        $this->assertHttpError(404, fn () => $this->memberships->removeStudent('teacher', 'class-a', 'student'));
        $this->assertSame('b', $this->data['users']['student']['block_id']);
        $this->assertSame(0, $this->writes);
        $this->data['users']['student']['block_id'] = 'a';
        $this->memberships->removeStudent('teacher', 'class-a', 'student');
        $this->assertHttpError(404, fn () => $this->memberships->removeStudent('teacher', 'class-a', 'student'));
    }

    public function test_legacy_removal_changes_only_the_owned_individual_roster(): void
    {
        $this->data['users']['student']['block_id'] = 'b';
        $this->data['classes']['legacy'] = ['teacher_uid' => 'teacher', 'students' => ['student' => true, 'another-student' => true]];
        $this->data['classes']['other-legacy'] = ['teacher_uid' => 'other', 'students' => ['student' => true]];
        $this->memberships->removeStudent('teacher', 'legacy', 'student');
        $this->assertSame(['another-student' => true], $this->data['classes']['legacy']['students']);
        $this->assertTrue($this->data['classes']['other-legacy']['students']['student']);
        $this->assertSame('b', $this->data['users']['student']['block_id']);
    }

    public function test_removal_rejects_non_teacher_actors_and_non_student_targets(): void
    {
        $this->assertHttpError(403, fn () => $this->memberships->removeStudent('student', 'class-a', 'student'));
        $this->assertHttpError(403, fn () => $this->memberships->removeStudent('teacher', 'class-a', 'other'));
        $this->assertSame(0, $this->writes);
    }

    public function test_members_and_pending_applicants_cannot_apply_to_a_second_block(): void
    {
        $this->data['users']['student']['block_id'] = 'a';
        $this->assertInvalid(fn () => $this->memberships->apply('student', 'b'));
        $this->assertSame(0, $this->writes);
        $this->memberships->leave('student');
        $this->memberships->apply('student', 'b');
        $this->assertInvalid(fn () => $this->memberships->apply('student', 'a'));
        $this->assertSame('b', $this->data['users']['student']['block_application']['block_id']);
    }

    public function test_canceling_and_reapplying_invalidates_the_old_approval_link(): void
    {
        $this->memberships->apply('student', 'a');
        $old = $this->data['users']['student']['block_application']['id'];
        $this->memberships->cancel('student');
        $this->memberships->apply('student', 'a');
        $this->assertNotSame($old, $this->data['users']['student']['block_application']['id']);
        $this->assertHttpError(404, fn () => $this->memberships->review('teacher', 'student', $old, true));
        $this->assertArrayNotHasKey('block_id', $this->data['users']['student']);
    }

    public function test_wrong_teacher_and_former_owner_cannot_review_an_application(): void
    {
        $this->memberships->apply('student', 'a');
        $id = $this->data['users']['student']['block_application']['id'];
        $this->assertHttpError(403, fn () => $this->memberships->review('other', 'student', $id, true));
        $this->data['classes']['class-a']['teacher_uid'] = 'other';
        $this->assertHttpError(403, fn () => $this->memberships->review('teacher', 'student', $id, false));
        $this->memberships->review('other', 'student', $id, true);
        $this->assertSame('a', $this->data['users']['student']['block_id']);
    }

    public function test_approval_rechecks_current_membership_and_rejects_repeated_approval(): void
    {
        $this->memberships->apply('student', 'a');
        $id = $this->data['users']['student']['block_application']['id'];
        $this->data['users']['student']['block_id'] = 'b';
        $this->assertInvalid(fn () => $this->memberships->review('teacher', 'student', $id, true));
        $this->assertSame('b', $this->data['users']['student']['block_id']);
        unset($this->data['users']['student']['block_id']);
        $this->memberships->review('teacher', 'student', $id, true);
        $this->assertHttpError(404, fn () => $this->memberships->review('teacher', 'student', $id, true));
    }

    public function test_declining_allows_another_application_without_joining(): void
    {
        $this->memberships->apply('student', 'a');
        $this->memberships->review('teacher', 'student', $this->data['users']['student']['block_application']['id'], false);
        $this->assertNull($this->memberships->forStudent('student')['pending']);
        $this->memberships->apply('student', 'b');
        $this->assertArrayNotHasKey('block_id', $this->data['users']['student']);
    }

    public function test_legacy_members_must_leave_and_only_their_own_memberships_are_removed(): void
    {
        $this->data['classes']['legacy'] = ['students' => ['student' => true, 'someone-else' => true]];
        $this->assertNotNull($this->memberships->forStudent('student')['current']);
        $this->assertInvalid(fn () => $this->memberships->apply('student', 'a'));
        $this->memberships->leave('student');
        $this->assertSame(['someone-else' => true], $this->data['classes']['legacy']['students']);
        $this->assertSame('Ada', $this->data['users']['student']['name']);
        $this->memberships->apply('student', 'a');
    }

    public function test_missing_blocks_and_blocks_without_teachers_cannot_receive_applications(): void
    {
        unset($this->data['classes']['class-a']['teacher_uid']);
        $this->assertInvalid(fn () => $this->memberships->apply('student', 'a'));
        $this->assertInvalid(fn () => $this->memberships->apply('student', 'missing'));
        $this->assertSame(['b' => 'Block B'], $this->memberships->forStudent('student')['available']);
        $this->assertSame(0, $this->writes);
    }

    public function test_nonstudent_and_nonteacher_roles_are_rejected(): void
    {
        $this->assertHttpError(403, fn () => $this->memberships->apply('teacher', 'a'));
        $this->assertHttpError(403, fn () => $this->memberships->forTeacher('student'));
    }

    public function test_database_failure_does_not_look_like_an_empty_membership(): void
    {
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('getReference')->andThrow(new RuntimeException('private details'));
        $this->expectException(ClassroomUnavailable::class);
        $this->expectExceptionMessage('Block information could not be updated or loaded.');
        (new BlockMemberships($database, $this->fullNames()))->forStudent('student');
    }

    private function assertInvalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the membership change to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('block', $exception->errors());
        }
    }

    private function assertHttpError(int $status, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected access to be rejected.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
