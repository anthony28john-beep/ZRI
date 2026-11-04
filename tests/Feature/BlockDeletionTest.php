<?php

namespace Tests\Feature;

use App\Exceptions\ClassroomUnavailable;
use App\Services\Classrooms;
use App\Services\FirebaseAccounts;
use Mockery;
use Tests\TestCase;

class BlockDeletionTest extends TestCase
{
    private const BLOCK = 'a0000000-0000-4000-8000-000000000001';

    private $classes;

    private array $identity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = ['uid' => 'admin', 'name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin', 'email_verified' => true, 'must_change_password' => false, 'credential_version' => 'v1'];
        $accounts = Mockery::mock(FirebaseAccounts::class);
        $accounts->shouldReceive('identity')->with('admin')->andReturnUsing(fn () => $this->identity);
        $this->app->instance(FirebaseAccounts::class, $accounts);
        $this->classes = Mockery::mock(Classrooms::class);
        $this->app->instance(Classrooms::class, $this->classes);
    }

    private function signIn(string $role = 'admin'): static
    {
        $this->identity['role'] = $role;

        return $this->withSession(['firebase_user' => $this->identity + ['authenticated_at' => now()->timestamp]]);
    }

    public function test_block_deletion_is_available_only_to_verified_admins(): void
    {
        $url = '/admin/blocks/'.self::BLOCK;
        $this->get($url.'/delete')->assertRedirect('/login');
        $this->delete($url, ['confirmation' => 'DELETE'])->assertRedirect('/login');
        foreach (['student', 'teacher'] as $role) {
            $this->signIn($role)->get($url.'/delete')->assertForbidden();
            $this->delete($url, ['confirmation' => 'DELETE'])->assertForbidden();
        }
        $this->identity['email_verified'] = false;
        $this->signIn()->get($url.'/delete')->assertRedirect('/verify-email');
    }

    public function test_unused_block_requires_explicit_confirmation(): void
    {
        $this->classes->shouldReceive('blockDeletionDetails')->once()->with(self::BLOCK)->andReturn(['id' => self::BLOCK, 'name' => 'BSIT 1A', 'students' => 0, 'classes' => 0]);
        $url = '/admin/blocks/'.self::BLOCK;
        $this->signIn()->get($url.'/delete')->assertOk()->assertSee('BSIT 1A')->assertSee('Type DELETE to confirm');
        $this->delete($url, ['confirmation' => 'yes'])->assertSessionHasErrors('confirmation');
        $this->classes->shouldReceive('deleteBlock')->once()->with(self::BLOCK);
        $this->delete($url, ['confirmation' => 'DELETE'])->assertRedirect('/admin/classes')->assertSessionHas('success');
    }

    public function test_used_block_explains_what_to_move_and_has_no_delete_form(): void
    {
        $this->classes->shouldReceive('blockDeletionDetails')->once()->andReturn(['id' => self::BLOCK, 'name' => 'BSIT 1A', 'students' => 5, 'classes' => 1]);
        $this->signIn()->get('/admin/blocks/'.self::BLOCK.'/delete')->assertOk()->assertSee('This block is still in use')
            ->assertSee('5 students')->assertDontSee('name="confirmation"', false);
    }

    public function test_delete_failure_never_reports_success(): void
    {
        $this->classes->shouldReceive('deleteBlock')->andThrow(new ClassroomUnavailable('The block could not be deleted.'));
        $this->signIn()->delete('/admin/blocks/'.self::BLOCK, ['confirmation' => 'DELETE'])->assertRedirect('/admin/classes')
            ->assertSessionHasErrors('block')->assertSessionMissing('success');
    }
}
