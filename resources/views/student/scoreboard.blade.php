@extends('layouts.app')

@section('title', 'Scoreboard | ZeroRiskInitiative')

@section('content')
<div class="student-dashboard scoreboard-page">
    @include('student.partials.navbar')

    <main class="student-content" aria-label="Scoreboard">
        <header class="scoreboard-heading">
            <div>
                <div class="brand-badge">SCOREBOARD</div>
                <h1>My Results</h1>
                <p>Your personal scores and progress from the game.</p>
            </div>
            <a href="{{ route('student.play-game') }}" class="button scoreboard-play">Play Again <span aria-hidden="true">&rarr;</span></a>
        </header>

        @if ($unavailable)
            <section class="scoreboard-unavailable" role="alert">
                <h2>Results temporarily unavailable</h2>
                <p>We couldn't load your game results right now. Please try again shortly.</p>
                <a href="{{ route('student.scoreboard') }}" class="scoreboard-refresh">Try Again &rarr;</a>
            </section>
        @else
            <section class="scoreboard-summary" aria-label="Your overall progress">
                <div class="scoreboard-stat">
                    <span class="scoreboard-stat-label">Total Points</span>
                    <strong>{{ $scoreboard['total_points'] ?? '—' }}</strong>
                    <span class="scoreboard-stat-note">Your overall game points</span>
                </div>
                <div class="scoreboard-stat">
                    <span class="scoreboard-stat-label">Levels Completed</span>
                    <strong>{{ $scoreboard['levels_completed'] }}</strong>
                    <span class="scoreboard-stat-note">Completed levels, including the tutorial</span>
                </div>
                <div class="scoreboard-stat">
                    <span class="scoreboard-stat-label">Total Attempts</span>
                    <strong>{{ $scoreboard['total_attempts'] }}</strong>
                    <span class="scoreboard-stat-note">Attempts across your levels</span>
                </div>
            </section>

            <section class="scoreboard-results" aria-labelledby="level-results-heading">
                <div class="scoreboard-results-heading">
                    <h2 id="level-results-heading">Level Results</h2>
                    <a href="{{ route('student.scoreboard') }}" class="scoreboard-refresh">Refresh Results &rarr;</a>
                </div>
                @if (!$scoreboard['has_results'])
                    <p class="scoreboard-empty" role="status">Play your first level to see your results.</p>
                @endif
                <div class="scoreboard-table-scroll" tabindex="0" role="region" aria-label="Level results table">
                    <table class="scoreboard-table">
                        <thead>
                            <tr>
                                <th scope="col">Level</th>
                                <th scope="col">Best Score</th>
                                <th scope="col">Best Grade</th>
                                <th scope="col">Best Time</th>
                                <th scope="col">Attempts</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scoreboard['rows'] as $level)
                                <tr>
                                    <th scope="row">
                                        <span class="scoreboard-level-name">{{ $level['name'] }}</span>
                                        <span class="scoreboard-last-played">
                                            Last played:
                                            @if ($level['last_played'])
                                                <time datetime="{{ $level['last_played']->toIso8601String() }}">{{ $level['last_played']->format('M j, Y · g:i A') }}</time>
                                            @else
                                                &mdash;
                                            @endif
                                        </span>
                                    </th>
                                    <td class="scoreboard-score">{{ $level['best_score'] ?? '—' }}</td>
                                    <td><span class="scoreboard-grade">{{ $level['best_grade'] ?? '—' }}</span></td>
                                    <td>{{ $level['best_time'] ?? '—' }}</td>
                                    <td>{{ $level['attempts'] ?? '—' }}</td>
                                    <td><span class="scoreboard-status {{ $level['completed'] ? 'is-completed' : ($level['status'] === 'In progress' ? 'is-progress' : '') }}">{{ $level['status'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <footer class="scoreboard-footnote">
                    <p>Best score, grade, and time may come from different attempts.</p>
                    <p>Dates shown in Philippine Time (UTC+8).</p>
                </footer>
            </section>
        @endif
        <section class="scoreboard-leaderboards" aria-labelledby="leaderboards-heading">
            <div class="scoreboard-results-heading">
                <h2 id="leaderboards-heading">Leaderboards by Level</h2>
                <a href="{{ route('student.scoreboard') }}" class="scoreboard-refresh">Refresh &rarr;</a>
            </div>
            @if ($leaderboardsUnavailable)
                <p class="scoreboard-empty" role="status">Leaderboards are temporarily unavailable. Please try again shortly.</p>
            @else
                <div class="scoreboard-leaderboard-grid">
                    @foreach ($leaderboards as $board)
                        <section class="scoreboard-results scoreboard-leaderboard" aria-labelledby="leaderboard-level-{{ $loop->index }}">
                            <div class="scoreboard-results-heading">
                                <h3 id="leaderboard-level-{{ $loop->index }}">{{ $board['name'] }}</h3>
                            </div>
                            @if ($board['players'])
                                <table class="scoreboard-table">
                                    <thead><tr><th scope="col">Player Name</th><th scope="col">Highest Score</th></tr></thead>
                                    <tbody>
                                        @foreach ($board['players'] as $player)
                                            <tr><th scope="row">{{ $player['name'] }}</th><td class="scoreboard-score">{{ $player['highest_score'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="scoreboard-empty">No scores saved for this level yet.</p>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
</div>
@endsection
