<?php

namespace App\Services;

use App\Exceptions\AccountException;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Transaction;
use Kreait\Firebase\Exception\Database\TransactionFailed;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class StudentEnrollment
{
    public function __construct(private Database $database) {}

    public static function key(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public function invitation(string $email): ?array
    {
        return $this->read()['student_invitations'][self::key($email)] ?? null;
    }

    public function pending(): array
    {
        $data = $this->read();

        return collect($data['student_invitations'] ?? [])->filter(fn ($row) => ($row['status'] ?? '') === 'pending')
            ->map(fn ($row, $id) => ['id' => $id, 'fullname' => $row['fullname'], 'email' => $row['email'],
                'block' => $data['blocks'][$row['block_id']]['name'] ?? 'Block unavailable'])->values()->all();
    }

    public function preview(array $rows): array
    {
        $data = $this->read();

        return array_map(fn ($row) => $this->assess($row, $data), $rows);
    }

    public function import(array $rows, string $admin): array
    {
        return $this->change(function (&$data) use ($rows, $admin) {
            abort_unless(($data['users'][$admin]['role'] ?? null) === 'admin', 403);
            $report = [];
            foreach ($rows as $row) {
                $row = $this->assess($row, $data);
                if ($row['status'] === 'ready') {
                    $data['student_invitations'][self::key($row['email'])] = [
                        'fullname' => $row['fullname'], 'email' => $row['email'], 'block_id' => $row['block_id'],
                        'status' => 'pending', 'revision' => (string) Str::uuid(),
                        'candidate_uid' => 'learner-'.substr(self::key($row['email']), 0, 40),
                        'created_at' => now()->toIso8601String(), 'created_by' => $admin,
                    ];
                    $row['status'] = 'imported';
                    $row['message'] = 'Ready for New User activation.';
                }
                $report[] = $row;
            }

            return $report;
        });
    }

    public function revoke(string $id, string $admin): void
    {
        $this->change(function (&$data) use ($id, $admin) {
            abort_unless(($data['users'][$admin]['role'] ?? null) === 'admin', 403);
            $invite = $data['student_invitations'][$id] ?? null;
            abort_unless(($invite['status'] ?? '') === 'pending' && ($invite['completing'] ?? 0) <= now()->subMinutes(2)->timestamp, 409);
            unset($data['student_invitations'][$id]);
        });
    }

    public function issue(string $email, string $revision, string $uid, string $token): void
    {
        $this->change(function (&$data) use ($email, $revision, $uid, $token) {
            $invite = &$data['student_invitations'][self::key($email)];
            $this->requirePending($invite, $data);
            if ($invite['revision'] !== $revision || $invite['candidate_uid'] !== $uid || ! empty($invite['completing'])) {
                $this->invalid();
            }
            $invite['activation_uid'] = $uid;
            $invite['token_hash'] = hash('sha256', $token);
            $invite['expires_at'] = now()->addHour()->timestamp;
        });
    }

    public function proof(string $email, string $token): array
    {
        $data = $this->read();
        $invite = $data['student_invitations'][self::key($email)] ?? null;
        $this->requireProof($invite, $data, $token);

        return $invite;
    }

    public function reserve(string $email, string $token): array
    {
        return $this->change(function (&$data) use ($email, $token) {
            $invite = &$data['student_invitations'][self::key($email)];
            $this->requireProof($invite, $data, $token);
            // Only one password update may run at a time, including across server instances.
            if (! empty($invite['completing']) && $invite['completing'] > now()->subMinutes(2)->timestamp) {
                throw new AccountException('Activation is already being completed. Please wait a moment and try again.');
            }
            $uid = $invite['activation_uid'];
            if (isset($data['users'][$uid]) && empty($data['users'][$uid]['activation_required'])) {
                $this->invalid();
            }
            $profile = $this->profile($uid, $invite);
            unset($profile['block_application']);
            $data['users'][$uid] = $profile + ['activation_required' => true];
            $invite['completing'] = now()->timestamp;

            return $invite;
        });
    }

    public function finish(string $email, string $token): void
    {
        $this->change(function (&$data) use ($email, $token) {
            $invite = &$data['student_invitations'][self::key($email)];
            $this->requireProof($invite, $data, $token);
            $uid = $invite['activation_uid'];
            $data['users'][$uid]['block_application'] = $this->profile($uid, $invite)['block_application'];
            unset($data['users'][$uid]['activation_required']);
            $invite['status'] = 'active';
            $invite['uid'] = $uid;
            unset($invite['token_hash'], $invite['expires_at'], $invite['completing']);
        });
    }

    public function release(string $email, string $token): void
    {
        $this->change(function (&$data) use ($email, $token) {
            $invite = &$data['student_invitations'][self::key($email)];
            $this->requireProof($invite, $data, $token);
            unset($invite['completing']);
        });
    }

    // Called only after Firebase password authentication succeeds for an existing account.
    public function admitExisting(string $uid, string $email): array
    {
        return $this->change(function (&$data) use ($uid, $email) {
            if (is_array($data['users'][$uid] ?? null)) {
                return $data['users'][$uid];
            }
            $invite = &$data['student_invitations'][self::key($email)];
            $this->requirePending($invite, $data);
            if (($invite['candidate_uid'] ?? null) === $uid) {
                throw new AccountException('Complete New User activation before signing in.', 'email');
            }
            $profile = $this->profile($uid, $invite);
            $data['users'][$uid] = $profile;
            $invite['status'] = 'active';
            $invite['uid'] = $uid;
            unset($invite['token_hash'], $invite['expires_at']);

            return $profile;
        });
    }

    private function profile(string $uid, array $invite): array
    {
        return ['uid' => $uid, 'name' => $invite['fullname'], 'fullname' => $invite['fullname'],
            'email' => $invite['email'], 'role' => 'student', 'must_change_password' => false,
            'created_at' => now()->toIso8601String(), 'created_by' => $invite['created_by'],
            'block_application' => ['id' => (string) Str::uuid(), 'block_id' => $invite['block_id'], 'requested_at' => now()->toIso8601String()]];
    }

    private function assess(array $row, array $data): array
    {
        $row['status'] = 'error';
        unset($row['block_id']);
        if (! empty($row['error'])) {
            $row['message'] = $row['error'];

            return $row;
        }
        $matches = array_keys(array_filter($data['blocks'] ?? [], fn ($block) => mb_strtolower(trim($block['name'] ?? '')) === mb_strtolower($row['block'])));
        if (count($matches) !== 1) {
            $row['message'] = 'Block not found or ambiguous. Create/correct the block first.';

            return $row;
        }
        $row['block_id'] = $matches[0];
        foreach ($data['users'] ?? [] as $profile) {
            if (strtolower($profile['email'] ?? '') !== $row['email'] || ! empty($profile['activation_required'])) {
                continue;
            }
            $row['status'] = ($profile['role'] ?? '') === 'student' ? 'skipped' : 'error';
            $row['message'] = $row['status'] === 'skipped' ? 'Existing student account retained; manage block changes in Manage Classes.' : 'Email belongs to a teacher or administrator.';

            return $row;
        }
        if (isset($data['student_invitations'][self::key($row['email'])])) {
            $row['status'] = 'skipped';
            $row['message'] = 'Already imported. Remove the pending invitation first to correct its details.';

            return $row;
        }
        $row['status'] = 'ready';
        $row['message'] = 'New invitation; teacher approval required for the block.';

        return $row;
    }

    private function requirePending(?array $invite, array $data): void
    {
        if (($invite['status'] ?? '') !== 'pending' || ! isset($data['blocks'][$invite['block_id']])) {
            $this->invalid();
        }
    }

    private function requireProof(?array $invite, array $data, string $token): void
    {
        $this->requirePending($invite, $data);
        if (empty($invite['activation_uid']) || ($invite['expires_at'] ?? 0) <= now()->timestamp || ! hash_equals($invite['token_hash'] ?? '', hash('sha256', $token))) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw new AccountException('Activation is unavailable or expired. Request a new link through New User, or contact your administrator.', 'email');
    }

    private function read(): array
    {
        try {
            return $this->database->getReference()->getValue() ?? [];
        } catch (Throwable $exception) {
            $this->unavailable($exception);
        }
    }

    private function change(Closure $transition): mixed
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $root = $this->database->getReference();

                return $this->database->runTransaction(function (Transaction $transaction) use ($root, $transition) {
                    $data = $transaction->snapshot($root)->getValue() ?? [];
                    $result = $transition($data);
                    $transaction->set($root, $data);

                    return $result;
                });
            } catch (AccountException|HttpExceptionInterface $exception) {
                throw $exception;
            } catch (TransactionFailed $exception) {
                if ($attempt === 2) {
                    $this->unavailable($exception);
                }
            } catch (Throwable $exception) {
                $this->unavailable($exception);
            }
        }
    }

    private function unavailable(Throwable $exception): never
    {
        Log::warning('Student enrollment operation failed.', ['exception_type' => $exception::class]);
        throw new AccountException('Student enrollment is temporarily unavailable. Please try again shortly.', status: 503);
    }
}
