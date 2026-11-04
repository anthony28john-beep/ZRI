<?php

namespace Tests\Feature;

use App\Exceptions\AccountException;
use App\Services\AdminAccounts;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use Mockery;
use Tests\TestCase;

class AdminAccountDeletionTest extends TestCase
{
    private $managed;

    private array $identity;

    private array $student = ['uid' => 'student', 'name' => 'Student Name', 'email' => 'student@example.com', 'role' => 'student'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = ['uid' => 'admin', 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin', 'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $accounts = Mockery::mock(FirebaseAccounts::class);
        $accounts->shouldReceive('identity')->with('admin')->andReturnUsing(fn () => $this->identity);
        $this->app->instance(FirebaseAccounts::class, $accounts);
        $this->managed = Mockery::mock(AdminAccounts::class);
        $this->app->instance(AdminAccounts::class, $this->managed);
    }

    private function signIn(string $role = 'admin'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    public function test_account_management_and_deletion_are_admin_only(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/admin/users/student/delete')->assertRedirect('/login');
        $this->delete('/admin/users/student', ['confirmation' => 'DELETE'])->assertRedirect('/login');
        foreach (['student', 'teacher'] as $role) {
            $this->signIn($role)->get('/admin/users')->assertForbidden();
            $this->get('/admin/users/student/delete')->assertForbidden();
            $this->delete('/admin/users/student', ['confirmation' => 'DELETE'])->assertForbidden();
        }
    }

    public function test_admin_can_search_and_filter_student_and_teacher_accounts(): void
    {
        $this->managed->shouldReceive('all')->andReturn([
            $this->student, ['uid' => 'teacher', 'name' => 'Teacher Name', 'email' => 'teacher@example.com', 'role' => 'teacher'],
        ]);
        $this->signIn()->get('/admin/users')->assertOk()->assertSee('Student Name')->assertSee('Teacher Name')->assertSee('Delete account');
        $this->get('/admin/users?role=teacher')->assertOk()->assertSee('Teacher Name')->assertDontSee('Student Name');
        $this->get('/admin/users?q=student@example.com')->assertOk()->assertSee('Student Name')->assertDontSee('Teacher Name');
    }

    public function test_delete_confirmation_explains_retained_history_and_needs_explicit_confirmation(): void
    {
        $this->managed->shouldReceive('target')->once()->with('student', 'admin')->andReturn($this->student);
        $this->signIn()->get('/admin/users/student/delete')->assertOk()->assertSee('Student Name')
            ->assertSee('Type DELETE to confirm')->assertSee('Saved Firestore game results are retained.')
            ->assertSee('name="_method" value="DELETE"', false)->assertSee('Cancel');
        $this->managed->shouldNotReceive('delete');
        $this->from('/admin/users/student/delete')->delete('/admin/users/student', ['confirmation' => 'yes'])->assertSessionHasErrors('confirmation');
    }

    public function test_confirmed_deletion_calls_service_and_returns_to_manage_accounts(): void
    {
        $this->managed->shouldReceive('delete')->once()->with('student', 'admin');
        $this->signIn()->delete('/admin/users/student', ['confirmation' => 'DELETE'])->assertRedirect('/admin/users')->assertSessionHas('success');
    }

    public function test_partial_deletion_shows_error_without_success_message(): void
    {
        $this->managed->shouldReceive('delete')->andThrow(new AccountException('Profile cleanup needs retry.', status: 503));
        $this->signIn()->delete('/admin/users/student', ['confirmation' => 'DELETE'])->assertRedirect('/admin/users')
            ->assertSessionHasErrors('account')->assertSessionMissing('success');
    }

    public function test_deleted_teachers_classes_render_as_unassigned_and_can_be_reassigned(): void
    {
        $classes = Mockery::mock(Classrooms::class);
        $classes->shouldReceive('administration')->andReturn([
            'classes' => ['a0000000-0000-4000-8000-000000000001' => ['name' => 'Retained class', 'students' => ['student' => true]]],
            'users' => [['uid' => 'replacement', 'name' => 'Replacement Teacher', 'role' => 'teacher']],
            'blocks' => [],
        ]);
        $this->app->instance(Classrooms::class, $classes);
        $this->signIn()->get('/admin/classes')->assertOk()->assertSee('Retained class')->assertSee('This class has no assigned teacher')->assertSee('Replacement Teacher');
    }
}
