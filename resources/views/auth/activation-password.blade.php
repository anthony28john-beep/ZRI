@extends('layouts.app')
@section('title', 'Set Your Password | ZeroRiskInitiative')
@section('content')
<div class="page"><div class="auth-container">
    <section class="brand-panel"><div class="brand-badge">EMAIL VERIFIED</div><h1>ZeroRisk<span>Initiative</span></h1><p>Welcome, {{ $student['fullname'] }}. Choose a password to use on the website and in the game.</p></section>
    <section class="form-panel">
        <h2>Set your password</h2><p class="subtitle">Verified email: {{ $student['email'] }}</p>
        @include('partials.messages')
        <form method="POST" action="{{ route('new-user.complete') }}">
            @csrf
            <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="new-password" maxlength="128" placeholder="At least 8 characters, letters and numbers" required><x-field-error name="password" /></div>
            <div class="form-group"><label for="password_confirmation">Confirm password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" maxlength="128" required></div>
            <button class="button" type="submit">Activate account</button>
        </form>
        <p class="form-link">Your imported block will be sent to its teacher for approval.</p>
    </section>
</div></div>
@endsection
