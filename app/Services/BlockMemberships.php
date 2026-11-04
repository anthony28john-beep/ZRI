<?php

namespace App\Services;

use App\Exceptions\ClassroomUnavailable;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Transaction;
use Kreait\Firebase\Exception\Database\TransactionFailed;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BlockMemberships
{
    public function __construct(private Database $database, private FullNames $names) {}

    public function forStudent(string $uid): array
    {
        $data = $this->read();
        $profile = $this->student($data, $uid);
        $currentId = $profile['block_id'] ?? null;
        $current = $currentId ? ['id' => $currentId, 'name' => $data['blocks'][$currentId]['name'] ?? 'Your current block'] : null;
        if (! $current && $this->hasMembership($data, $uid)) {
            $current = ['id' => null, 'name' => 'Individual class assignments'];
        }
        $pending = $profile['block_application'] ?? null;
        if (is_array($pending)) {
            $pending['name'] = $data['blocks'][$pending['block_id'] ?? '']['name'] ?? 'Block no longer available';
        } else {
            $pending = null;
        }
        $available = [];
        foreach ($data['blocks'] ?? [] as $id => $block) {
            if (is_array($block) && is_string($block['name'] ?? null) && $this->teachers($data, (string) $id)) {
                $available[$id] = $block['name'];
            }
        }
        natcasesort($available);

        return ['current' => $current, 'pending' => $pending, 'available' => $available];
    }

    public function apply(string $uid, string $block): void
    {
        $application = ['id' => (string) Str::uuid(), 'block_id' => $block, 'requested_at' => now()->toIso8601String()];
        $this->change(function (array $data) use ($uid, $block, $application) {
            $profile = $this->student($data, $uid);
            if ($this->hasMembership($data, $uid)) {
                $this->invalid('Leave your current block before applying to another.');
            }
            if (! empty($profile['block_application'])) {
                $this->invalid('You already have a pending application. Cancel it before applying to another block.');
            }
            if (! isset($data['blocks'][$block]) || ! $this->teachers($data, $block)) {
                $this->invalid('Choose a block with an assigned teacher.');
            }
            $data['users'][$uid]['block_application'] = $application;

            return $data;
        });
    }

    public function leave(string $uid): void
    {
        $this->change(function (array $data) use ($uid) {
            $this->student($data, $uid);
            if (! $this->hasMembership($data, $uid)) {
                $this->invalid('You are not currently in a block.');
            }
            unset($data['users'][$uid]['block_id']);
            foreach ($data['classes'] ?? [] as $id => $class) {
                if (is_array($class) && ! isset($class['block_id'])) {
                    unset($data['classes'][$id]['students'][$uid]);
                }
            }

            return $data;
        });
    }

    public function cancel(string $uid): void
    {
        $this->change(function (array $data) use ($uid) {
            $profile = $this->student($data, $uid);
            if (empty($profile['block_application'])) {
                $this->invalid('There is no pending application to cancel.');
            }
            unset($data['users'][$uid]['block_application']);

            return $data;
        });
    }

    public function removeStudent(string $teacher, string $classroom, string $student): void
    {
        $this->change(function (array $data) use ($teacher, $classroom, $student) {
            abort_unless(($data['users'][$teacher]['role'] ?? null) === 'teacher', 403);
            $class = $data['classes'][$classroom] ?? null;
            abort_unless(is_array($class) && ($class['teacher_uid'] ?? null) === $teacher, 403);
            $profile = $this->student($data, $student);
            if (isset($class['block_id'])) {
                // A stale roster must never remove a student from a different block.
                abort_unless(is_string($class['block_id']) && ($profile['block_id'] ?? null) === $class['block_id'], 404);
                unset($data['users'][$student]['block_id']);
            } else {
                // Legacy individual assignments belong only to this specific class.
                abort_unless(($class['students'][$student] ?? false) === true, 404);
                unset($data['classes'][$classroom]['students'][$student]);
            }

            return $data;
        });
    }

    public function forTeacher(string $uid): array
    {
        $data = $this->read();
        abort_unless(($data['users'][$uid]['role'] ?? null) === 'teacher', 403);
        $applications = [];
        foreach ($data['users'] ?? [] as $studentUid => $profile) {
            $application = is_array($profile) ? ($profile['block_application'] ?? null) : null;
            if (($profile['role'] ?? null) !== 'student' || ! is_array($application) || ! is_string($application['id'] ?? null) || ! is_string($application['block_id'] ?? null)) {
                continue;
            }
            $block = $application['block_id'];
            if (! isset($data['blocks'][$block]) || ! in_array($uid, $this->teachers($data, $block), true)) {
                continue;
            }
            $applications[] = [
                'student_uid' => (string) $studentUid, 'name' => $this->names->forProfile((string) $studentUid, $profile),
                'id' => $application['id'], 'block' => $data['blocks'][$block]['name'] ?? 'Block',
                'requested_at' => $application['requested_at'] ?? '', 'can_accept' => ! $this->hasMembership($data, (string) $studentUid),
            ];
        }
        usort($applications, fn ($a, $b) => strcmp($a['requested_at'], $b['requested_at']));

        return $applications;
    }

    public function review(string $teacher, string $student, string $applicationId, bool $accept): void
    {
        $this->change(function (array $data) use ($teacher, $student, $applicationId, $accept) {
            abort_unless(($data['users'][$teacher]['role'] ?? null) === 'teacher', 403);
            $profile = $this->student($data, $student);
            $application = $profile['block_application'] ?? null;
            abort_unless(is_array($application) && ($application['id'] ?? null) === $applicationId, 404);
            $block = $application['block_id'] ?? '';
            abort_unless(in_array($teacher, $this->teachers($data, $block), true), 403);
            if ($accept) {
                if ($this->hasMembership($data, $student)) {
                    $this->invalid('This student already belongs to a block. They must leave it first.');
                }
                if (! isset($data['blocks'][$block])) {
                    $this->invalid('This block is no longer available.');
                }
                $data['users'][$student]['block_id'] = $block;
            }
            unset($data['users'][$student]['block_application']);

            return $data;
        });
    }

    private function teachers(array $data, string $block): array
    {
        $teachers = [];
        foreach ($data['classes'] ?? [] as $class) {
            $teacher = is_array($class) ? ($class['teacher_uid'] ?? null) : null;
            if (($class['block_id'] ?? null) === $block && is_string($teacher) && ($data['users'][$teacher]['role'] ?? null) === 'teacher') {
                $teachers[] = $teacher;
            }
        }

        return array_unique($teachers);
    }

    private function hasMembership(array $data, string $uid): bool
    {
        if (! empty($data['users'][$uid]['block_id'])) {
            return true;
        }
        foreach ($data['classes'] ?? [] as $class) {
            if (is_array($class) && ! isset($class['block_id']) && ($class['students'][$uid] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function student(array $data, string $uid): array
    {
        $profile = $data['users'][$uid] ?? [];
        abort_unless(is_array($profile) && ($profile['role'] ?? null) === 'student', 403);

        return $profile;
    }

    private function read(): array
    {
        try {
            $data = $this->database->getReference()->getValue();

            return is_array($data) ? $data : [];
        } catch (Throwable $exception) {
            $this->unavailable($exception);
        }
    }

    private function change(Closure $transition): void
    {
        // Root ETag protects membership, pending request, and live class ownership together.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $root = $this->database->getReference();
                $this->database->runTransaction(function (Transaction $transaction) use ($root, $transition) {
                    $data = $transaction->snapshot($root)->getValue();
                    $transaction->set($root, $transition(is_array($data) ? $data : []));
                });

                return;
            } catch (ValidationException|HttpExceptionInterface $exception) {
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

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['block' => $message]);
    }

    private function unavailable(Throwable $exception): never
    {
        Log::warning('Block membership operation failed.', ['exception_type' => $exception::class]);
        throw new ClassroomUnavailable('Block information could not be updated or loaded. Please try again shortly.');
    }
}
