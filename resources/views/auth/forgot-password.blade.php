@extends('layouts.app')
@section('title', 'Reset Password | ZeroRiskInitiative')
@section('content')
<main class="page">
    <section class="dashboard-card recovery-card">
        <div class="brand-badge">ACCOUNT RECOVERY</div>
        <h1>Forgot your password?</h1>
        <p>Enter your email address to receive a password reset link.</p>
        @include('partials.messages')
        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->has('email')) aria-describedby="email-error" @endif>
                <x-field-error name="email" />
            </div>
            <button type="submit" class="button">Send Reset Link</button>
        </form>
        <p class="form-link"><a href="{{ route('login') }}">Return to Sign In</a></p>
    </section>
</main>
@endsection
