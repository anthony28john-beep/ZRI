@extends('layouts.app')
@section('title', 'Temporarily Unavailable | ZeroRiskInitiative')
@section('content')
<main class="page">
    <section class="dashboard-card recovery-card">
        <div class="brand-badge">PLEASE TRY AGAIN</div>
        <h1>Account service unavailable</h1>
        <p>We couldn't check your account right now. Your session has been kept. Please try again shortly.</p>
        <p class="form-link"><a href="{{ route('dashboard') }}">Try Again</a></p>
        <form action="{{ route('logout') }}" method="POST" class="recovery-logout">
            @csrf
            <button type="submit" class="text-button">Log Out</button>
        </form>
    </section>
</main>
@endsection
