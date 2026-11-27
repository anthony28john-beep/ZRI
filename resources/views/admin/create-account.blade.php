@extends('layouts.app')

@section('title', 'Create Account | ZeroRiskInitiative')

@section('content')


<div class="admin-page">
    @include('admin.partials.navbar')

    <main id="main-content" tabindex="-1" class="admin-container admin-main">
        <div class="admin-page-heading">
            <div class="admin-eyebrow">PEOPLE &amp; ACCESS</div>
            <h1>Create an account</h1>
            <p class="admin-description">
                Create student or teacher accounts with a temporary password. Users replace it and verify their email before accessing their dashboard.
            </p>
        </div>

        <div class="admin-messages">
            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="admin-grid">
            <aside class="admin-sidebar" aria-label="Account creation guidance">
                <section class="admin-guide" aria-labelledby="account-types-heading">
                    <h2 id="account-types-heading">Account types</h2>
                    <div class="admin-guide-item">
                        <div class="admin-guide-role"><span aria-hidden="true"></span>Student</div>
                        <p>Create an individual student here, or let teachers import students through CSV. Students created here sign in with their temporary password, complete setup, then apply to a block for teacher approval.</p>
                        <a class="tp-link" href="{{ route('admin.students.activations') }}">Pending Activations &rarr;</a>
                    </div>
                    <div class="admin-guide-item">
                        <div class="admin-guide-role"><span aria-hidden="true"></span>Teacher</div>
                        <p>Create an account for a teacher to access their dashboard.</p>
                    </div>
                </section>
            </aside>

            <section class="admin-form-panel" aria-labelledby="new-account-heading">
                <div class="admin-panel-heading">
                    <div class="admin-panel-icon" aria-hidden="true">
                        <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="8" r="3"/>
                            <path d="M3 20v-2a6 6 0 0 1 12 0v2M19 7v6M16 10h6"/>
                        </svg>
                    </div>
                    <div>
                        <h2 id="new-account-heading">Add New Account</h2>
                        <p>Choose an account type and provide temporary sign-in credentials.</p>
                    </div>
                </div>

                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf
                    <div class="admin-form-body">
                        <fieldset class="admin-fieldset">
                            <legend>Account details</legend>
                            <div class="admin-fields">
                                <div class="form-group">
                                    <label for="name">Full name</label>
                                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Enter the user's full name" autocomplete="name" required>
                <x-field-error name="name" />
                                </div>
                                <div class="form-group">
                                    <label for="email">Email address</label>
                                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Enter the user's email" autocomplete="email" required>
                <x-field-error name="email" />
                                </div>
                                <div class="form-group admin-field-wide">
                                    <label for="role">Account type</label>
                                    <select id="role" name="role" required>
                                        <option value="teacher" @selected(old('role', 'teacher') === 'teacher')>Teacher</option>
                                        <option value="student" @selected(old('role') === 'student')>Student</option>
                                    </select>
                <x-field-error name="role" />
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="admin-fieldset">
                            <legend>Sign-in credentials</legend>
                            <div class="admin-fields">
                                <div class="form-group">
                                    <label for="password">Temporary password</label>
                                    <input type="password" id="password" name="password" placeholder="At least 8 characters, letters and numbers" autocomplete="new-password" aria-describedby="password-hint" required>
                <x-field-error name="password" />
                                </div>
                                <div class="form-group">
                                    <label for="password_confirmation">Confirm temporary password</label>
                                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Enter the password again" autocomplete="new-password" required>
                <x-field-error name="password_confirmation" />
                                </div>
                            </div>
                            <p class="admin-field-hint" id="password-hint">Use at least 8 characters, including a letter and a number, and enter the same password in both fields.</p>
                        </fieldset>
                    </div>

                    <div class="admin-form-footer">
                        <p>Review the account details before creating the account.</p>
                        <button type="submit" class="button admin-submit">
                            Create Account
                            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14m-6-6 6 6-6 6"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </section>
        </div>
        <footer class="admin-footer">ZeroRiskInitiative &middot; Administration workspace</footer>
    </main>
</div>

@endsection
