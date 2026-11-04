@extends('layouts.app')
@section('title', 'Remove Student | Teacher Portal')
@section('content')
@include('teacher.partials.navbar')
<main class="tp-shell">
    <a class="tp-link" href="{{ route('teacher.students', ['class' => $classroom['id']]) }}">&larr; Back to class roster</a>
    <header class="tp-heading"><div><span class="tp-eyebrow">CLASS MEMBERSHIP</span><h1>Remove student?</h1><p>Review the membership below before confirming.</p></div></header>
    @include('partials.messages')
    <section class="tp-panel tp-removal-panel" aria-labelledby="remove-student-heading">
        <h2 id="remove-student-heading">{{ $student['name'] }}</h2>
        <p class="tp-caption">{{ $classroom['name'] }}</p>
        <div class="tp-notice">
            @if (isset($classroom['block_id']))
                This student will leave this block and all class rosters that use it. They can apply to a block again, subject to teacher approval.
            @else
                This student will be removed from this class's individual roster. Other class and block memberships will stay in place.
            @endif
            Their account and all game scores will be kept.
        </div>
        <form method="POST" action="{{ route('teacher.students.destroy', ['classroom' => $classroom['id'], 'student' => $student['uid']]) }}" class="tp-assignment-form">
            @csrf @method('DELETE')
            <label class="tp-removal-check"><input type="checkbox" name="confirmation" value="1" required><span>Remove {{ $student['name'] }} from {{ $classroom['name'] }}.</span></label>
            <div class="tp-removal-actions"><button type="submit" class="button">Confirm removal</button><a class="tp-link" href="{{ route('teacher.students', ['class' => $classroom['id']]) }}">Cancel</a></div>
        </form>
    </section>
</main>
@endsection
