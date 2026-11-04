<?php

namespace App\Services;

use App\Exceptions\ClassroomUnavailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Contract\Database;
use Throwable;

class Classrooms
{
    public function __construct(private Database $database, private FullNames $names) {}

    private function read(string $path): array
    {
        try {
            $value = $this->database->getReference($path)->getValue();

            return is_array($value) ? $value : [];
        } catch (Throwable $exception) {
            Log::warning('Classroom read failed.', ['exception_type' => $exception::class]);
            throw new ClassroomUnavailable('Classes are temporarily unavailable. Please try again shortly.');
        }
    }

    public function administration(): array
    {
        $users = [];
        foreach ($this->read('users') as $uid => $profile) {
            if (is_array($profile) && in_array($profile['role'] ?? null, ['teacher', 'student'], true)) {
                $users[$uid] = ['uid' => (string) $uid, 'name' => $this->names->forProfile((string) $uid, $profile, 'Unnamed account'), 'role' => $profile['role'], 'block_id' => $profile['block_id'] ?? null, 'pending_block_id' => $profile['block_application']['block_id'] ?? null];
            }
        }
        uasort($users, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['classes' => $this->read('classes'), 'users' => $users, 'blocks' => $this->blocks()];
    }

    public function blocks(): array
    {
        $blocks = [];
        foreach ($this->read('blocks') as $id => $block) {
            if (Str::isUuid((string) $id) && is_array($block) && is_string($block['name'] ?? null)) {
                $blocks[$id] = $block['name'];
            }
        }
        natcasesort($blocks);

        return $blocks;
    }

    public function createBlock(string $name): void
    {
        $name = trim($name);
        foreach ($this->blocks() as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($name)) {
                throw ValidationException::withMessages(['block_name' => 'This block already exists.']);
            }
        }
        $this->write(['blocks/'.Str::uuid() => ['name' => $name]], 'The block could not be saved. Please try again.');
    }

    public function assignBlock(string $blockId, array $students): void
    {
        $this->requireBlock($blockId);
        $updates = [];
        foreach ($students as $uid) {
            if (! $this->validKey($uid) || ($this->read('users/'.$uid)['role'] ?? null) !== 'student') {
                throw ValidationException::withMessages(['students' => 'Choose existing student accounts only.']);
            }
            $updates['users/'.$uid.'/block_id'] = $blockId;
            $updates['users/'.$uid.'/block_application'] = null;
        }
        if ($updates) {
            $this->write($updates, 'Student blocks could not be saved. Please try again.');
        }
    }

    public function blockDeletionDetails(string $id): array
    {
        $blocks = $this->blocks();
        abort_unless(isset($blocks[$id]), 404);
        $profiles = $this->read('users');
        $students = count(array_filter($profiles, fn ($profile) => is_array($profile) && ($profile['role'] ?? null) === 'student' && ($profile['block_id'] ?? null) === $id));
        $classes = count(array_filter($this->read('classes'), fn ($class) => is_array($class) && ($class['block_id'] ?? null) === $id));

        $applications = count(array_filter($profiles, fn ($profile) => is_array($profile) && ($profile['role'] ?? null) === 'student' && ($profile['block_application']['block_id'] ?? null) === $id));

        return ['id' => $id, 'name' => $blocks[$id], 'students' => $students, 'classes' => $classes, 'applications' => $applications];
    }

    public function deleteBlock(string $id): void
    {
        // Recheck usage on submission, not just when the confirmation page was opened.
        $block = $this->blockDeletionDetails($id);
        if ($block['students'] > 0 || $block['classes'] > 0 || $block['applications'] > 0) {
            throw ValidationException::withMessages(['block' => 'This block is still in use. Move its students, resolve pending applications, and update class assignments before deleting it.']);
        }
        $this->write(['blocks/'.$id => null], 'The block could not be deleted. Please try again.');
    }

    private function requireBlock(string $id): string
    {
        $blocks = $this->blocks();
        if (! isset($blocks[$id])) {
            throw ValidationException::withMessages(['block_id' => 'Choose an existing block.']);
        }

        return $blocks[$id];
    }

