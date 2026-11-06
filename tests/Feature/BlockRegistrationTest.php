<?php

namespace Tests\Feature;

use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use Mockery;
use Tests\TestCase;

class BlockRegistrationTest extends TestCase
{
    private const BLOCK = 'a0000000-0000-4000-8000-000000000001';

    private $classes;

    private $accounts;

    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classes = Mockery::mock(Classrooms::class);
        $this->accounts = Mockery::mock(FirebaseAccounts::class);
        $this->app->instance(Classrooms::class, $this->classes);
        $this->app->instance(FirebaseAccounts::class, $this->accounts);
        $this->identity = ['uid' => 'admin', 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin', 'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $this->accounts->shouldReceive('identity')->with('admin')->andReturnUsing(fn () => $this->identity);
    }

    private function registration(): array
    {
        return ['name' => 'Student', 'email' => 'student@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'block_id' => self::BLOCK];
    }

    private function signIn(string $role = 'admin'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    public function test_new_user_page_does_not_offer_public_registration_or_block_selection(): void
    {
        $this->accounts->shouldNotReceive('create');
        $this->get('/new-user')->assertOk()->assertSee('New User')->assertSee('Send verification link')
            ->assertDontSee('name="password"', false)->assertDontSee('name="block_id"', false);
        $this->get('/register')->assertRedirect('/new-user');
        $this->post('/register', $this->registration())->assertStatus(405);
    }

    public function test_only_admins_can_add_blocks_or_assign_existing_students(): void
    {
        $this->post('/admin/blocks', [])->assertRedirect('/login');
        foreach (['student', 'teacher'] as $role) {
            $this->signIn($role)->post('/admin/blocks', [])->assertForbidden();
            $this->put('/admin/blocks/students', [])->assertForbidden();
        }
    }

    public function test_admin_can_add_blocks_and_assign_existing_students(): void
    {
        $this->classes->shouldReceive('createBlock')->once()->with('BSIT 1A');
        $this->signIn()->post('/admin/blocks', ['block_name' => 'BSIT 1A'])->assertRedirect('/admin/classes');
        $this->classes->shouldReceive('assignBlock')->once()->with(self::BLOCK, ['student']);
        $this->put('/admin/blocks/students', ['block_id' => self::BLOCK, 'students' => ['student']])->assertRedirect('/admin/classes');
    }

    public function test_admin_can_assign_a_block_to_a_teacher_with_no_student_checklist(): void
    {
        $data = ['block_id' => self::BLOCK, 'teacher_uid' => 'teacher'];
        $this->classes->shouldReceive('save')->once()->with($data, 'admin', null);
        $this->signIn()->post('/admin/classes', $data)->assertRedirect('/admin/classes');
    }

    public function test_class_management_renders_block_assignment_and_existing_student_tools(): void
    {
        $this->classes->shouldReceive('administration')->once()->andReturn([
            'blocks' => [self::BLOCK => 'BSIT 1A'],
            'users' => ['teacher' => ['uid' => 'teacher', 'name' => 'Teacher', 'role' => 'teacher'], 'student' => ['uid' => 'student', 'name' => 'Student', 'role' => 'student', 'block_id' => self::BLOCK]],
            'classes' => [self::BLOCK => ['name' => 'BSIT 1A', 'teacher_uid' => 'teacher', 'block_id' => self::BLOCK]],
        ]);
        $this->signIn()->get('/admin/classes')->assertOk()->assertSee('id="blocks-heading"', false)->assertSee('BSIT 1A')
            ->assertSee('Current assignments')->assertSee('Move students to another block');
    }

    public function test_admin_creates_teachers_only_and_students_use_csv(): void
    {
        $this->signIn()->post('/admin/users', $this->registration() + ['role' => 'student'])->assertSessionHasErrors('role');
        $this->accounts->shouldReceive('create')->once()->with(Mockery::on(fn ($data) => $data['role'] === 'teacher' && ! isset($data['block_id'])), 'admin');
        $this->post('/admin/users', $this->registration() + ['role' => 'teacher'])->assertSessionHasNoErrors();
    }
}
