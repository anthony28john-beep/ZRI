<?php

namespace Tests\Feature;

use App\Exceptions\ClassroomUnavailable;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use Mockery;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    private $classes;

    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = ['uid' => 'admin-user', 'name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin', 'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $accounts = Mockery::mock(FirebaseAccounts::class);
        $accounts->shouldReceive('identity')->with('admin-user')->andReturnUsing(fn () => $this->identity);
        $this->app->instance(FirebaseAccounts::class, $accounts);
        $this->classes = Mockery::mock(Classrooms::class);
        $this->classes->shouldReceive('blocks')->andReturn(['a0000000-0000-4000-8000-000000000001' => 'Block A'])->byDefault();
        $this->app->instance(Classrooms::class, $this->classes);
    }

    private function signIn(string $role = 'admin'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    public function test_admin_home_is_a_landing_page_with_navigation_and_working_shortcuts(): void
    {
        $this->signIn()->get('/dashboard')->assertOk()->assertSee('Admin User')
            ->assertSeeInOrder(['Home', 'Create Account', 'Manage Classes', 'Log Out'])
            ->assertSee('Build your classroom in three steps')->assertSee('Blocks available for student enrollment')->assertSee('Import Students')
            ->assertSee('href="'.route('dashboard').'" class="admin-nav-link is-active"', false)
            ->assertSee('href="'.route('admin.users.create').'"', false)
            ->assertDontSee('name="password_confirmation"', false);
    }

    public function test_create_account_page_keeps_account_fields_and_highlights_its_navigation(): void
    {
        $this->signIn()->get('/admin/users/create')->assertOk()->assertSee('Add New Account')->assertSee('Import Students')
            ->assertSee('name="name"', false)->assertSee('name="email"', false)->assertSee('name="role"', false)
            ->assertDontSee('name="block_id"', false)->assertDontSee('value="student"', false)->assertSee('name="password"', false)->assertSee('name="password_confirmation"', false)
            ->assertSee('action="'.route('admin.users.store').'"', false)
            ->assertSee('href="'.route('admin.users.create').'" class="admin-nav-link is-active"', false);
    }

    public function test_create_account_route_requires_admin_role_and_ready_account(): void
    {
        $this->get('/admin/users/create')->assertRedirect('/login');
        $this->signIn('student')->get('/admin/users/create')->assertForbidden();
        $this->signIn('teacher')->get('/admin/users/create')->assertForbidden();
        $this->identity['email_verified'] = false;
        $this->signIn()->get('/admin/users/create')->assertRedirect('/verify-email');
    }

    public function test_classes_page_keeps_management_tools_and_uses_the_shared_navigation(): void
    {
        $this->classes->shouldReceive('administration')->once()->andReturn(['classes' => [], 'users' => [], 'blocks' => []]);
        $this->signIn()->get('/admin/classes')->assertOk()->assertSee('id="blocks-heading"', false)
            ->assertSee('Move students to another block')->assertSee('Create Account')
            ->assertSee('href="'.route('admin.classes').'" class="admin-nav-link is-active"', false)
            ->assertSee('action="'.route('logout').'"', false);
    }

    public function test_block_outage_preserves_admin_navigation_and_teacher_creation_form(): void
    {
        $this->classes->shouldReceive('blocks')->andThrow(new ClassroomUnavailable('Private details'));
        $this->signIn()->get('/dashboard')->assertOk()->assertSee('Block information temporarily unavailable')->assertDontSee('Private details');
        $this->get('/admin/users/create')->assertOk()->assertSee('Create a teacher account')
            ->assertSee('Temporary password')->assertDontSee('Private details');
    }
}
