<section class="tp-global-leaderboards" aria-labelledby="all-students-heading">
    <div class="tp-section-heading">
        <div>
            <span class="tp-eyebrow">ALL STUDENTS</span>
            <h2 id="all-students-heading">Leaderboards by Level</h2>
            <p class="tp-caption">Highest scores across all blocks. Your selected class does not restrict this section.</p>
        </div>
        <a class="tp-link" href="{{ route('teacher.leaderboard', request()->only('class', 'level', 'global_level')) }}#all-students-heading">Refresh &rarr;</a>
    </div>

    @if ($leaderboardsUnavailable)
        <div class="tp-notice" role="status">All-student leaderboards are temporarily unavailable. Please try again shortly.</div>
    @else
        <form method="GET" action="{{ route('teacher.leaderboard') }}#all-students-heading" class="tp-toolbar">
            @if ($selectedClass)<input type="hidden" name="class" value="{{ $selectedClass['id'] }}">@endif
            <input type="hidden" name="level" value="{{ $level }}">
            <div><label for="global-level">All-student leaderboard level</label><select id="global-level" name="global_level"><option value="">All levels</option>@foreach ($globalLevels as $name)<option value="{{ $name }}" @selected($globalLevel === $name)>{{ $name }}</option>@endforeach</select></div>
            <button class="button" type="submit">Update all-student leaderboard</button>
        </form>
        @forelse ($leaderboards as $board)
            <section class="tp-panel tp-leaderboard" aria-labelledby="all-level-{{ $loop->index }}">
                <div class="tp-section-heading">
                    <h3 id="all-level-{{ $loop->index }}">{{ $board['name'] }}</h3>
                    <span class="tp-tag">All blocks</span>
                </div>
                <div class="tp-table-wrap" tabindex="0" role="region" aria-label="{{ $board['name'] }} all-student leaderboard">
                    <table class="tp-table">
                        <thead><tr><th scope="col">Player name</th><th scope="col">Highest score</th></tr></thead>
                        <tbody>
                            @forelse ($board['players'] as $player)
                                <tr><td>{{ $player['name'] }}</td><td class="tp-score">{{ $player['highest_score'] }}</td></tr>
                            @empty
                                <tr><td colspan="2">No scores saved for this level yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <p class="tp-panel tp-empty">No scores saved yet.</p>
        @endforelse
    @endif
</section>
