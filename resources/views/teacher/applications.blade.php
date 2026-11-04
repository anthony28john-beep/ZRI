@extends('layouts.app')
@section('title', 'Block Applications | ZeroRiskInitiative')
@section('content')
@include('teacher.partials.navbar')
<main class="tp-shell">
    <header class="tp-heading"><div><span class="tp-eyebrow">YOUR CLASSROOMS</span><h1>Block Applications</h1><p>Review students who want to join your assigned blocks. Accepting adds them to the block and your class roster.</p></div></header>
    @include('partials.messages')
    @if ($unavailable)
        <section class="tp-panel tp-empty"><h2>Applications temporarily unavailable</h2><p>Please refresh this page to try again.</p><a class="button" href="{{ route('teacher.applications') }}">Try Again</a></section>
    @else
        <section class="tp-panel"><div class="tp-section-heading"><h2>Pending requests</h2><span class="tp-tag">{{ count($applications) }} applications</span></div>
            <div class="tp-table-wrap"><table class="tp-table"><thead><tr><th scope="col">Student</th><th scope="col">Block</th><th scope="col">Actions</th></tr></thead><tbody>
                @forelse ($applications as $application)
                    <tr><td>{{ $application['name'] }}</td><td>{{ $application['block'] }} @if (!$application['can_accept'])<span class="tp-caption">Already in a block</span>@endif</td><td><div class="block-review-actions">
                        <form method="POST" action="{{ route('teacher.applications.accept', ['student' => $application['student_uid'], 'application' => $application['id']]) }}">@csrf<button type="submit" class="button" @disabled(!$application['can_accept'])>Accept</button></form>
                        <form method="POST" action="{{ route('teacher.applications.decline', ['student' => $application['student_uid'], 'application' => $application['id']]) }}">@csrf<button type="submit" class="text-button">Decline</button></form>
                    </div></td></tr>
                @empty<tr><td colspan="3">No pending applications for your blocks.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    @endif
</main>
@endsection
