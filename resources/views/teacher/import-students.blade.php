@extends('layouts.app')
@section('title', 'Import Students | ZeroRiskInitiative')
@section('content')
<div class="teacher-page">
    @include('teacher.partials.navbar')
    <main id="main-content" tabindex="-1" class="tp-shell enrollment-page">
        <header class="tp-heading"><div><span class="tp-eyebrow">STUDENT ENROLLMENT</span><h1>Import students</h1><p>Upload your student list. Learners verify their email and set their own password through New User.</p></div><a class="tp-link" href="{{ route('teacher.students.template') }}">Download CSV template &darr;</a></header>
        @include('partials.messages')
        <ol class="enrollment-workflow" aria-label="Import steps"><li>1. Prepare CSV</li><li>2. Preview and correct</li><li>3. Confirm import</li><li>4. Guide student activation</li></ol>
        @if (!$blocks)
            <div class="tp-notice" role="status">No blocks are assigned to you yet. Ask your administrator to assign a block before importing students.</div>
        @else
            <section class="tp-panel"><h2>Your assigned blocks</h2><p class="tp-caption">Use these exact names in the CSV block column.</p><ul class="enrollment-blocks">@foreach ($blocks as $blockName)<li class="tp-tag">{{ $blockName }}</li>@endforeach</ul></section>
        @endif
        <section class="tp-panel enrollment-upload">
            <h2>1. Prepare your list</h2><p class="tp-caption">Use exactly three headers: <strong>Fullname,email,block</strong>. Use only the block names currently assigned to you. Save as UTF-8 CSV, up to 100 students and 1 MB per file.</p>
            <p class="tp-caption">Existing website accounts are kept unchanged. Existing game accounts keep their password and scores; after import, they can sign in to create their website profile. New students receive a pending block application after activation.</p>
            <form method="POST" action="{{ route('teacher.students.import.preview') }}" enctype="multipart/form-data" class="enrollment-upload-form">
                @csrf
                <div class="form-group"><label for="csv">Student CSV file</label><input id="csv" name="csv" type="file" accept=".csv,text/csv" required @disabled(!$blocks)><x-field-error name="csv" /></div>
                <button class="button" type="submit" @disabled(!$blocks)>Preview students</button>
            </form>
        </section>
        @if ($rows !== null)
            <section class="tp-panel">
                <div class="tp-section-heading"><h2>{{ $report ? 'Import results' : '2. Review before importing' }}</h2><span class="tp-tag">{{ count($rows) }} rows</span></div>
                <div class="tp-notice" role="status">
                    @if ($report)Download your row-by-row report now. It is available for one hour after import.
                    @else This preview is available for 15 minutes. No students are imported until you confirm below.@endif
                    @if ($expiresAt)<strong>Available until {{ \Carbon\CarbonImmutable::parse($expiresAt)->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila).</strong>@endif
                    Summary history remains below; it does not contain student names or emails.
                </div>
                <p class="tp-caption">{{ collect($rows)->where('status', $report ? 'imported' : 'ready')->count() }} {{ $report ? 'imported' : 'ready' }} &middot; {{ collect($rows)->where('status', 'skipped')->count() }} skipped &middot; {{ collect($rows)->where('status', 'error')->count() }} need correction. Invalid rows are excluded; correct them and upload again.</p>
                <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th>Row</th><th>Full name</th><th>Email</th><th>Block</th><th>Result</th></tr></thead><tbody>
                    @foreach ($rows as $row)<tr><td>{{ $row['row'] }}</td><th scope="row">{{ $row['fullname'] }}</th><td>{{ $row['email'] }}</td><td>{{ $row['block'] }}</td><td><strong>{{ ucfirst($row['status']) }}</strong><br>{{ $row['message'] }}</td></tr>@endforeach
                </tbody></table></div>
                @if (!$report)
                    <form method="POST" action="{{ route('teacher.students.import.store', $preview) }}" class="enrollment-confirm">
                        @csrf
                        <label><input type="checkbox" name="confirmation" value="1" required> I reviewed the names, emails, and blocks. Import ready rows only.</label>
                        <button class="button" type="submit" @disabled(!collect($rows)->contains('status', 'ready'))>Import students</button>
                    </form>
                @else
                    <p><a class="tp-link" href="{{ route('teacher.students.import.report', $preview) }}">Download import report &darr;</a></p>
                    <a class="tp-link" href="{{ route('teacher.students.import') }}">Import another list &rarr;</a>
                @endif
            </section>
        @endif
        <section class="tp-panel" aria-labelledby="import-next-heading">
            <h2 id="import-next-heading">After import: what happens next?</h2>
            <ol class="enrollment-guidance"><li>Ask newly imported students to open <strong>New User</strong> on the login page, verify their email, and set a password. Existing game accounts use their normal login.</li><li>After activation, review the student's request in <a href="{{ route('teacher.applications') }}">Block Applications</a>. Importing does not automatically approve block membership.</li><li><strong>Correcting a CSV error:</strong> fix the failed row and upload again. Existing students and invitations are skipped, so re-uploading does not create duplicates.</li><li><strong>Correcting an imported invitation:</strong> ask the administrator to remove it in Pending Activations, then import the corrected row. Existing accounts and game scores are preserved.</li></ol>
        </section>
        <section class="tp-panel" aria-labelledby="import-history-heading">
            <div class="tp-section-heading"><h2 id="import-history-heading">Recent import history</h2><span class="tp-tag">Your latest 20 imports</span></div>
            <p class="tp-caption">Summaries remain after reports expire. Full row reports are temporary; download them within one hour. Counts describe the import result, not current student activation status.</p>
            <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th scope="col">Imported on (Asia/Manila)</th><th scope="col">Rows</th><th scope="col">Imported</th><th scope="col">Skipped</th><th scope="col">Needs correction</th><th scope="col">Report</th></tr></thead><tbody>
                @forelse ($history as $entry)
                    <tr><th scope="row">{{ \Carbon\CarbonImmutable::parse($entry['created_at'])->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}</th><td>{{ $entry['total'] }}</td><td>{{ $entry['imported'] }}</td><td>{{ $entry['skipped'] }}</td><td>{{ $entry['errors'] }}</td><td>@if ($entry['report_available'])<a class="tp-link" href="{{ route('teacher.students.import.report', $entry['id']) }}">Download report</a>@else<span class="tp-caption">Report no longer available</span>@endif</td></tr>
                @empty<tr><td colspan="6">No import history yet. Completed imports will appear here.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </main>
</div>
@endsection
