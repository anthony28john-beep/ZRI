<?php

namespace Tests\Unit;

use App\Exceptions\ScoreboardUnavailable;
use App\Services\FirestoreCredentials;
use App\Services\FirestoreLevels;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FirestoreLevelsTest extends TestCase
{
    private string $resource = 'projects/test-project/databases/(default)/documents';

    private string $url = 'https://firestore.googleapis.com/v1/projects/test-project/databases/%28default%29/documents:runQuery';

    private FirestoreLevels $levels;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['firebase.firestore_database_id' => '(default)']);
        $credentials = Mockery::mock(FirestoreCredentials::class);
        $credentials->shouldReceive('connection')->andReturn(['project_id' => 'test-project', 'access_token' => 'private-test-token']);
        $this->levels = new FirestoreLevels($credentials);
    }

    private function document(string $player, string $level): array
    {
        return ['document' => ['name' => $this->resource.'/players/'.$player.'/levels/'.$level]];
    }

    public function test_discovers_levels_without_scores_and_ignores_unrelated_collections(): void
    {
        Http::fake([$this->url => Http::response([
            $this->document('student-outside-class', 'level2'),
            $this->document('another-student', 'level2'),
            $this->document('student-outside-class', 'level10'),
            ['document' => ['name' => $this->resource.'/unrelated/player/levels/secret']],
            ['document' => ['name' => $this->resource.'/players/player/levels/level2/nested/secret']],
        ])]);
        $this->assertSame(['tutorial' => 'Tutorial', 'level1' => 'Level 1', 'level2' => 'Level 2', 'level10' => 'Level 10'], $this->levels->available());
        Http::assertSent(fn ($request) => $request['structuredQuery']['select']['fields'] === [['fieldPath' => '__name__']]);
    }

    public function test_cached_catalog_refreshes_after_one_minute_to_pick_up_new_levels(): void
    {
        Http::fake([$this->url => Http::sequence()
            ->push([$this->document('student', 'level2')])
            ->push([$this->document('student', 'level2'), $this->document('student', 'level3')])]);
        $this->assertArrayHasKey('level2', $this->levels->available());
        $this->assertArrayNotHasKey('level3', $this->levels->available());
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->assertArrayHasKey('level3', $this->levels->available());
        Http::assertSentCount(2);
    }

    public function test_discovery_reads_later_pages_with_an_exclusive_cursor(): void
    {
        $first = [];
        for ($i = 0; $i < 200; $i++) {
            $first[] = $this->document(sprintf('player-%03d', $i), 'level1');
        }
        Http::fake([$this->url => Http::sequence()->push($first)->push([$this->document('player-200', 'level2')])]);
        $this->assertArrayHasKey('level2', $this->levels->available());
        Http::assertSent(fn ($request) => ($request['structuredQuery']['startAt'] ?? null) === ['values' => [['referenceValue' => $this->resource.'/players/player-199/levels/level1']], 'before' => false]);
        Http::assertSentCount(2);
    }

    public function test_failure_is_safe_and_not_cached_as_a_successful_empty_catalog(): void
    {
        Http::fake([$this->url => Http::sequence()->push(['error' => ['message' => 'private-test-token']], 403)->push([$this->document('student', 'level2')])]);
        try {
            $this->levels->available();
            $this->fail('Expected unavailable levels.');
        } catch (ScoreboardUnavailable $exception) {
            $this->assertStringNotContainsString('private-test-token', $exception->getMessage());
        }
        $this->assertArrayHasKey('level2', $this->levels->available());
    }
}
