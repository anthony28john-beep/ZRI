<?php

namespace Tests\Unit;

use App\Exceptions\ScoreboardUnavailable;
use App\Services\FirestoreAttempts;
use App\Services\StudentNotifications;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StudentNotificationsTest extends TestCase
{
    private function database(array $records = []): Database
    {
        $database = Mockery::mock(Database::class);
        $reference = Mockery::mock(Reference::class);
        $database->shouldReceive('getReference')->with('student_notifications/student-one')->andReturn($reference);
        $reference->shouldReceive('getValue')->andReturn($records);

        return $database;
    }

    private function attempt(string $id, int $score = 27): array
    {
        return ['id' => $id, 'level' => 'Level 1', 'score' => $score, 'completed' => true, 'finished_at' => now()->toImmutable()];
    }

    public function test_replays_with_identical_scores_are_separate_notifications_and_refresh_does_not_duplicate_them(): void
    {
        Cache::flush();
        $attempts = Mockery::mock(FirestoreAttempts::class);
        $attempts->shouldReceive('forPlayer')->once()->with('student-one')->andReturn([$this->attempt('first'), $this->attempt('replay')]);
        $notifications = new StudentNotifications($this->database(), $attempts);
        $result = $notifications->forStudent('student-one');
        $this->assertSame(2, $result['count']);
        $this->assertSame(['attempt-first', 'attempt-replay'], array_column($result['items'], 'id'));
        $this->assertSame(['You scored 27 points on this run.', 'You scored 27 points on this run.'], array_column($result['items'], 'message'));
        $this->assertSame(2, $notifications->forStudent('student-one')['count']);
    }

    public function test_new_attempts_appear_after_cache_refresh_with_their_own_run_scores(): void
    {
        Cache::flush();
        $this->freezeTime();
        $attempts = Mockery::mock(FirestoreAttempts::class);
        $attempts->shouldReceive('forPlayer')->with('student-one')->twice()->andReturn(
            [$this->attempt('first', 35)], [$this->attempt('replay', 0), $this->attempt('first', 35)]
        );
        $notifications = new StudentNotifications($this->database(), $attempts);
        $this->assertSame(1, $notifications->forStudent('student-one')['count']);
        $this->travel(31)->seconds();
        $result = $notifications->forStudent('student-one');
        $this->assertSame(2, $result['count']);
        $this->assertSame('You scored 0 points on this run.', $result['items'][0]['message']);
        $this->assertStringNotContainsString('Highest score', json_encode($result));
    }

    public function test_an_attempt_outage_preserves_block_acceptance_notifications(): void
    {
        Cache::flush();
        $attempts = Mockery::mock(FirestoreAttempts::class);
        $attempts->shouldReceive('forPlayer')->andThrow(new ScoreboardUnavailable('Unavailable'));
        $result = (new StudentNotifications($this->database([
            ['type' => 'block_accepted', 'block' => 'BSCE 2 A'],
        ]), $attempts))->forStudent('student-one');
        $this->assertSame(1, $result['count']);
        $this->assertSame('You have been accepted into BSCE 2 A.', $result['items'][0]['message']);
        $this->assertSame(['Recent game attempts are temporarily unavailable.'], $result['unavailable']);
    }

    public function test_a_block_notification_outage_preserves_attempt_notifications(): void
    {
        Cache::flush();
        $database = Mockery::mock(Database::class);
        $database->shouldReceive('getReference')->with('student_notifications/student-one')->andThrow(new RuntimeException('Unavailable'));
        $attempts = Mockery::mock(FirestoreAttempts::class);
        $attempts->shouldReceive('forPlayer')->with('student-one')->andReturn([$this->attempt('first')]);
        $result = (new StudentNotifications($database, $attempts))->forStudent('student-one');
        $this->assertSame(1, $result['count']);
        $this->assertSame(['Block updates are temporarily unavailable.'], $result['unavailable']);
    }
}
