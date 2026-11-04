@extends('layouts.app')
@section('title', 'Verify Email | ZeroRiskInitiative')
@section('content')
<main class="page verify-page">
    <div class="verify-card">
        <aside class="verify-story" aria-label="Account verification">
            <div class="verify-brand">ZeroRisk<span>Initiative</span></div>
            <div class="verify-art" aria-hidden="true">
                <svg viewBox="0 0 220 190" fill="none">
                    <circle cx="110" cy="94" r="78" stroke="currentColor" stroke-opacity=".14"/>
                    <circle cx="110" cy="94" r="61" stroke="currentColor" stroke-opacity=".22" stroke-dasharray="3 7"/>
                    <rect x="42" y="58" width="136" height="94" rx="12" fill="var(--color-accent-strong)" stroke="currentColor" stroke-width="2"/>
                    <path d="m45 66 58 43a12 12 0 0 0 14 0l58-43M45 144l43-37m87 37-43-37" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="m151 30 30 10v21c0 20-17 31-30 37-13-6-30-17-30-37V40l30-10Z" fill="var(--color-accent-strong)" stroke="currentColor" stroke-width="2"/>
                    <path d="m139 61 8 8 17-19" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="48" cy="37" r="4" fill="currentColor"/>
                    <circle cx="186" cy="123" r="3" fill="currentColor"/>
                </svg>
            </div>
            <div class="verify-story-copy">
                <span class="verify-eyebrow">YOUR ACCOUNT, PROTECTED</span>
                <h2>One last step.<br><span>A safer start.</span></h2>
                <p>Confirm your email to continue your ZeroRiskInitiative learning journey.</p>
            </div>
            <div class="verify-story-footer"><span aria-hidden="true"></span>Better choices. Lower risks.</div>
        </aside>

        <section class="verify-content" aria-labelledby="verify-heading">
            <span class="verify-status"><span aria-hidden="true"></span>EMAIL VERIFICATION REQUIRED</span>
            <h1 id="verify-heading">Check your inbox</h1>
            <p class="verify-intro">Verify your email address before accessing your dashboard.</p>

            <div class="verify-email">
                <svg aria-hidden="true" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>
                <div><span>Your email address</span><strong>{{ $user['email'] }}</strong></div>
            </div>

            @include('partials.messages')

            <ol class="verify-steps" aria-label="Verification steps">
                <li><span aria-hidden="true">1</span><p>Send the verification email using the button below.</p></li>
                <li><span aria-hidden="true">2</span><p>Open the email and select its verification link.</p></li>
                <li><span aria-hidden="true">3</span><p>Come back here and select Continue.</p></li>
            </ol>

            <form action="{{ route('verification.send') }}" method="POST" class="verify-send-form">
                @csrf
                <button type="submit" class="button verify-send-button">Send Verification Email <span aria-hidden="true">&rarr;</span></button>
            </form>
            <a href="{{ route('dashboard') }}" class="verify-continue">I've verified my email. Continue <span aria-hidden="true">&rarr;</span></a>

            <p class="verify-help">Can't find the email? Check your spam or junk folder. You can request a new email once per minute.</p>

            <footer class="verify-footer">
                @if (in_array($user['role'], ['student', 'teacher'], true))
                    <a href="{{ route($user['role'].'.account') }}">Correct my email address</a>
                @endif
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-button">Log Out</button>
                </form>
            </footer>
        </section>
    </div>
</main>
@endsection
