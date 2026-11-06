@extends('layouts.app')
@section('title', 'New User | ZeroRiskInitiative')
@section('content')
<div class="page"><div class="auth-container">
    <section class="brand-panel">
        <div class="brand-badge">YOUR LEARNING STARTS HERE</div>
        <h1>ZeroRisk<span>Initiative</span></h1>
        <p>Activate your student account using the email your school has registered.</p>
        <ol class="activation-steps"><li>Enter your imported email</li><li>Verify your email and select Continue</li><li>Set your password and sign in</li></ol>
    </section>
    <section class="form-panel">
        <h2>New User</h2><p class="subtitle">Enter the email address provided to your administrator.</p>
        @include('partials.messages')
        <form method="POST" action="{{ route('new-user.send') }}">
            @csrf
            <div class="form-group"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" placeholder="Enter your registered email" required><x-field-error name="email" /></div>
            <button class="button" type="submit">Send verification link</button>
        </form>
        <p class="form-link">Check your inbox and spam folder. You can request another link after one minute. Each link expires after one hour.</p>
        <p class="form-link">Already registered in the game or website? <a href="{{ route('login') }}">Sign in</a> or <a href="{{ route('password.request') }}">reset your password</a>.</p>
        <p class="form-link">Not on the student list? Ask your administrator to import your details.</p>
    </section>
</div></div>
@endsection
