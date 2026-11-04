@extends('layouts.app')

@section('title', 'Register | ZeroRiskInitiative')

@section('content')
<div class="page">
    <div class="auth-container">
        <section class="brand-panel">
            <div class="brand-badge">CREATE YOUR ACCOUNT</div>

            <h1>
                Join <span>ZeroRisk</span>
            </h1>

            <p>
    Create your student account to access learning activities
    and track your progress.
</p>
        </section>

        <section class="form-panel">
            <h2>Create Account</h2>
            <p class="subtitle">Choose your block and complete the form to register.</p>
            @if (!$blocks)
                <div class="alert alert-warning" role="status">No blocks are available yet. Ask your administrator to add your block before registering.</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('register.submit') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="block_id">Your block</label>
                    <select id="block_id" name="block_id" required @disabled(!$blocks)>
                        <option value="">Select your block</option>
                        @foreach ($blocks as $id => $name)
                            <option value="{{ $id }}" @selected(old('block_id') === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-field-error name="block_id" />
                    <p class="block-registration-hint">Your teacher must accept your block application before you join.</p>
                </div>

                <div class="form-group">
                    <label for="name">Full name</label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Enter your full name" autocomplete="name"
                        required
                    >
                <x-field-error name="name" />
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Enter your email" autocomplete="email"
                        required
                    >
                <x-field-error name="email" />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password" autocomplete="new-password"
                        placeholder="At least 8 characters, letters and numbers"
                        required
                    >
                <x-field-error name="password" />
                </div>

                <div class="form-group">
                    <label for="password_confirmation">
                        Confirm password
                    </label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation" autocomplete="new-password"
                        placeholder="Enter the password again"
                        required
                    >
                <x-field-error name="password_confirmation" />
                </div>

                <button type="submit" class="button" @disabled(!$blocks)>
                    Create Account
                </button>
            </form>

            <p class="form-link">
                Already have an account?
                <a href="{{ route('login') }}">Sign in</a>
            </p>
        </section>
    </div>
</div>
@endsection
