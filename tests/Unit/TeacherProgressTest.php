<?php

namespace Tests\Unit;

use App\Services\FirestoreScoreboard;
use App\Services\TeacherProgress;
use Mockery;
use Tests\TestCase;

class TeacherProgressTest extends TestCase
{
    public function test_available_levels_are_merged_with_roster_levels_and_sorted_naturally(): void
    {
        $service = new TeacherProgress(Mockery::mock(FirestoreScoreboard::class));
        $students = [['results' => ['rows' => [['id' => 'level3', 'name' => 'Level 3']]]]];
        $this->assertSame(['tutorial' => 'Tutorial', 'level1' => 'Level 1', 'level2' => 'Level 2', 'level3' => 'Level 3', 'level10' => 'Level 10'],
            $service->levels($students, ['level10' => 'Level 10', 'level2' => 'Level 2']));
    }

    public function test_class_rankings_include_zero_scores_and_ties_without_exposing_player_ids(): void
    {
        $service = new TeacherProgress(Mockery::mock(FirestoreScoreboard::class));
        $students = [
            ['uid' => 'z', 'name' => 'Zoe', 'results' => ['rows' => [['id' => 'level1', 'name' => 'Level 1', 'best_score' => 27]]]],
            ['uid' => 'a', 'name' => 'Ada', 'results' => ['rows' => [['id' => 'level1', 'name' => 'Level 1', 'best_score' => 27]]]],
            ['uid' => 'b', 'name' => 'Ben', 'results' => ['rows' => [['id' => 'level1', 'name' => 'Level 1', 'best_score' => 0]]]],
            ['uid' => 'c', 'name' => 'Cara', 'results' => ['rows' => [['id' => 'level2', 'name' => 'Level 2', 'best_score' => 99]]]],
            ['uid' => 'd', 'name' => 'Dan', 'results' => null, 'unavailable' => true],
        ];
        $this->assertSame([
            ['name' => 'Ada', 'highest_score' => 27], ['name' => 'Zoe', 'highest_score' => 27], ['name' => 'Ben', 'highest_score' => 0],
        ], $service->leaders($students, 'level1'));
        $this->assertSame(['tutorial' => 'Tutorial', 'level1' => 'Level 1', 'level2' => 'Level 2'], $service->levels($students));
    }
}
