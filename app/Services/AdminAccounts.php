<?php

namespace App\Services;

use App\Exceptions\AccountException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Throwable;

class AdminAccounts
{
    public function __construct(private Auth $auth, private Database $database, private FullNames $names) {}

    public function all(): array
    {
        $accounts = [];
        foreach ($this->read('users') as $uid => $profile) {
            if (is_array($profile) && in_array($profile['role'] ?? null, ['student', 'teacher'], true) && $this->validUid((string) $uid)) {
                $accounts[] = $this->present((string) $uid, $profile);
            }
        }

        return $accounts;
    }

    public function target(string $uid, string $administrator): array
    {
        abort_unless($this->validUid($uid), 404);
        abort_if($uid === $administrator, 403);
        $profile = $this->read('users/'.$uid);
        abort_if(! $profile, 404);
        // Recheck the live profile; a forged role or an old confirmation page grants nothing.
        abort_unless(in_array($profile['role'] ?? null, ['student', 'teacher'], true), 403);

        return $this->present($uid, $profile);
    }

    public function delete(string $uid, string $administrator): void
    {
        $target = $this->target($uid, $administrator);
        // Read relationships before the irreversible Auth operation. Failed reads stop deletion.
        $classes = $this->read('classes');
        $updates = ['users/'.$uid => null, 'teacher_classes/'.$uid => null];
        if ($target['email'] !== '') {
            $invitationPath = 'student_invitations/'.StudentEnrollment::key($target['email']);
            $invitation = $this->read($invitationPath);
            if (($invitation['uid'] ?? $invitation['activation_uid'] ?? null) === $uid) {
                $updates[$invitationPath] = null;
            }
        }
        foreach ($classes as $id => $class) {
            if (! Str::isUuid((string) $id) || ! is_array($class)) {
                continue;
            }
            if (($class['teacher_uid'] ?? null) === $uid) {
                $updates['classes/'.$id.'/teacher_uid'] = null;
            }
            if (isset($class['students'][$uid])) {
                $updates['classes/'.$id.'/students/'.$uid] = null;
            }
        }
        try {
            // Delete Auth first so a missing profile cannot be recreated by a later login.
            $this->auth->deleteUser($uid);
        } catch (UserNotFound) {
            // A prior partial deletion may already have removed Auth. Finish the cleanup.
        } catch (Throwable $exception) {
            $this->logFailure('delete_auth', $exception);
            throw new AccountException('Account deletion could not be confirmed. Please retry from Manage Accounts.', status: 503);
        }
        try {
            $this->database->getReference()->update($updates);
        } catch (Throwable $exception) {
            $this->logFailure('delete_profile_cleanup', $exception);
            throw new AccountException('Sign-in access was removed, but profile cleanup could not be confirmed. If the account is still listed, retry deletion to finish cleanup.', status: 503);
        }
        try {
            Cache::forget('teacher-results:'.hash('sha256', $uid));
        } catch (Throwable $exception) {
            $this->logFailure('delete_cached_results', $exception);
        }
        Log::notice('Student or teacher account deleted.', ['uid' => $uid, 'role' => $target['role'], 'administrator_uid' => $administrator]);
    }

    private function read(string $path): array
    {
        try {
            $value = $this->database->getReference($path)->getValue();

            return is_array($value) ? $value : [];
        } catch (Throwable $exception) {
            $this->logFailure('read_accounts', $exception);
            throw new AccountException('Accounts are temporarily unavailable. Please try again shortly.', status: 503);
        }
    }

    private function present(string $uid, array $profile): array
    {
        return [
            'uid' => $uid, 'name' => $this->names->forProfile($uid, $profile, 'Unnamed account'),
            'email' => $profile['email'] ?? '', 'role' => $profile['role'],
        ];
    }

    private function validUid(string $uid): bool
    {
        return $uid !== '' && strlen($uid) <= 128 && preg_match('/[.\x00-\x20#$\[\]\/\x7f]/', $uid) === 0;
    }

    private function logFailure(string $operation, Throwable $exception): void
    {
        Log::warning('Admin account operation failed.', ['operation' => $operation, 'exception_type' => $exception::class]);
    }
}
