<?php

namespace App\Services;

use App\Exceptions\AccountException;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\SendActionLink\FailedToSendActionLink;
use Kreait\Firebase\Auth\SignIn\FailedToSignIn;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Kreait\Firebase\Exception\Auth\EmailNotFound;
use Kreait\Firebase\Exception\Auth\UserDisabled;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Throwable;

class FirebaseAccounts
{
    public function __construct(private Auth $auth, private Database $database, private FullNames $names) {}

    public function signIn(string $email, #[\SensitiveParameter] string $password): array
    {
        try {
            $result = $this->auth->signInWithEmailAndPassword($email, $password);
            $uid = $result->firebaseUserId();
            if (! $uid) {
                throw new AccountException('Sign in could not be completed. Please try again.', 'email', 503);
            }

            return $this->identity($uid, repairMissing: true);
        } catch (FailedToSignIn $exception) {
            $code = $exception->getMessage();
            foreach (['INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD', 'EMAIL_NOT_FOUND', 'USER_DISABLED'] as $error) {
                if (str_contains($code, $error)) {
                    throw new AccountException('The email address or password is incorrect, or the account is unavailable.', 'email');
                }
            }
            if (str_contains($code, 'TOO_MANY_ATTEMPTS')) {
                throw new AccountException('Too many attempts. Please wait before trying again.', 'email');
            }
            $this->unavailable('sign_in', $exception);
        } catch (AccountException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->unavailable('sign_in', $exception);
        }
    }

    public function create(#[\SensitiveParameter] array $data, ?string $createdBy = null): void
    {
        if (! $createdBy || ($data['role'] ?? null) !== 'teacher') {
            throw new AccountException('Teachers import students through CSV. This form creates teacher accounts only.', 'role');
        }
        $uid = null;
        try {
            $record = $this->auth->createUser([
                'email' => $data['email'], 'password' => $data['password'],
                'displayName' => $data['name'], 'emailVerified' => false, 'disabled' => false,
            ]);
            $uid = $record->uid;
            $this->database->getReference('users/'.$uid)->set([
                'uid' => $uid, 'name' => $data['name'], 'fullname' => $data['name'], 'email' => $record->email,
                'role' => 'teacher',
                'created_at' => now()->toIso8601String(), 'created_by' => $createdBy,
                'must_change_password' => $createdBy !== null,
                'temporary_password_version' => $createdBy ? $this->passwordVersion($record) : null,
            ]);
        } catch (EmailExists) {
            throw new AccountException('This email address is already registered. Sign in or reset your password.', 'email');
        } catch (Throwable $exception) {
            if ($uid) {
                try {
                    $this->auth->deleteUser($uid);
                    $this->database->getReference('users/'.$uid)->remove();
                } catch (Throwable $cleanup) {
                    $this->logFailure('creation_cleanup', $cleanup, $uid);
                }
            }
            $this->unavailable('create_account', $exception, $uid);
        }
    }

    public function identity(string $uid, bool $repairMissing = false): array
    {
        try {
            $record = $this->auth->getUser($uid);
            if ($record->disabled || ! $record->email) {
                throw new AccountException('Your account is unavailable. Please sign in again.', status: 401);
            }
            $reference = $this->database->getReference('users/'.$uid);
            $profile = $reference->getValue();
            if ($profile === null && $repairMissing) {
                $profile = app(StudentEnrollment::class)->admitExisting($uid, $record->email);
            }
            if (! empty($profile['activation_required'])) {
                throw new AccountException('Complete New User activation before signing in.', 'email');
            }
            if (! is_array($profile) || ! in_array($profile['role'] ?? null, ['admin', 'teacher', 'student'], true)) {
                throw new AccountException('Your account profile is unavailable. Please sign in again or contact an administrator.', status: 401);
            }
            $mustChange = (bool) ($profile['must_change_password'] ?? false);
            if ($mustChange && isset($profile['temporary_password_version']) && $profile['temporary_password_version'] !== $this->passwordVersion($record)) {
                $mustChange = false;
            }
            $updates = ['email' => $record->email, 'must_change_password' => $mustChange];
            if (($profile['email'] ?? null) !== $record->email || ($profile['must_change_password'] ?? false) !== $mustChange) {
                try {
                    $reference->update($updates);
                } catch (Throwable $exception) {
                    // Auth is canonical. Retry profile reconciliation on the next request.
                    $this->logFailure('profile_reconciliation', $exception, $uid);
                }
            }

            return [
                'uid' => $uid, 'name' => $this->names->forProfile($uid, $profile, 'User'),
                'email' => $record->email, 'role' => $profile['role'],
                'email_verified' => $record->emailVerified, 'must_change_password' => $mustChange,
                'credential_version' => $this->credentialVersion($record),
            ];
        } catch (UserNotFound) {
            throw new AccountException('Your account is unavailable. Please sign in again.', status: 401);
        } catch (AccountException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->unavailable('load_identity', $exception, $uid);
        }
    }

    public function update(array $user, #[\SensitiveParameter] array $data): void
    {
        try {
            $verified = $this->signIn($user['email'], $data['current_password']);
        } catch (AccountException $exception) {
            if ($exception->field === 'email' && $exception->status === 422) {
                throw new AccountException('The current password is incorrect or the account is unavailable.', 'current_password');
            }
            throw $exception;
        }
        if (! hash_equals($user['uid'], $verified['uid'])) {
            throw new AccountException('Please sign in again before changing your account.', status: 401);
        }
        try {
            $changes = [];
            if ($data['email'] !== $user['email']) {
                $changes['email'] = $data['email'];
                $changes['emailVerified'] = false;
            }
            if (! empty($data['new_password'])) {
                if (hash_equals($data['current_password'], $data['new_password'])) {
                    throw new AccountException('Choose a password different from your current password.', 'new_password');
                }
                $changes['password'] = $data['new_password'];
            }
            if (! $changes) {
                throw new AccountException('No account changes were entered.');
            }
            $record = $this->auth->updateUser($user['uid'], $changes);
            // Keep the current session consistent even if the profile write fails.
            $updated = array_merge($user, [
                'email' => $record->email, 'email_verified' => $record->emailVerified,
                'credential_version' => $this->credentialVersion($record),
                'must_change_password' => isset($changes['password']) ? false : $user['must_change_password'],
            ]);
            session()->put('firebase_user', $updated);
            session()->regenerate();
            try {
                $this->database->getReference('users/'.$user['uid'])->update([
                    'email' => $record->email, 'must_change_password' => $updated['must_change_password'],
                ]);
            } catch (Throwable $exception) {
                $this->logFailure('update_profile_pending', $exception, $user['uid']);
                session()->flash('warning', 'Your account changes were saved. Your profile will synchronize automatically.');
            }
        } catch (EmailExists) {
            throw new AccountException('That email address is already being used.', 'email');
        } catch (AccountException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // The remote write might have succeeded despite a timeout. Require a fresh login.
            session()->invalidate();
            session()->regenerateToken();
            $this->unavailable('update_account', $exception, $user['uid']);
        }
    }

    public function sendPasswordReset(string $email): void
    {
        try {
            $this->auth->sendPasswordResetLink($email);
        } catch (UserNotFound|EmailNotFound|UserDisabled) {
            // Use the same response for registered and unregistered email addresses.
        } catch (FailedToSendActionLink $exception) {
            if (! str_contains($exception->getMessage(), 'EMAIL_NOT_FOUND') && ! str_contains($exception->getMessage(), 'USER_DISABLED')) {
                $this->unavailable('send_password_reset', $exception);
            }
        } catch (Throwable $exception) {
            $this->unavailable('send_password_reset', $exception);
        }
    }

    public function sendVerification(array $user): void
    {
        try {
            $this->auth->sendEmailVerificationLink($user['email']);
        } catch (Throwable $exception) {
            $this->unavailable('send_verification', $exception, $user['uid']);
        }
    }

    private function passwordVersion(UserRecord $record): string
    {
        return $record->metadata->passwordUpdatedAt?->format('U.u') ?? '';
    }

    private function credentialVersion(UserRecord $record): string
    {
        return hash('sha256', implode('|', [$record->email, $this->passwordVersion($record), $record->tokensValidAfterTime?->format('U.u')]));
    }

    private function logFailure(string $operation, Throwable $exception, ?string $uid = null): void
    {
        // SDK exceptions can contain request credentials: never log their body, message or trace.
        Log::warning('Firebase operation failed.', ['operation' => $operation, 'exception_type' => $exception::class, 'uid' => $uid]);
    }

    private function unavailable(string $operation, Throwable $exception, ?string $uid = null): never
    {
        $this->logFailure($operation, $exception, $uid);
        throw new AccountException('The account service is temporarily unavailable. Please try again shortly.', status: 503);
    }
}
