@extends('layouts.app')
@section('title', 'Delete Block | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
    @include('admin.partials.navbar')
    <main class="tp-shell">
        <a class="tp-link" href="{{ route('admin.classes') }}">&larr; Back to Manage Classes</a>
        <header class="tp-heading"><div><span class="tp-eyebrow">BLOCK MANAGEMENT</span><h1>Delete {{ $block['name'] }}?</h1><p>Deleted blocks are removed from the registration list.</p></div></header>
        @include('partials.messages')
        <section class="tp-panel admin-delete-panel">
            @if ($block['students'] || $block['classes'] || ($block['applications'] ?? 0))
                <h2>This block is still in use</h2>
                <p class="tp-caption">{{ $block['students'] }} students &middot; {{ $block['classes'] }} class assignments &middot; {{ $block['applications'] ?? 0 }} pending applications</p>
                <div class="tp-notice">Move its students to another block, resolve pending applications, and change its class assignments first. Accounts and classes will not be deleted.</div>
                <a class="button" href="{{ route('admin.classes') }}">Go back and update assignments</a>
            @else
                <h2>{{ $block['name'] }}</h2>
                <p class="tp-caption">No students or classes are assigned to this block.</p>
                <form method="POST" action="{{ route('admin.blocks.destroy', $block['id']) }}" class="tp-assignment-form">
                    @csrf @method('DELETE')
                    <label for="confirmation">Type DELETE to confirm</label>
                    <input id="confirmation" name="confirmation" required pattern="DELETE" autocomplete="off" spellcheck="false">
                    <x-field-error name="confirmation" />
                    <div class="admin-delete-actions"><a class="tp-link" href="{{ route('admin.classes') }}">Cancel</a><button class="button" type="submit">Delete block</button></div>
                </form>
            @endif
        </section>
    </main>
</div>
@endsection
