<?php

namespace Tests\Unit;

use App\Exceptions\AccountException;
use App\Services\AdminAccounts;
use App\Services\StudentEnrollment;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminAccountsTest extends TestCase
{
    private const CLASS_ID = 'a0000000-0000-4000-8000-000000000001';

    private $auth;

    private $database;

    private AdminAccounts $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = Mockery::mock(Auth::class);
        $this->database = Mockery::mock(Database::class);
        $this->accounts = new AdminAccounts($this->auth, $this->database, $this->fullNames());
    }

    private function read(string $path, mixed $value): void
    {
        $reference = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->with($path)->once()->andReturn($reference);
        $reference->shouldReceive('getValue')->once()->andReturn($value);
    }

    private function root(): mixed
    {
        $root = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->withNoArgs()->once()->andReturn($root);

        return $root;
    }

    public function test_account_management_uses_full_names_instead_of_usernames(): void
    {
        $this->read('users', ['ada' => ['role' => 'student', 'fullname' => 'Ada Lovelace', 'name' => 'gamer123', 'username' => 'gamer123']]);
        $this->assertSame('Ada Lovelace', $this->accounts->all()[0]['name']);
    }

    public function test_account_listing_excludes_admins_and_private_metadata(): void
    {
        $this->read('users', [
            'student' => ['name' => 'Student', 'email' => 's@example.com', 'role' => 'student', 'temporary_password_version' => 'secret'],
            'teacher' => ['name' => 'Teacher', 'email' => 't@example.com', 'role' => 'teacher'],
            'admin' => ['name' => 'Admin', 'role' => 'admin'],
            'unknown' => ['role' => 'unexpected'],
        ]);
        $this->assertSame([
            ['uid' => 'student', 'name' => 'Student', 'email' => 's@example.com', 'role' => 'student'],
            ['uid' => 'teacher', 'name' => 'Teacher', 'email' => 't@example.com', 'role' => 'teacher'],
        ], $this->accounts->all());
    }

    #[DataProvider('accountRoles')]
    public function test_deletion_removes_auth_profile_and_relationships_without_deleting_classes(string $role): void
    {
        $this->read('users/victim', ['name' => 'User', 'role' => $role]);
        $this->read('classes', [self::CLASS_ID => ['teacher_uid' => 'victim', 'students' => ['victim' => true, 'other' => true]]]);
        $this->auth->shouldReceive('deleteUser')->once()->with('victim')->globally()->ordered();
        $this->root()->shouldReceive('update')->once()->with([
            'users/victim' => null, 'teacher_classes/victim' => null,
            'classes/'.self::CLASS_ID.'/teacher_uid' => null,
            'classes/'.self::CLASS_ID.'/students/victim' => null,
        ])->globally()->ordered();
        $this->accounts->delete('victim', 'admin');
    }

    public static function accountRoles(): array
    {
        return [['student'], ['teacher']];
    }

    public function test_current_admin_profile_cannot_be_deleted_even_after_opening_confirmation_earlier(): void
    {
        $this->read('users/victim', ['role' => 'admin']);
        $this->auth->shouldNotReceive('deleteUser');
        try {
            $this->accounts->delete('victim', 'admin');
            $this->fail('Administrator deletion should be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_administrator_cannot_delete_their_own_uid(): void
    {
        $this->database->shouldNotReceive('getReference');
        $this->auth->shouldNotReceive('deleteUser');
        try {
            $this->accounts->delete('admin', 'admin');
            $this->fail('Self-deletion should be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_path_injection_is_rejected_without_database_access(): void
    {
        $this->database->shouldNotReceive('getReference');
        $this->auth->shouldNotReceive('deleteUser');
        try {
            $this->accounts->delete('../admin', 'admin');
            $this->fail('Invalid UID should be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_preflight_database_failure_never_deletes_auth(): void
    {
        $this->read('users/victim', ['role' => 'student']);
        $this->database->shouldReceive('getReference')->with('classes')->andThrow(new RuntimeException('Private details'));
        $this->auth->shouldNotReceive('deleteUser');
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Accounts are temporarily unavailable.');
        $this->accounts->delete('victim', 'admin');
    }

    public function test_auth_failure_does_not_remove_profile(): void
    {
        $this->read('users/victim', ['role' => 'student']);
        $this->read('classes', []);
        $this->auth->shouldReceive('deleteUser')->with('victim')->andThrow(new RuntimeException('Private details'));
        $this->database->shouldNotReceive('getReference')->withNoArgs();
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Account deletion could not be confirmed.');
        $this->accounts->delete('victim', 'admin');
    }

    public function test_partial_cleanup_failure_is_reported_instead_of_claiming_success(): void
    {
        $this->read('users/victim', ['role' => 'student']);
        $this->read('classes', []);
        $this->auth->shouldReceive('deleteUser')->with('victim')->once();
        $this->root()->shouldReceive('update')->andThrow(new RuntimeException('Private details'));
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Sign-in access was removed, but profile cleanup could not be confirmed.');
        $this->accounts->delete('victim', 'admin');
    }

    public function test_retry_finishes_cleanup_when_auth_account_is_already_gone(): void
    {
        $this->read('users/victim', ['role' => 'student']);
        $this->read('classes', []);
        $this->auth->shouldReceive('deleteUser')->with('victim')->andThrow(new UserNotFound('Missing user'));
        $this->root()->shouldReceive('update')->once()->with(['users/victim' => null, 'teacher_classes/victim' => null]);
        $this->accounts->delete('victim', 'admin');
    }

    public function test_deletion_also_clears_the_matching_invitation_so_admin_can_reimport_later(): void
    {
        $this->read('users/victim', ['role' => 'student', 'email' => 's@example.com']);
        $this->read('classes', []);
        $path = 'student_invitations/'.StudentEnrollment::key('s@example.com');
        $this->read($path, ['uid' => 'victim', 'status' => 'active']);
        $this->auth->shouldReceive('deleteUser')->once()->with('victim');
        $this->root()->shouldReceive('update')->once()->with(['users/victim' => null, 'teacher_classes/victim' => null, $path => null]);
        $this->accounts->delete('victim', 'admin');
    }
}
