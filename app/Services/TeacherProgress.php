<?php

namespace App\Services;

use App\Exceptions\ScoreboardUnavailable;
use Illuminate\Support\Facades\Cache;

class TeacherProgress
{
    public function __construct(private FirestoreScoreboard $scoreboard) {}

    public function students(array $roster): array
    {
        $students = [];
        foreach ($roster as $uid => $student) {
            try {
                // Membership is checked before this method. Cache results, never membership.
                $results = Cache::remember('teacher-results:'.hash('sha256', $uid), 60, fn () => $this->scoreboard->forPlayer((string) $uid));
                $students[$uid] = $student + ['results' => $results, 'unavailable' => false];
            } catch (ScoreboardUnavailable) {
                $students[$uid] = $student + ['results' => null, 'unavailable' => true];
            }
        }

        return $students;
    }

    public function levels(array $students, array $available = []): array
    {
        $levels = array_replace(['tutorial' => 'Tutorial', 'level1' => 'Level 1'], $available);
        foreach ($students as $student) {
            foreach ($student['results']['rows'] ?? [] as $row) {
                $levels[$row['id']] = $row['name'];
            }
        }

        uksort($levels, fn ($left, $right) => $left === $right ? 0 : ($left === 'tutorial' ? -1 : ($right === 'tutorial' ? 1 : strnatcasecmp($left, $right))));

        return $levels;
    }

    public function leaders(array $students, string $level): array
    {
        $leaders = [];
        foreach ($students as $student) {
            $row = collect($student['results']['rows'] ?? [])->firstWhere('id', $level);
            if (isset($row['best_score'])) {
                $leaders[] = ['name' => $student['name'], 'highest_score' => $row['best_score']];
            }
        }
        usort($leaders, fn ($a, $b) => ($b['highest_score'] <=> $a['highest_score']) ?: strcasecmp($a['name'], $b['name']));

        return $leaders;
    }
}
