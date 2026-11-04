<?php

namespace Tests\Unit;

use App\Exceptions\ScoreboardUnavailable;
use App\Services\FirestoreCredentials;
use App\Services\FirestoreScoreboard;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FirestoreScoreboardTest extends TestCase
{
    private FirestoreScoreboard $scores;

    private string $base = 'https://firestore.googleapis.com/v1/projects/test-project/databases/%28default%29/documents/players/current-user';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['firebase.firestore_database_id' => '(default)']);
        $credentials = Mockery::mock(FirestoreCredentials::class);
        $credentials->shouldReceive('connection')->once()->andReturn(['project_id' => 'test-project', 'access_token' => 'test-token']);
        $this->scores = new FirestoreScoreboard($credentials);
    }

    private function level(string $id, array $fields): array
    {
        return ['name' => $this->base.'/levels/'.$id, 'fields' => $fields];
    }

    public function test_results_are_decoded_and_overall_points_are_not_confused_with_level_scores(): void
    {
        Http::fake([
            $this->base => Http::response(['fields' => [
                'points' => ['integerValue' => '37'], 'totalAttempts' => ['integerValue' => '2'],
                'email' => ['stringValue' => 'private@example.com'], 'device' => ['stringValue' => 'Private device'],
            ]]),
            $this->base.'/levels*' => Http::response(['documents' => [$this->level('level1', [
                'highestScore' => ['integerValue' => '27'], 'bestGrade' => ['stringValue' => 'A'],
                'bestTimeSec' => ['doubleValue' => 100.5], 'attempts' => ['integerValue' => '1'],
                'completed' => ['booleanValue' => true], 'lastPlayed' => ['timestampValue' => '2026-10-18T02:54:56Z'],
            ])]]),
        ]);
        $result = $this->scores->forPlayer('current-user');
        $this->assertSame(37, $result['total_points']);
        $this->assertSame(2, $result['total_attempts']);
        $this->assertSame(1, $result['levels_completed']);
        $this->assertSame('Tutorial', $result['rows'][0]['name']);
        $this->assertSame('Not played', $result['rows'][0]['status']);
        $level = $result['rows'][1];
        $this->assertSame(27, $level['best_score']);
        $this->assertSame('A', $level['best_grade']);
        $this->assertSame('1m 40.5s', $level['best_time']);
        $this->assertSame('Completed', $level['status']);
        $this->assertSame('2026-10-18 10:54:56 +08:00', $level['last_played']->format('Y-m-d H:i:s P'));
        $this->assertStringNotContainsString('private@example.com', json_encode($result));
        $this->assertStringNotContainsString('Private device', json_encode($result));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-token') && str_contains($request->url(), '/players/current-user/levels'));
        Http::assertSentCount(2);
    }

    public function test_a_new_player_gets_empty_results_and_not_sample_scores(): void
    {
        Http::fake([
            $this->base => Http::response(['error' => ['status' => 'NOT_FOUND']], 404),
            $this->base.'/levels*' => Http::response([]),
        ]);
        $result = $this->scores->forPlayer('current-user');
        $this->assertFalse($result['has_results']);
        $this->assertSame(0, $result['total_points']);
        $this->assertSame(0, $result['total_attempts']);
        $this->assertCount(2, $result['rows']);
        $this->assertNull($result['rows'][1]['best_score']);
        $this->assertNull($result['rows'][1]['best_time']);
    }

    public function test_existing_subcollection_results_load_even_without_a_parent_document(): void
    {
        Http::fake([
            $this->base => Http::response([], 404),
            $this->base.'/levels*' => Http::response(['documents' => [$this->level('level1', [
                'highestScore' => ['integerValue' => '0'], 'attempts' => ['integerValue' => '2'],
            ])]]),
        ]);
        $result = $this->scores->forPlayer('current-user');
        $this->assertTrue($result['has_results']);
        $this->assertNull($result['total_points']);
        $this->assertSame(0, $result['rows'][1]['best_score']);
        $this->assertSame(2, $result['total_attempts']);
        $this->assertSame('In progress', $result['rows'][1]['status']);
    }

    public function test_paginated_levels_are_loaded_and_sorted_naturally(): void
    {
        Http::fake([
            $this->base => Http::response(['fields' => []]),
            $this->base.'/levels*' => Http::sequence()
                ->push(['documents' => [$this->level('level10', [])], 'nextPageToken' => 'next-page'])
                ->push(['documents' => [$this->level('level2', ['completed' => ['booleanValue' => true]])]]),
        ]);
        $result = $this->scores->forPlayer('current-user');
        $this->assertSame(['Tutorial', 'Level 1', 'Level 2', 'Level 10'], array_column($result['rows'], 'name'));
        Http::assertSent(fn ($request) => ($request['pageToken'] ?? null) === 'next-page');
        Http::assertSentCount(3);
    }

    public function test_firestore_permission_errors_are_not_reported_as_empty_results(): void
    {
        Http::fake([$this->base => Http::response(['error' => ['message' => 'private internal details']], 403)]);
        try {
            $this->scores->forPlayer('current-user');
            $this->fail('Expected unavailable results.');
        } catch (ScoreboardUnavailable $exception) {
            $this->assertStringContainsString('temporarily unavailable', $exception->getMessage());
            $this->assertStringNotContainsString('private internal details', $exception->getMessage());
        }
    }

    public function test_invalid_times_and_dates_render_as_missing_values(): void
    {
        Http::fake([
            $this->base => Http::response(['fields' => []]),
            $this->base.'/levels*' => Http::response(['documents' => [$this->level('level1', [
                'bestTimeSec' => ['doubleValue' => -5], 'lastPlayed' => ['timestampValue' => 'not-a-date'],
            ])]]),
        ]);
        $level = $this->scores->forPlayer('current-user')['rows'][1];
        $this->assertNull($level['best_time']);
        $this->assertNull($level['last_played']);
    }
}
