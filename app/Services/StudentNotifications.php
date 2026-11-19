<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;
use Throwable;

class StudentNotifications
{
    public function __construct(private Database $database, private FirestoreAttempts $attempts) {}

    public function forStudent(string $uid): array
    {
        $items = [];
        $unavailable = [];
        try {
            $records = $this->database->getReference('student_notifications/'.$uid)->getValue() ?? [];
            foreach (is_array($records) ? $records : [] as $record) {
                if (($record['type'] ?? null) !== 'block_accepted' || ! is_string($record['block'] ?? null)) {
                    continue;
                }
                $items[] = ['title' => 'Block application accepted', 'message' => 'You have been accepted into '.$record['block'].'.',
                    'icon' => 'users', 'url' => route('student.account'), 'sort' => $record['created_at'] ?? ''];
            }
        } catch (Throwable $exception) {
            Log::warning('Student block notifications could not be loaded.', ['exception_type' => $exception::class]);
            $unavailable[] = 'Block updates are temporarily unavailable.';
        }
        try {
            $attempts = Cache::remember('student-notification-attempts:v1:'.hash('sha256', $uid), 30, fn () => $this->attempts->forPlayer($uid));
            foreach ($attempts as $attempt) {
                $items[] = ['id' => 'attempt-'.$attempt['id'],
                    'title' => $attempt['level'].($attempt['completed'] ? ' completed!' : ' attempt finished'),
                    'message' => 'You scored '.$attempt['score'].' points on this run.',
                    'icon' => 'trophy', 'url' => route('student.scoreboard'),
                    'recorded_at' => $attempt['finished_at']->format('M j, Y · g:i:s A').' PHT',
                    'sort' => $attempt['finished_at']->toIso8601String()];
            }
        } catch (Throwable $exception) {
            Log::warning('Student level notifications could not be loaded.', ['exception_type' => $exception::class]);
            $unavailable[] = 'Recent game attempts are temporarily unavailable.';
        }

        return ['count' => count($items), 'items' => collect($items)->sortByDesc('sort')->take(10)->values()->all(), 'unavailable' => $unavailable];
    }
}
