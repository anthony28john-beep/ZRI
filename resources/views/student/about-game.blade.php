@extends('layouts.app')

@section('title', 'About the Game | ZeroRiskInitiative')

@section('content')
<div class="student-dashboard game-about-page">
    @include('student.partials.navbar')

    <main class="student-content">
        <header class="about-intro">
            <div class="about-intro-copy">
            <div class="brand-badge">ZERO RISK INITIATIVE</div>
            <h1>About the <span>Game</span></h1>
            <p>
                ZeroRiskInitiative is an educational game set on a construction site.
                Explore the environment, inspect potential hazards, and answer safety questions
                to practice recognizing risks and making better decisions.
            </p>
            </div>
            <div class="about-intro-emblem" aria-hidden="true">
                <svg viewBox="0 0 80 80" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m40 6 26 10v22c0 17-15 28-26 36C29 66 14 55 14 38V16L40 6Z"/>
                    <path d="m27 39 9 9 18-22"/>
                </svg>
            </div>
        </header>

        <nav class="about-chapter-nav" aria-label="About the Game sections">
            <a href="#main-menu"><span>01</span> Main Menu <span aria-hidden="true">&rarr;</span></a>
            <a href="#choose-level"><span>02</span> Choose Your Level <span aria-hidden="true">&rarr;</span></a>
            <a href="#game-interface"><span>03</span> Game Interface <span aria-hidden="true">&rarr;</span></a>
        </nav>

        <section class="about-chapter about-overview" id="main-menu" aria-labelledby="main-menu-heading">
            <div class="about-chapter-heading">
                <span class="about-step" aria-hidden="true">01</span>
                <div><p class="about-eyebrow">THE STARTING POINT</p><h2 id="main-menu-heading">Your Adventure Starts Here</h2></div>
            </div>
            <figure class="game-about-preview">
                <div class="about-frame-bar" aria-hidden="true"><span class="about-frame-dots"><i></i><i></i><i></i></span><span>MAIN MENU</span><span>01 / 03</span></div>
                <img src="{{ asset('images/student/about1.png').'?v='.filemtime(public_path('images/student/about1.png')) }}"
                    alt="ZeroRiskInitiative main menu showing a construction site with Start, Settings, and Exit options"
                    width="1920" height="1080" class="game-about-image">
                <figcaption>The main menu of ZeroRiskInitiative.</figcaption>
            </figure>
            <p class="about-overview-note">
                The main menu lets players start their adventure, adjust game settings, or exit.
                Once you begin, look closely at your surroundings and put your hazard-identification
                skills to the test.
            </p>
        </section>

        <section class="about-chapter about-split" id="choose-level" aria-labelledby="level-selection-heading">
            <div class="about-section-copy">
            <div class="about-chapter-heading">
                <span class="about-step" aria-hidden="true">02</span>
                <div><p class="about-eyebrow">CHOOSE YOUR CHALLENGE</p>
            <h2 id="level-selection-heading">Choose Your Level</h2>
                </div>
            </div>
            <p>
                Select a level from the hub to begin your next challenge. Start with the tutorial
                to learn the basics, then enter Level 1 when you're ready. Follow Inspector Andrea's
                guidance as you explore.
            </p>
            </div>
            <figure class="game-about-preview">
                <div class="about-frame-bar" aria-hidden="true"><span class="about-frame-dots"><i></i><i></i><i></i></span><span>LEVEL SELECTION</span><span>02 / 03</span></div>
                <img src="{{ asset('images/student/about2.png').'?v='.filemtime(public_path('images/student/about2.png')) }}"
                    alt="Level-selection hub with Tutorial, Level 1, and Exit doors and guidance from Inspector Andrea"
                    width="1920" height="1080" class="game-about-image" loading="lazy">
                <figcaption>Choose a level and get ready for your next mission.</figcaption>
            </figure>
        </section>
        <section class="about-chapter about-split about-interface" id="game-interface" aria-labelledby="game-interface-heading">
            <div class="about-section-copy">
            <div class="about-chapter-heading">
                <span class="about-step" aria-hidden="true">03</span>
                <div><p class="about-eyebrow">KNOW YOUR SURROUNDINGS</p>
            <h2 id="game-interface-heading">Game Interface</h2>
                </div>
            </div>
            <p>
                The objectives panel shows your current task. At the top, track the hazards found,
                your score, and the timer. Press <kbd>H</kbd> for a hint or <kbd>M</kbd> for the map to help you navigate
                the construction site.
            </p>
            </div>
            <figure class="game-about-preview">
                <div class="about-frame-bar" aria-hidden="true"><span class="about-frame-dots"><i></i><i></i><i></i></span><span>IN-GAME VIEW</span><span>03 / 03</span></div>
                <img src="{{ asset('images/student/about3.png').'?v='.filemtime(public_path('images/student/about3.png')) }}"
                    alt="ZeroRiskInitiative gameplay showing the objectives panel, hazard counter, score, timer, hint prompt, and minimap"
                    width="1920" height="1080" class="game-about-image" loading="lazy">
                <figcaption>Follow your objectives and monitor your progress as you explore.</figcaption>
            </figure>
        </section>
        <div class="about-play-callout">
            <div><p class="about-eyebrow">BETTER CHOICES. LOWER RISKS.</p><h2>Ready for your next challenge?</h2></div>
            <a href="{{ route('student.play-game') }}" class="button about-play-button">Download Game <span aria-hidden="true">&rarr;</span></a>
        </div>
    </main>
</div>
@endsection
