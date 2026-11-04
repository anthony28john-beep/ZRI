@extends('layouts.app')
@section('title', 'Confirm Account Deletion | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
    @include('admin.partials.navbar')
    <main class="tp-shell">
        <header class="tp-heading"><div><span class="tp-eyebrow">ACCOUNT MANAGEMENT</span><h1>Delete account?</h1><p>Review this account before permanently removing its access.</p></div></header>
        @include('partials.messages')
        <section class="tp-panel admin-delete-panel" aria-labelledby="delete-account-heading">
            <h2 id="delete-account-heading">{{ $account['name'] }}</h2>
            <p class="tp-caption">{{ $account['email'] }} &middot; {{ ucfirst($account['role']) }}</p>
            <div class="tp-notice">
                <p>This permanently removes the Firebase sign-in account and website profile. The user will lose website access, and this action cannot be undone.</p>
                <p>{{ $account['role'] === 'teacher' ? 'Their classes and students will remain. Assign those classes to another teacher in Manage Classes.' : 'Their individual class memberships will be removed.' }}</p>
                <p>Saved Firestore game results are retained.</p>
            </div>
            <form action="{{ route('admin.users.destroy', $account['uid']) }}" method="POST" class="tp-assignment-form">
                @csrf @method('DELETE')
                <label for="confirmation">Type DELETE to confirm</label>
                <input id="confirmation" name="confirmation" autocomplete="off" spellcheck="false" required pattern="DELETE" aria-describedby="confirmation-help">
                <p id="confirmation-help" class="tp-caption">Only this student or teacher account will be deleted.</p>
                <x-field-error name="confirmation" />
                <div class="admin-delete-actions"><a class="tp-link" href="{{ route('admin.users.index') }}">Cancel</a><button type="submit" class="button">Delete account permanently</button></div>
            </form>
        </section>
    </main>
</div>
@endsection
