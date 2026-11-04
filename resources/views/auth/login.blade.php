@extends('layouts.app')

@section('title', 'Login | ZeroRiskInitiative')

@section('content')
<div class="page">
    <div class="auth-container">
        <section class="brand-panel">
            <div class="brand-badge">ZERO RISK SYSTEM</div>

            <h1>
                ZeroRisk<span>Initiative</span>
            </h1>

            <p>
                Sign in to access your student or teacher dashboard
                and continue your learning activities.
            </p>
        </section>

        <section class="form-panel">
            <h2>Welcome Back</h2>
            <p class="subtitle">Enter your account information.</p>

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
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

            <form action="{{ route('login.submit') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="email">Email address</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Enter your email" autocomplete="email"
                        required
                        autofocus
                    >
                <x-field-error name="email" />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password" autocomplete="current-password"
                        required
                    >
                <x-field-error name="password" />
                </div>

                <button type="submit" class="button">
                    Sign In
                </button>
            </form>

            <p class="form-link"><a href="{{ route('password.request') }}">Forgot your password?</a></p>

            <p class="form-link">
                Don't have an account?
                <a href="{{ route('register') }}">Create account</a>
            </p>
        </section>
    </div>
</div>
@endsection