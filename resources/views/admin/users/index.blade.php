@extends('layouts.app')
@section('title', 'Manage Accounts | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
    @include('admin.partials.navbar')
    <main class="tp-shell">
        <header class="tp-heading">
            <div><span class="tp-eyebrow">PEOPLE &amp; ACCESS</span><h1>Manage accounts</h1><p>Find student and teacher accounts and manage their access.</p></div>
            <a class="button" href="{{ route('admin.users.create') }}">Create Account &rarr;</a>
        </header>
        @include('partials.messages')
        <p><a class="button" href="{{ route('admin.students.activations') }}">Pending Student Activations &rarr;</a></p>
        <form method="GET" action="{{ route('admin.users.index') }}" class="tp-toolbar">
            <div><label for="q">Find an account</label><input id="q" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name or email"></div>
            <div><label for="role">Account type</label><select id="role" name="role"><option value="">Students and teachers</option><option value="student" @selected($role === 'student')>Students</option><option value="teacher" @selected($role === 'teacher')>Teachers</option></select></div>
            <button class="button" type="submit">Search</button>
        </form>
        <section class="tp-panel" aria-labelledby="account-list-heading">
            <div class="tp-section-heading"><h2 id="account-list-heading">Student &amp; teacher accounts</h2><span class="tp-tag">{{ $users->total() }} accounts</span></div>
            <div class="tp-table-wrap"><table class="tp-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Type</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @forelse ($users as $account)
                        <tr><th scope="row">{{ $account['name'] }}</th><td>{{ $account['email'] ?: 'Not available' }}</td><td><span class="tp-tag">{{ ucfirst($account['role']) }}</span></td><td><a class="admin-delete-link" href="{{ route('admin.users.delete', $account['uid']) }}">Delete account<span class="admin-visually-hidden"> for {{ $account['name'] }}</span></a></td></tr>
                    @empty
                        <tr><td colspan="4">No student or teacher accounts match your search.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <nav class="admin-account-pagination" aria-label="Account list pages">
                <span class="tp-caption">Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</span>
                <div>@if ($users->previousPageUrl())<a class="tp-link" href="{{ $users->previousPageUrl() }}">&larr; Previous</a>@endif @if ($users->nextPageUrl())<a class="tp-link" href="{{ $users->nextPageUrl() }}">Next &rarr;</a>@endif</div>
            </nav>
        </section>
    </main>
</div>
@endsection
