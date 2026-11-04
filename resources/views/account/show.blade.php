@extends('layouts.app')

@section('title', $accountTitle)

@section('content')
<div class="account-page">
    @include('partials.navbar', ['portalRole' => $accountRole])

    <main class="account-content">
        @include('partials.messages')
        @if ($user['must_change_password'])
            <div class="alert alert-warning" role="status">Replace your temporary password to continue to your dashboard.</div>
        @endif

        <section class="account-card">
            <div class="account-header">
                <div class="account-header-icon" aria-hidden="true">
                    <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="10" width="14" height="11" rx="2"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2"/>
                    </svg>
                </div>
                <div>
                <h1>Account Information</h1>

                <p>
                    Update your email address or password.
                    Enter your current password to confirm the change.
                </p>
                </div>
            </div>

            <div class="account-name">
                <div class="account-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user['name'], 0, 1)) }}</div>
                <strong>{{ ucfirst($accountRole) }}:</strong> {{ $user['name'] }}
            </div>

            <form
                action="{{ route($accountRole.'.account.update') }}"
                method="POST"
                class="account-form"
            >
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="email">Email address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email', $user['email']) }}"
                        required
                    >
                <x-field-error name="email" />
                </div>

                <div class="form-group">
                    <label for="current_password">
                        Current password
                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        placeholder="Enter your current password"
                        autocomplete="current-password"
                        required
                    >
                <x-field-error name="current_password" />
                </div>

                <div class="form-group">
                    <label for="new_password">
                        New password
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        placeholder="{{ $user['must_change_password'] ? 'Enter a new password' : 'Leave blank to keep current password' }}"
                        autocomplete="new-password"
                        @required($user['must_change_password'])
                    >
                <x-field-error name="new_password" />
                </div>

                <div class="form-group">
                    <label for="new_password_confirmation">
                        Confirm new password
                    </label>

                    <input
                        type="password"
                        id="new_password_confirmation"
                        name="new_password_confirmation"
                        placeholder="Enter the new password again"
                        autocomplete="new-password"
                        @required($user['must_change_password'])
                    >
                <x-field-error name="new_password_confirmation" />
                </div>

                <p class="account-password-hint">New passwords need at least 8 characters, including a letter and a number.</p>
                <div class="account-actions">
                    <button type="submit" class="button account-button">
                        Save Changes
                        <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m5 12 4 4L19 6"/>
                        </svg>
                    </button>
                </div>
            </form>

            <a href="{{ route('dashboard') }}" class="back-link">
                <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5m6-6-6 6 6 6"/>
                </svg>
                Return to Dashboard
            </a>
        </section>
        @if ($accountRole === 'student')
            @include('student.partials.block-membership')
        @endif
    </main>
</div>
@endsection
