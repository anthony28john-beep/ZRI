@include('partials.messages')
@php($classQuery = $selectedClass ? ['class' => $selectedClass['id']] : [])

<section class="th-hero" aria-labelledby="teacher-home-heading">
    <div class="th-intro">
        <span class="th-access"><span aria-hidden="true"></span>Teacher workspace</span>
        <p class="th-greeting">Welcome back, {{ auth()->user()->name }}.</p>
        <h1 id="teacher-home-heading">Guide their learning.<br><span>Inspire safer choices.</span></h1>
        <p class="th-description">Your students, their progress, and your next steps. Bring every learner closer to better decisions through play.</p>
        <div class="th-actions">
            <a class="button" href="{{ route('teacher.students', $classQuery) }}">View students <span aria-hidden="true">&rarr;</span></a>
            <a class="th-secondary" href="{{ route('teacher.applications') }}">Review block applications <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
    <aside class="th-overview" aria-labelledby="teacher-class-overview">
        <div class="th-overview-heading"><span class="tp-eyebrow">YOUR CLASSROOM</span><span class="th-mark" aria-hidden="true">ZRI</span></div>
        <h2 id="teacher-class-overview">{{ $selectedClass['name'] ?? 'Ready for a new beginning' }}</h2>
        @if ($selectedClass)
            <p class="tp-caption">A snapshot of the selected class.</p>
            <dl class="th-stats" aria-label="Class overview">
                @foreach (['assigned' => 'Assigned students', 'started' => 'Started playing', 'completed' => 'Completed Level 1', 'not_started' => 'Not started'] as $key => $label)
                    <div><dt>{{ $label }}</dt><dd>{{ $summary[$key] }}</dd></div>
                @endforeach
            </dl>
            <p class="th-overview-note">Progress totals use available game results.</p>
        @else
            <p class="th-empty-description">No classes have been assigned to you yet. Ask your administrator to assign a block to your account.</p>
            <div class="th-empty-steps"><span>01 &nbsp; Get a block assignment</span><span>02 &nbsp; Review student applications</span><span>03 &nbsp; Follow their progress</span></div>
        @endif
    </aside>
</section>

@if ($classes)
    <form method="GET" action="{{ route('teacher.home') }}" class="tp-toolbar th-class-picker">
        <div class="th-class-copy"><h2>Your teaching space</h2><p>Choose the class you want to view.</p></div>
        <div><label for="home-class">Selected class</label><select id="home-class" name="class">@foreach ($classes as $class)<option value="{{ $class['id'] }}" @selected($selectedClass['id'] === $class['id'])>{{ $class['name'] }}</option>@endforeach</select></div>
        <button class="button" type="submit">View Class</button>
    </form>
    @if ($summary['unavailable'])<div class="tp-notice" role="status">Results for {{ $summary['unavailable'] }} student(s) are temporarily unavailable. They are excluded from progress totals and rankings. Refresh to try again.</div>@endif
@endif

<section class="th-tools" aria-labelledby="teacher-tools-heading">
    <div class="th-section-heading"><div><span class="tp-eyebrow">YOUR WORKSPACE</span><h2 id="teacher-tools-heading">Where would you like to start?</h2></div><p>Keep your classroom moving forward.</p></div>
    <div class="th-tool-grid">
        @foreach ([
            ['route' => 'students', 'number' => '01', 'label' => 'YOUR LEARNERS', 'title' => 'Meet your students', 'description' => 'Open your class roster and view each student’s game results.', 'action' => 'View students'],
            ['route' => 'progress', 'number' => '02', 'label' => 'LEARNING JOURNEY', 'title' => 'Follow their progress', 'description' => 'See scores, attempts, and level completion to understand where help is needed.', 'action' => 'View progress'],
            ['route' => 'leaderboard', 'number' => '03', 'label' => 'CELEBRATE ACHIEVEMENT', 'title' => 'Explore the leaderboard', 'description' => 'Recognize high scores in your class and across the student community.', 'action' => 'View leaderboard'],
            ['route' => 'applications', 'number' => '04', 'label' => 'GROW YOUR CLASSROOM', 'title' => 'Welcome new learners', 'description' => 'Accept or decline students applying to the blocks assigned to you.', 'action' => 'Review applications'],
            ['route' => 'students.import', 'number' => '05', 'label' => 'STUDENT ENROLLMENT', 'title' => 'Import your student list', 'description' => 'Upload Fullname, email, and block for students in your assigned blocks.', 'action' => 'Import students'],
        ] as $tool)
            <a class="th-tool {{ $tool['route'] === 'applications' ? 'th-tool-orange' : '' }}" href="{{ route('teacher.'.$tool['route'], $tool['route'] === 'applications' ? [] : $classQuery) }}">
                <span class="th-tool-number" aria-hidden="true">{{ $tool['number'] }}</span>
                <span class="th-tool-label">{{ $tool['label'] }}</span>
                <h3>{{ $tool['title'] }}</h3><p>{{ $tool['description'] }}</p>
                <span class="th-tool-action">{{ $tool['action'] }} <span aria-hidden="true">&rarr;</span></span>
            </a>
        @endforeach
    </div>
</section>

@if ($selectedClass)
    <section class="tp-panel th-follow-ups" aria-labelledby="teacher-follow-ups-heading">
        <div class="tp-section-heading"><div><span class="tp-eyebrow">A LITTLE GUIDANCE GOES A LONG WAY</span><h2 id="teacher-follow-ups-heading">Students to follow up</h2></div><a class="tp-link" href="{{ route('teacher.students', $classQuery) }}">View all students &rarr;</a></div>
        <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th scope="col">Student</th><th scope="col">Learning status</th><th scope="col">Details</th></tr></thead><tbody>
            @php($followUps = array_filter($students, fn ($student) => $student['unavailable'] || ! (collect($student['results']['rows'])->firstWhere('id', 'level1')['completed'] ?? false)))
            @forelse ($followUps as $student)
                <tr><td>{{ $student['name'] }}</td><td><span class="tp-status">{{ $student['unavailable'] ? 'Results unavailable' : ($student['results']['has_results'] ? 'Level 1 incomplete' : 'Not started') }}</span></td><td><a class="tp-link" href="{{ route('teacher.students.show', ['student' => $student['uid']] + $classQuery) }}">View results</a></td></tr>
            @empty
                <tr><td colspan="3">{{ $summary['assigned'] ? 'Everyone has completed Level 1. Great progress!' : 'No students assigned to this class yet.' }}</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
    <p class="tp-footnote">Class results include only students assigned to this class. Game results refresh at most once per minute.</p>
@endif
<footer class="th-footer">ZeroRiskInitiative &middot; Better choices. Lower risks.</footer>
