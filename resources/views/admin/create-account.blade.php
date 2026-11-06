@extends('layouts.app')

@section('title', 'Create Account | ZeroRiskInitiative')

@section('content')


<div class="admin-page">
    @include('admin.partials.navbar')

    <main class="admin-container admin-main">
        <div class="admin-page-heading">
            <div class="admin-eyebrow">PEOPLE &amp; ACCESS</div>
            <h1>Create a teacher account</h1>
            <p class="admin-description">
                Add teachers here. For students, import your CSV list and let them activate their own accounts.
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
            <aside class="admin-sidebar" aria-label="Administrator information">
                <section class="admin-profile" aria-label="Your account">
                    <div class="admin-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user['name'], 0, 1)) }}</div>
                    <div class="admin-profile-name">{{ $user['name'] }}</div>
                    <p class="admin-profile-label">Account administrator</p>
                    <div class="role-badge">{{ $user['role'] }}</div>
                    <form action="{{ route('logout') }}" method="POST" class="admin-logout">
                        @csrf
                        <button type="submit" class="admin-logout-button">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H4v14h5M14 8l4 4-4 4M8 12h12"/>
                            </svg>
                            Log Out
                        </button>
                    </form>
                </section>

                <section class="admin-guide" aria-labelledby="account-types-heading">
                    <h2 id="account-types-heading">Account types</h2>
                    <div class="admin-guide-item">
                        <div class="admin-guide-role"><span aria-hidden="true"></span>Student</div>
                        <p>Import Fullname, email, and block. Students verify their email before setting a password.</p>
                        <a class="tp-link" href="{{ route('admin.students.import') }}">Import Students &rarr;</a>
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
                        <p>Create a teacher account with a temporary password.</p>
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
                                        <option value="teacher">Teacher</option>
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
