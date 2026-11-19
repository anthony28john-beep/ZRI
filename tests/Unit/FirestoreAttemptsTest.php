<?php

namespace Tests\Unit;

use App\Exceptions\ScoreboardUnavailable;
use App\Services\FirestoreAttempts;
use App\Services\FirestoreCredentials;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FirestoreAttemptsTest extends TestCase
{
    private function reader(): FirestoreAttempts
    {
        Http::preventStrayRequests();
        $credentials = Mockery::mock(FirestoreCredentials::class);
        $credentials->shouldReceive('connection')->once()->andReturn(['project_id' => 'test-project', 'access_token' => 'test-token']);

        return new FirestoreAttempts($credentials);
    }

    private function document(string $id, array $fields = [], string $uid = 'student-one'): array
    {
        return ['document' => ['name' => 'projects/test-project/databases/(default)/documents/players/'.$uid.'/attempts/'.$id,
            'fields' => array_replace([
                'level' => ['stringValue' => 'level2'], 'score' => ['integerValue' => '14'],
                'completed' => ['booleanValue' => true], 'finishedAt' => ['timestampValue' => '2026-11-19T08:00:00Z'],
            ], $fields)]];
    }

    public function test_query_is_scoped_to_the_player_and_keeps_separate_replay_documents(): void
    {
        $reader = $this->reader();
        Http::fake(['*' => Http::response([
            $this->document('first'), $this->document('replay'),
            $this->document('other-player', [], 'student-two'), $this->document('bad-score', ['score' => ['stringValue' => 'fake']]),
            $this->document('zero', ['score' => ['integerValue' => '0'], 'completed' => ['booleanValue' => false]]),
        ])]);
        $attempts = $reader->forPlayer('student-one');
        $this->assertSame(['first', 'replay', 'zero'], array_column($attempts, 'id'));
        $this->assertSame(14, $attempts[0]['score']);
        $this->assertSame('Level 2', $attempts[0]['level']);
        $this->assertSame(0, $attempts[2]['score']);
        $this->assertFalse($attempts[2]['completed']);
        $this->assertSame('2026-11-19 16:00:00 +08:00', $attempts[0]['finished_at']->format('Y-m-d H:i:s P'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/documents/players/student-one:runQuery')
            && $request['structuredQuery']['from'] === [['collectionId' => 'attempts']]
            && $request['structuredQuery']['limit'] === 20
            && $request['structuredQuery']['orderBy'][0]['field']['fieldPath'] === 'finishedAt');
        Http::assertSentCount(1);
    }

    public function test_empty_attempt_history_does_not_generate_fake_level_notifications(): void
    {
        $reader = $this->reader();
        Http::fake(['*' => Http::response([['readTime' => '2026-11-19T08:00:00Z']])]);
        $this->assertSame([], $reader->forPlayer('student-one'));
    }

    public function test_upstream_failure_is_reported_without_private_details(): void
    {
        $reader = $this->reader();
        Http::fake(['*' => Http::response(['error' => 'private backend details'], 503)]);
        $this->expectException(ScoreboardUnavailable::class);
        $this->expectExceptionMessage('Your recent game attempts are temporarily unavailable.');
        $reader->forPlayer('student-one');
    }
}
