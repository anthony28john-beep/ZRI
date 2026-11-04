<?php

namespace Tests\Unit;

use App\Exceptions\ScoreboardUnavailable;
use App\Services\FirestoreCredentials;
use App\Services\FirestoreLeaderboards;
use App\Services\FullNames;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Mockery;
use Tests\TestCase;

class FirestoreLeaderboardsTest extends TestCase
{
    private string $resource = 'projects/test-project/databases/(default)/documents';

    private string $url = 'https://firestore.googleapis.com/v1/projects/test-project/databases/%28default%29/documents';

    private FirestoreLeaderboards $boards;

    private $database;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['firebase.firestore_database_id' => '(default)']);
        $credentials = Mockery::mock(FirestoreCredentials::class);
        $credentials->shouldReceive('connection')->once()->andReturn(['project_id' => 'test-project', 'access_token' => 'test-token']);
        $this->database = Mockery::mock(Database::class);
        $this->boards = new FirestoreLeaderboards($credentials, $this->database);
    }

    private function scoreDocument(string $uid, string $level, mixed $score): array
    {
        return ['document' => ['name' => $this->resource.'/players/'.$uid.'/levels/'.$level, 'fields' => ['highestScore' => ['integerValue' => $score]]]];
    }

    private function player(string $uid, string $name): array
    {
        return ['found' => ['name' => $this->resource.'/players/'.$uid, 'fields' => ['fullname' => ['stringValue' => $name], 'username' => ['stringValue' => 'DoNotShowUsername'], 'displayName' => ['stringValue' => 'AlsoNotAFullName']]]];
    }

    public function test_each_level_is_sorted_by_highest_score_with_only_public_fields(): void
    {
        Http::fake([
            $this->url.':runQuery' => Http::response([
                $this->scoreDocument('alice-uid', 'level1', '10'), $this->scoreDocument('bob-uid', 'level1', '27'),
                $this->scoreDocument('alice-uid', 'tutorial', '5'), $this->scoreDocument('bob-uid', 'level2', '0'),
                $this->scoreDocument('ignored-uid', 'level1', 'invalid'),
                ['document' => ['name' => $this->resource.'/unrelated/x/levels/level1', 'fields' => ['highestScore' => ['integerValue' => '99']]]],
            ]),
            $this->url.':batchGet' => Http::response([$this->player('alice-uid', 'Alice'), $this->player('bob-uid', 'Bob')]),
        ]);
        $boards = $this->boards->levels();
        $this->assertSame(['Tutorial', 'Level 1', 'Level 2'], array_column($boards, 'name'));
        $this->assertSame([['name' => 'Bob', 'highest_score' => 27], ['name' => 'Alice', 'highest_score' => 10]], $boards[1]['players']);
        $this->assertSame(0, $boards[2]['players'][0]['highest_score']);
        $this->assertStringNotContainsString('alice-uid', json_encode($boards));
        $this->assertStringNotContainsString('ignored', json_encode($boards));
        $this->assertStringNotContainsString('DoNotShowUsername', json_encode($boards));
        $this->database->shouldNotHaveReceived('getReference');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), ':batchGet') && $request['mask']['fieldPaths'] === FullNames::FIELDS);
    }

    public function test_missing_full_names_use_website_full_names_and_never_usernames_emails_or_ids(): void
    {
        Http::fake([
            $this->url.':runQuery' => Http::response([$this->scoreDocument('one', 'level1', '27'), $this->scoreDocument('two', 'level1', '5')]),
            $this->url.':batchGet' => Http::response([$this->player('one', 'private@example.com'), $this->player('two', '')]),
        ]);
        $one = Mockery::mock(Reference::class);
        $two = Mockery::mock(Reference::class);
        $this->database->shouldReceive('getReference')->once()->with('users/one')->andReturn($one);
        $this->database->shouldReceive('getReference')->once()->with('users/two')->andReturn($two);
        $one->shouldReceive('getValue')->once()->andReturn(['fullname' => 'Game Player', 'username' => 'gamer123']);
        $two->shouldReceive('getValue')->once()->andReturn(['name' => 'gamer456', 'username' => 'gamer456']);
        $players = $this->boards->levels()[1]['players'];
        $this->assertSame([['name' => 'Game Player', 'highest_score' => 27], ['name' => 'Player', 'highest_score' => 5]], $players);
        $this->assertStringNotContainsString('@', json_encode($players));
    }

    public function test_empty_results_keep_empty_boards_for_known_levels(): void
    {
        Http::fake([$this->url.':runQuery' => Http::response([['readTime' => '2026-10-19T00:00:00Z']])]);
        $this->assertSame([['name' => 'Tutorial', 'players' => []], ['name' => 'Level 1', 'players' => []]], $this->boards->levels());
        Http::assertSentCount(1);
    }

    public function test_query_errors_do_not_return_fake_or_partial_rankings(): void
    {
        Http::fake([$this->url.':runQuery' => Http::response(['error' => ['message' => 'Private token test-token']], 403)]);
        try {
            $this->boards->levels();
            $this->fail('Expected unavailable leaderboards.');
        } catch (ScoreboardUnavailable $exception) {
            $this->assertStringContainsString('temporarily unavailable', $exception->getMessage());
            $this->assertStringNotContainsString('test-token', $exception->getMessage());
        }
    }

    public function test_collection_group_results_are_paginated(): void
    {
        $first = [];
        for ($index = 0; $index < 200; $index++) {
            $first[] = ['document' => ['name' => $this->resource.'/other/'.$index.'/levels/level1', 'fields' => []]];
        }
        Http::fake([
            $this->url.':runQuery' => Http::sequence()->push($first)->push([$this->scoreDocument('one', 'level1', '27')]),
            $this->url.':batchGet' => Http::response([$this->player('one', 'Player One')]),
        ]);
        $this->assertSame('Player One', $this->boards->levels()[1]['players'][0]['name']);
        Http::assertSent(fn ($request) => isset($request['structuredQuery']['startAt']) && $request['structuredQuery']['startAt']['before'] === false);
        Http::assertSentCount(3);
    }
}