    public function forTeacher(string $uid): array
    {
        $classes = [];
        $blockProfiles = null;
        foreach ($this->read('teacher_classes/'.$uid) as $id => $assigned) {
            if ($assigned !== true || ! Str::isUuid((string) $id)) {
                continue;
            }
            $class = $this->read('classes/'.$id);
            // Recheck ownership even when an index exists or is stale.
            if (($class['teacher_uid'] ?? null) !== $uid) {
                continue;
            }
            $students = [];
            if (isset($class['block_id'])) {
                if (! is_string($class['block_id']) || ! Str::isUuid($class['block_id'])) {
                    continue;
                }
                // The server reads profiles; only members of this owned class reach the teacher view.
                $blockProfiles ??= $this->read('users');
                foreach ($blockProfiles as $studentUid => $profile) {
                    if (is_array($profile) && ($profile['role'] ?? null) === 'student' && ($profile['block_id'] ?? null) === $class['block_id']) {
                        $students[$studentUid] = ['uid' => (string) $studentUid, 'name' => $this->names->forProfile((string) $studentUid, $profile)];
                    }
                }
            }
            foreach (($class['students'] ?? []) as $studentUid => $member) {
                if (isset($class['block_id'])) {
                    break;
                }
                if ($member !== true || ! $this->validKey((string) $studentUid)) {
                    continue;
                }
                $profile = $this->read('users/'.$studentUid);
                if (($profile['role'] ?? null) === 'student') {
                    $students[$studentUid] = ['uid' => (string) $studentUid, 'name' => $this->names->forProfile((string) $studentUid, $profile)];
                }
            }
            uasort($students, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
            $classes[$id] = ['id' => (string) $id, 'name' => $class['name'] ?? 'Class', 'students' => $students];
            if (isset($class['block_id'])) {
                $classes[$id]['block_id'] = $class['block_id'];
            }
        }
        uasort($classes, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $classes;
    }

    public function save(array $data, string $administrator, ?string $id = null): void
    {
        $existing = $id ? $this->read('classes/'.$id) : [];
        abort_if($id !== null && ! $existing, 404);
        $teacher = $data['teacher_uid'];
        if (! $this->validKey($teacher) || ($this->read('users/'.$teacher)['role'] ?? null) !== 'teacher') {
            throw ValidationException::withMessages(['teacher_uid' => 'Choose an existing teacher account.']);
        }
        $students = [];
        $blockId = $data['block_id'] ?? null;
        $name = $blockId ? $this->requireBlock($blockId) : $data['name'];
        foreach ($blockId ? [] : ($data['students'] ?? []) as $uid) {
            if (! $this->validKey($uid) || ($this->read('users/'.$uid)['role'] ?? null) !== 'student') {
                throw ValidationException::withMessages(['students' => 'Choose existing student accounts only.']);
            }
            $students[$uid] = true;
        }
        $id ??= (string) Str::uuid();
        $updates = [
            'classes/'.$id => [
                'name' => $name, 'teacher_uid' => $teacher, 'students' => $students ?: null,
                'created_by' => $existing['created_by'] ?? $administrator,
                'created_at' => $existing['created_at'] ?? now()->toIso8601String(),
            ],
            'teacher_classes/'.$teacher.'/'.$id => true,
        ];
        if ($blockId) {
            $updates['classes/'.$id]['block_id'] = $blockId;
        }
        $previous = $existing['teacher_uid'] ?? null;
        if ($previous && $previous !== $teacher && $this->validKey($previous)) {
            $updates['teacher_classes/'.$previous.'/'.$id] = null;
        }
        // One atomic update keeps ownership and the teacher index in agreement.
        $this->write($updates, 'The class could not be saved. Please try again shortly.');
    }

    private function write(array $updates, string $message): void
    {
        try {
            $this->database->getReference()->update($updates);
        } catch (Throwable $exception) {
            Log::warning('Classroom save failed.', ['exception_type' => $exception::class]);
            throw new ClassroomUnavailable($message);
        }
    }

    private function validKey(string $value): bool
    {
        return $value !== '' && strlen($value) <= 128 && preg_match('/[.\x00-\x20#$\[\]\/\x7f]/', $value) === 0;
    }
}
