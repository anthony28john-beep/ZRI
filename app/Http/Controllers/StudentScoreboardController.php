<?php

namespace App\Http\Controllers;

use App\Exceptions\ScoreboardUnavailable;
use App\Services\FirestoreLeaderboards;
use App\Services\FirestoreScoreboard;
use Illuminate\Http\Request;

class StudentScoreboardController extends Controller
{
    public function show(Request $request, FirestoreScoreboard $scores)
    {
        $scoreboard = null;
        $unavailable = false;
        $leaderboards = [];
        $leaderboardsUnavailable = false;
        try {
            $scoreboard = $scores->forPlayer($request->user()->getAuthIdentifier());
        } catch (ScoreboardUnavailable) {
            $unavailable = true;
        }
        try {
            $leaderboards = app(FirestoreLeaderboards::class)->levels();
        } catch (ScoreboardUnavailable) {
            $leaderboardsUnavailable = true;
        }

        $response = response()->view('student.scoreboard', compact('scoreboard', 'unavailable', 'leaderboards', 'leaderboardsUnavailable'), $unavailable ? 503 : 200);

        return $unavailable ? $response->header('Retry-After', '30') : $response;
    }
}
