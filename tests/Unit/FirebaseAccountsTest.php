<?php

namespace Tests\Unit;

use App\Exceptions\AccountException;
use App\Services\FirebaseAccounts;
use App\Services\StudentEnrollment;
use Kreait\Firebase\Auth\SendActionLink\FailedToSendActionLink;
use Kreait\Firebase\Auth\SignIn\FailedToSignIn;
use Kreait\Firebase\Auth\SignInResult;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FirebaseAccountsTest extends TestCase
{
    private $auth;

    private $database;

    private $reference;

    private FirebaseAccounts $accounts;

    private array $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = Mockery::mock(Auth::class);
        $this->database = Mockery::mock(Database::class);
        $this->reference = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->with('users/test-user')->andReturn($this->reference)->byDefault();
        $this->accounts = new FirebaseAccounts($this->auth, $this->database, $this->fullNames());
        $this->profile = [
            'uid' => 'test-user', 'name' => 'Test User', 'email' => 'test@example.com', 'role' => 'student', 'must_change_password' => false,
        ];
    }

    private function record(array $changes = []): UserRecord
    {
        return UserRecord::fromResponseData(array_merge([
            'localId' => 'test-user', 'email' => 'test@example.com', 'emailVerified' => true,
            'displayName' => 'Test User', 'createdAt' => '1700000000000',
            'passwordUpdatedAt' => '1700000000000', 'validSince' => '1700000000',
        ], $changes));
    }

    private function expectReauthentication(): array
    {
        $this->auth->shouldReceive('signInWithEmailAndPassword')->with('test@example.com', 'OldSecret123')
            ->andReturn(SignInResult::fromData(['localId' => 'test-user']))->byDefault();
        $this->auth->shouldReceive('getUser')->with('test-user')->andReturn($this->record());
        $this->reference->shouldReceive('getValue')->andReturn($this->profile);

        return array_merge($this->accounts->identity('test-user'), ['authenticated_at' => now()->timestamp]);
    }

    public function test_registration_compensates_when_profile_creation_fails(): void
    {
        $this->auth->shouldReceive('createUser')->once()->andReturn($this->record());
        $this->reference->shouldReceive('set')->once()->andThrow(new RuntimeException('database outage'));
        $this->auth->shouldReceive('deleteUser')->once()->with('test-user');
        $this->reference->shouldReceive('remove')->once();
        $this->expectException(AccountException::class);
        $this->accounts->create(['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'Secret123', 'role' => 'teacher'], 'admin');
    }

    public function test_public_creation_is_rejected(): void
    {
        $this->auth->shouldNotReceive('createUser');
        $this->expectException(AccountException::class);
        $this->accounts->create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'Secret123', 'role' => 'admin']);
    }

    public function test_identity_uses_full_name_when_profile_also_has_a_username(): void
    {
        $this->auth->shouldReceive('getUser')->with('test-user')->andReturn($this->record(['displayName' => 'gamer123']));
        $this->reference->shouldReceive('getValue')->andReturn($this->profile + ['fullname' => 'Ada Lovelace', 'username' => 'gamer123']);
        $this->assertSame('Ada Lovelace', $this->accounts->identity('test-user')['name']);
    }

    public function test_admin_created_accounts_require_password_replacement(): void
    {
        $this->auth->shouldReceive('createUser')->once()->andReturn($this->record());
        $this->reference->shouldReceive('set')->once()->with(Mockery::on(fn ($profile) => $profile['role'] === 'teacher' && $profile['must_change_password'] && $profile['temporary_password_version'] !== ''));
        $this->accounts->create(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'Secret123', 'role' => 'teacher'], 'admin-user');
    }

    public function test_administrator_cannot_bypass_student_import_with_a_password(): void
    {
        $this->auth->shouldNotReceive('createUser');
        $this->expectException(AccountException::class);
        $this->accounts->create(['role' => 'student'], 'admin');
    }

    public function test_missing_profile_requires_import_and_keeps_existing_uid_and_password(): void
    {
        $this->auth->shouldReceive('getUser')->with('test-user')->andReturn($this->record());
        $this->auth->shouldReceive('signInWithEmailAndPassword')->with('test@example.com', 'UnitySecret123')
            ->andReturn(SignInResult::fromData(['localId' => 'test-user']));
        $this->auth->shouldNotReceive('createUser');
        $this->auth->shouldNotReceive('updateUser');
        $this->reference->shouldReceive('getValue')->andReturn(null);
        $enrollment = Mockery::mock(StudentEnrollment::class);
        $enrollment->shouldReceive('admitExisting')->once()->with('test-user', 'test@example.com')->andReturn($this->profile + ['fullname' => 'Ada Lovelace']);
        $this->app->instance(StudentEnrollment::class, $enrollment);
        $user = $this->accounts->signIn('test@example.com', 'UnitySecret123');
        $this->assertSame('test-user', $user['uid']);
        $this->assertSame('Ada Lovelace', $user['name']);
        $this->assertTrue($user['email_verified']);
    }

    public function test_unimported_orphan_is_denied(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record());
        $this->reference->shouldReceive('getValue')->andReturn(null);
        $enrollment = Mockery::mock(StudentEnrollment::class);
        $enrollment->shouldReceive('admitExisting')->andThrow(new AccountException('Contact your administrator.'));
        $this->app->instance(StudentEnrollment::class, $enrollment);
        $this->expectException(AccountException::class);
        $this->accounts->identity('test-user', repairMissing: true);
    }

    public function test_incomplete_activation_cannot_be_bypassed_with_password_recovery(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record());
        $this->reference->shouldReceive('getValue')->andReturn($this->profile + ['activation_required' => true]);
        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Complete New User activation');
        $this->accounts->identity('test-user', repairMissing: true);
    }

    public function test_existing_administrator_issued_password_requirement_is_preserved_on_login(): void
    {
        $this->auth->shouldReceive('signInWithEmailAndPassword')->once()->andReturn(SignInResult::fromData(['localId' => 'test-user']));
        $this->auth->shouldReceive('getUser')->with('test-user')->andReturn($this->record());
        $this->reference->shouldReceive('getValue')->andReturn(array_merge($this->profile, ['must_change_password' => true, 'created_by' => 'admin-user']));
        $this->database->shouldNotReceive('runTransaction');
        $this->auth->shouldNotReceive('updateUser');
        $this->assertTrue($this->accounts->signIn('test@example.com', 'Temporary123')['must_change_password']);
    }

    public function test_remote_email_is_canonical_even_when_reconciliation_fails(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record(['email' => 'new@example.com']));
        $this->reference->shouldReceive('getValue')->andReturn($this->profile);
        $this->reference->shouldReceive('update')->once()->andThrow(new RuntimeException('database outage'));
        $this->assertSame('new@example.com', $this->accounts->identity('test-user')['email']);
    }

    public function test_profile_write_failure_does_not_leave_the_session_stale_after_update(): void
    {
        $user = $this->expectReauthentication();
        $this->auth->shouldReceive('updateUser')->once()->with('test-user', ['email' => 'new@example.com', 'emailVerified' => false])
            ->andReturn($this->record(['email' => 'new@example.com', 'emailVerified' => false]));
        $this->reference->shouldReceive('update')->once()->andThrow(new RuntimeException('database outage'));
        $this->accounts->update($user, ['email' => 'new@example.com', 'current_password' => 'OldSecret123']);
        $this->assertSame('new@example.com', session('firebase_user.email'));
        $this->assertFalse(session('firebase_user.email_verified'));
        $this->assertNotSame($user['credential_version'], session('firebase_user.credential_version'));
        $this->assertNotNull(session('warning'));
    }

    public function test_password_reset_clears_temporary_password_requirement_during_reconciliation(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record(['passwordUpdatedAt' => '1800000000000']));
        $this->reference->shouldReceive('getValue')->andReturn(array_merge($this->profile, [
            'must_change_password' => true, 'temporary_password_version' => '1700000000.000000',
        ]));
        $this->reference->shouldReceive('update')->once()->with(['email' => 'test@example.com', 'must_change_password' => false]);
        $this->assertFalse($this->accounts->identity('test-user')['must_change_password']);
    }

    public function test_wrong_password_is_distinguished_from_an_outage(): void
    {
        $this->auth->shouldReceive('signInWithEmailAndPassword')->andThrow(new FailedToSignIn('INVALID_LOGIN_CREDENTIALS'));
        try {
            $this->accounts->signIn('test@example.com', 'Wrong123');
            $this->fail('Expected an account exception.');
        } catch (AccountException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame('email', $exception->field);
        }
    }

    public function test_sign_in_outage_returns_a_safe_error(): void
    {
        $this->auth->shouldReceive('signInWithEmailAndPassword')->andThrow(new RuntimeException('Secret request body'));
        try {
            $this->accounts->signIn('test@example.com', 'Secret123');
            $this->fail('Expected an account exception.');
        } catch (AccountException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertStringNotContainsString('Secret request body', $exception->getMessage());
        }
    }

    public function test_unknown_recovery_email_does_not_reveal_account_existence(): void
    {
        $this->auth->shouldReceive('sendPasswordResetLink')->with('unknown@example.com')->andThrow(new UserNotFound('not found'));
        $this->accounts->sendPasswordReset('unknown@example.com');
        $this->auth->shouldHaveReceived('sendPasswordResetLink')->once();
    }

    public function test_reauthentication_must_match_the_session_uid(): void
    {
        $user = $this->expectReauthentication();
        $this->auth->shouldReceive('signInWithEmailAndPassword')->andReturn(SignInResult::fromData(['localId' => 'someone-else']));
        $this->auth->shouldReceive('getUser')->with('someone-else')->andReturn($this->record(['localId' => 'someone-else']));
        $otherReference = Mockery::mock(Reference::class);
        $otherReference->shouldReceive('getValue')->andReturn(array_merge($this->profile, ['uid' => 'someone-else']));
        $this->database->shouldReceive('getReference')->with('users/someone-else')->andReturn($otherReference);
        $this->auth->shouldNotReceive('updateUser');
        $this->expectException(AccountException::class);
        $this->accounts->update($user, ['email' => 'new@example.com', 'current_password' => 'OldSecret123']);
    }

    public function test_recovery_handles_the_sdk_action_link_error_without_account_disclosure(): void
    {
        $this->auth->shouldReceive('sendPasswordResetLink')->once()->andThrow(new FailedToSendActionLink('EMAIL_NOT_FOUND'));
        $this->accounts->sendPasswordReset('unknown@example.com');
        $this->auth->shouldHaveReceived('sendPasswordResetLink')->once();
    }

    public function test_disabled_accounts_are_rejected_before_reading_the_profile(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record(['disabled' => true]));
        $this->database->shouldNotReceive('getReference');
        $this->expectException(AccountException::class);
        $this->accounts->identity('test-user');
    }

    public function test_unknown_profile_roles_fail_closed(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record());
        $this->reference->shouldReceive('getValue')->andReturn(array_merge($this->profile, ['role' => 'superuser']));
        $this->expectException(AccountException::class);
        $this->accounts->identity('test-user');
    }

    public function test_revocation_changes_the_session_credential_version(): void
    {
        $this->auth->shouldReceive('getUser')->andReturn($this->record(), $this->record(['validSince' => '1800000000']));
        $this->reference->shouldReceive('getValue')->andReturn($this->profile);
        $original = $this->accounts->identity('test-user');
        $revoked = $this->accounts->identity('test-user');
        $this->assertNotSame($original['credential_version'], $revoked['credential_version']);
    }

    public function test_password_replacement_changes_the_session_version_and_clears_the_requirement(): void
    {
        $user = $this->expectReauthentication();
        $user['must_change_password'] = true;
        $this->auth->shouldReceive('updateUser')->once()->with('test-user', ['password' => 'NewSecret123'])
            ->andReturn($this->record(['passwordUpdatedAt' => '1800000000000']));
        $this->reference->shouldReceive('update')->once()->with(['email' => 'test@example.com', 'must_change_password' => false]);
        $this->accounts->update($user, ['email' => 'test@example.com', 'current_password' => 'OldSecret123', 'new_password' => 'NewSecret123']);
        $this->assertFalse(session('firebase_user.must_change_password'));
        $this->assertNotSame($user['credential_version'], session('firebase_user.credential_version'));
    }

    public function test_an_uncertain_authentication_update_invalidates_the_session(): void
    {
        $user = $this->expectReauthentication();
        session()->put('firebase_user', $user);
        $this->auth->shouldReceive('updateUser')->once()->andThrow(new RuntimeException('timeout'));
        try {
            $this->accounts->update($user, ['email' => 'new@example.com', 'current_password' => 'OldSecret123']);
            $this->fail('Expected an account exception.');
        } catch (AccountException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertNull(session('firebase_user'));
        }
    }
}
