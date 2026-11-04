@extends('layouts.app')

@section('title', 'Student Dashboard | ZeroRiskInitiative')

@section('content')


<div class="student-dashboard game-home">
    @include('student.partials.navbar')

    <main>
        @if (session('success') || session('warning') || $errors->any())
            <div class="game-container game-notices">@include('partials.messages')</div>
        @endif

        <section class="game-hero" aria-labelledby="game-title">
            {{-- Image assets are added only after the user supplies them. --}}
            <div class="game-hero-visual" aria-hidden="true">
                @php($heroImage = config('student_dashboard.images.hero'))
                @if ($heroImage && is_file(public_path($heroImage)))
                    <img src="{{ asset($heroImage).'?v='.filemtime(public_path($heroImage)) }}" alt="" fetchpriority="high">
                @endif
            </div>
            <div class="game-container game-hero-inner">
                <div class="game-hero-copy">
                    <p class="game-eyebrow">PLAY &middot; LEARN &middot; BE SAFER</p>
                    <h1 id="game-title">ZeroRisk<span>Initiative</span></h1>
                    <p class="game-tagline">A GAME FOR A SAFER TOMORROW</p>
                    <p class="game-hero-description">
                        Step into a world where every choice matters.<br>
                        Play, complete missions, and learn real-life skills that
                        help you make safer decisions &mdash; online and offline.
                    </p>
                    <div class="game-hero-actions">
                        <a href="{{ route('student.play-game') }}" class="game-button game-button-primary">
                            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 7h10c2 0 3 2 4 5l1 5c.5 3-2 4-4 2l-3-3H9l-3 3c-2 2-4.5 1-4-2l1-5c1-3 2-5 4-5Z"/>
                                <path d="M7 10v5m-2.5-2.5h5M16 11h.01M18 14h.01"/>
                            </svg>
                            Play Now
                            <span class="game-button-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('student.about-game') }}" class="game-button game-button-outline">
                            <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5c-3-2-6-2-10-1v15c4-1 7-1 10 1 3-2 6-2 10-1V4c-4-1-7-1-10 1Zm0 0v15"/>
                            </svg>
                            Learn More
                        </a>
                    </div>
                </div>
                <p class="game-motto">Better Choices.<br><span>Lower Risks.</span></p>
            </div>
        </section>

        <section class="game-features" aria-label="Game highlights">
            <div class="game-container game-features-grid">
                <div class="game-feature game-feature-orange">
                    <div class="game-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 9h12c3 0 4 3 5 6l2 7c1 4-3 6-5 3l-4-4h-8l-4 4c-2 3-6 1-5-3l2-7c1-3 2-6 5-6Z"/><path d="M10 12v7m-3.5-3.5h7M21 13h.01M24 17h.01"/>
                        </svg>
                    </div>
                    <div><h2>Engaging Gameplay</h2><p>Fun and challenging missions</p></div>
                </div>
                <div class="game-feature game-feature-blue">
                    <div class="game-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m16 3 11 4v9c0 6-6 10-11 13C11 26 5 22 5 16V7l11-4Z"/><path d="m10 16 4 4 8-9"/>
                        </svg>
                    </div>
                    <div><h2>Real-Life Skills</h2><p>Build better decision making</p></div>
                </div>
                <div class="game-feature game-feature-purple">
                    <div class="game-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="10" r="5"/><path d="M2 28v-3a10 10 0 0 1 20 0v3H2ZM23 5a5 5 0 0 1 0 10m3 5a8 8 0 0 1 4 7"/>
                        </svg>
                    </div>
                    <div><h2>Track Your Progress</h2><p>See how you grow</p></div>
                </div>
                <div class="game-feature game-feature-green">
                    <div class="game-feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 4h14v9a7 7 0 0 1-14 0V4ZM16 20v8m-6 1h12M9 7H3v4a6 6 0 0 0 7 6M23 7h6v4a6 6 0 0 1-7 6"/>
                        </svg>
                    </div>
                    <div><h2>Compete &amp; Earn</h2><p>Climb the leaderboard</p></div>
                </div>
            </div>
        </section>

        <section class="game-about" aria-labelledby="game-about-heading">
            <div class="game-container game-about-grid">
                <div class="game-about-copy">
                    <p class="game-eyebrow">ABOUT THE GAME</p>
                    <h2 id="game-about-heading">More Than Just a <span>Game</span></h2>
                    <p>ZeroRiskInitiative is an educational game designed to teach important life skills through interactive challenges, and missions.</p>
                    <a href="{{ route('student.about-game') }}" class="game-button game-button-outline">
                        Explore the World <span class="game-button-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
                <div class="game-journey" aria-label="Explore, solve, and improve">
                    @foreach ([['key' => 'explore', 'title' => 'Explore', 'caption' => 'Discover the game'], ['key' => 'solve', 'title' => 'Solve', 'caption' => 'Complete tasks'], ['key' => 'improve', 'title' => 'Improve', 'caption' => 'Build real-life skills']] as $card)
                        <article class="game-journey-card game-journey-{{ $card['key'] }}">
                            @if (config('student_dashboard.images.'.$card['key']))
                                <img src="{{ asset(config('student_dashboard.images.'.$card['key'])) }}" alt="" loading="lazy">
                            @endif
                            <div class="game-journey-caption"><h3>{{ $card['title'] }}</h3><p>{{ $card['caption'] }}</p></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</div>

@endsection
