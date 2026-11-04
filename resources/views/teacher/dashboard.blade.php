@extends('layouts.app')
@section('title', ucfirst($page).' | Teacher Portal')
@section('content')
@include('teacher.partials.navbar')
<main class="tp-shell {{ $page === 'home' ? 'teacher-home' : '' }}">
    @if ($page === 'home')
        @include('teacher.partials.home')
    @else
    <header class="tp-heading">
        <div><span class="tp-eyebrow">TEACHER WORKSPACE</span>
            <h1>{{ $page === 'home' ? 'Welcome, '.auth()->user()->name.'!' : ($detail ? 'Student results' : ucfirst($page)) }}</h1>
            <p>{{ ['home' => 'A clear view of your class and where to help next.', 'students' => 'Get to know your students and follow their learning journey.', 'progress' => 'See how your class is progressing, one level at a time.', 'leaderboard' => 'Celebrate your class’s highest scores.'][$page] }}</p>
        </div>
        @if ($selectedClass)<span class="tp-tag">{{ $selectedClass['name'] }}</span>@endif
    </header>
    @include('partials.messages')
    @if ($levelsUnavailable)<div class="tp-notice" role="status">Available levels could not be refreshed. Showing levels from your class results. Refresh this page to try again.</div>@endif
    @if (!$classes)
        <section class="tp-panel tp-empty"><span class="tp-step">01</span><h2>Your classroom starts here</h2><p>No classes have been assigned to you yet. Ask your administrator to create a class and assign your students.</p></section>
    @else
        <form method="GET" action="{{ route('teacher.'.$page) }}" class="tp-toolbar">
            <div><label for="class">Class</label><select id="class" name="class">@foreach ($classes as $class)<option value="{{ $class['id'] }}" @selected($selectedClass['id'] === $class['id'])>{{ $class['name'] }}</option>@endforeach</select></div>
            @if (in_array($page, ['progress', 'leaderboard']))
                <div><label for="level">Level</label><select id="level" name="level">@foreach ($levels as $id => $label)<option value="{{ $id }}" @selected($level === $id)>{{ $label }}</option>@endforeach</select></div>
            @endif
            @if ($page === 'students' && !$detail)<div class="tp-search"><label for="q">Find a student</label><input id="q" name="q" value="{{ $search }}" maxlength="100" placeholder="Search by name"></div>@endif
            <button class="button" type="submit">View results</button>
        </form>
        @if ($summary['unavailable'])<div class="tp-notice" role="status">Results for {{ $summary['unavailable'] }} student(s) are temporarily unavailable. They are excluded from progress totals and rankings. Refresh to try again.</div>@endif
        @if ($page === 'students' && $detail)
            @php($student = $students[$detail])
            <section class="tp-panel"><div class="tp-section-heading"><h2>{{ $student['name'] }}</h2><a class="tp-link" href="{{ route('teacher.students', ['class' => $selectedClass['id']]) }}">&larr; All students</a></div>
                @if ($student['unavailable'])<p class="tp-empty">Results temporarily unavailable. Please try again shortly.</p>
                @else
                    <p class="tp-caption">Total points: {{ $student['results']['total_points'] ?? '—' }} &nbsp; / &nbsp; Total attempts: {{ $student['results']['total_attempts'] }}</p>
                    <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Level</th><th>Highest score</th><th>Best grade</th><th>Best time</th><th>Attempts</th><th>Status</th><th>Last played</th></tr></thead><tbody>
                        @foreach ($student['results']['rows'] as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['best_score'] ?? '—' }}</td><td>{{ $row['best_grade'] ?? '—' }}</td><td>{{ $row['best_time'] ?? '—' }}</td><td>{{ $row['attempts'] ?? '—' }}</td><td><span class="tp-status {{ $row['completed'] ? 'tp-complete' : '' }}">{{ $row['status'] }}</span></td><td>{{ $row['last_played']?->format('M j, Y g:i A') ?? '—' }}</td></tr>@endforeach
                    </tbody></table></div><p class="tp-caption">Times shown in Asia/Manila.</p>
                @endif
            </section>
        @elseif ($page === 'students')
            <section class="tp-panel"><div class="tp-section-heading"><h2>Class roster</h2><span class="tp-tag">{{ count($students) }} students</span></div>
                <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Student name</th><th>Status</th><th>Completed levels</th><th>Details</th><th>Membership</th></tr></thead><tbody>
                    @forelse ($students as $student)<tr><td>{{ $student['name'] }}</td><td>{{ $student['unavailable'] ? 'Results unavailable' : ($student['results']['has_results'] ? 'Started playing' : 'Not started') }}</td><td>{{ $student['results']['levels_completed'] ?? '—' }}</td><td><a class="tp-link" href="{{ route('teacher.students.show', ['student' => $student['uid'], 'class' => $selectedClass['id']]) }}">View results &rarr;</a></td><td><a class="tp-link tp-remove-student" href="{{ route('teacher.students.remove', ['classroom' => $selectedClass['id'], 'student' => $student['uid']]) }}">Remove student<span class="admin-visually-hidden"> {{ $student['name'] }}</span></a></td></tr>
                    @empty<tr><td colspan="5">{{ $search ? 'No students match your search.' : 'No students assigned yet. Ask your administrator to update the class roster.' }}</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @elseif ($page === 'progress')
            <section class="tp-panel"><div class="tp-section-heading"><h2>{{ $levels[$level] }} progress</h2><span class="tp-tag">Class results</span></div>
                <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Student name</th><th>Highest score</th><th>Best grade</th><th>Attempts</th><th>Completion</th></tr></thead><tbody>
                    @forelse ($students as $student)
                        @php($row = collect($student['results']['rows'] ?? [])->firstWhere('id', $level))
                        <tr><td>{{ $student['name'] }}</td><td>{{ $row['best_score'] ?? '—' }}</td><td>{{ $row['best_grade'] ?? '—' }}</td><td>{{ $row['attempts'] ?? '—' }}</td><td><span class="tp-status {{ ($row['completed'] ?? false) ? 'tp-complete' : '' }}">{{ $student['unavailable'] ? 'Unavailable' : ($row['status'] ?? 'Not played') }}</span></td></tr>
                    @empty<tr><td colspan="5">No students assigned to this class yet.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @else
            <section class="tp-panel tp-leaderboard"><div class="tp-section-heading"><div><span class="tp-eyebrow">CLASS LEADERBOARD</span><h2>{{ $levels[$level] }}</h2></div><span class="tp-tag">Highest scores</span></div>
                <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Player name</th><th>Highest score</th></tr></thead><tbody>
                    @forelse ($leaders as $leader)<tr><td>{{ $leader['name'] }}</td><td class="tp-score">{{ $leader['highest_score'] }}</td></tr>@empty<tr><td colspan="2">No scores recorded for this level yet.</td></tr>@endforelse
                </tbody></table></div>
            </section>
        @endif
        <p class="tp-footnote">Class results include only students assigned to this class. Game results refresh at most once per minute.</p>
    @endif
    @if ($page === 'leaderboard')
        @include('teacher.partials.all-student-leaderboards')
    @endif
    @endif
</main>
@endsection
