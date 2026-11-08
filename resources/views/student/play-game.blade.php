@extends('layouts.app')

@section('title', 'Download Game | ZeroRiskInitiative')

@section('content')
<div class="student-dashboard download-page">
    @include('student.partials.navbar')

    <main class="student-content" aria-label="Download Game">
        <header class="download-heading">
            <span class="brand-badge">YOUR NEXT MISSION</span>
            <h1>Download the game</h1>
            <p>Take your safety skills to the construction site with ZeroRiskInitiative.</p>
        </header>

        <section class="download-card" aria-labelledby="download-title">
            <div class="download-art" aria-hidden="true">
                <span class="download-art-label">ZERO RISK INITIATIVE</span>
                <div class="download-art-icon">
                    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 39v-5a20 20 0 0 1 40 0v5M7 39h50v8H7zM26 15v15M38 15v15M19 20v12M45 20v12"/><path d="M22 54h20"/></svg>
                </div>
                <strong>Better choices.<br><span>Lower risks.</span></strong>
                <span class="download-art-caption">EXPLORE &nbsp; / &nbsp; INSPECT &nbsp; / &nbsp; LEARN</span>
            </div>
            <div class="download-details">
                <span class="download-provider">GAME FILES &middot; GOOGLE DRIVE</span>
                <h2 id="download-title">Your training starts here.</h2>
                <p>Explore the site, identify hazards, and practice making safer decisions. Open the game folder to find the files you need to get started.</p>
                <a class="button download-button" href="{{ config('student_dashboard.download_url') }}" target="_blank" rel="noopener noreferrer">
                    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg>
                    Open download folder
                    <span class="download-external" aria-hidden="true">&#8599;</span>
                    <span class="download-sr-only"> (opens Google Drive in a new tab)</span>
                </a>
                <p class="download-caption">Opens Google Drive in a new tab.</p>
                <a class="download-about" href="{{ route('student.about-game') }}">Learn about the game <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>

        <section class="download-start" aria-labelledby="download-start-title">
            <h2 id="download-start-title">Get ready in three steps</h2>
            <ol class="download-steps">
                <li><span class="download-step-number" aria-hidden="true">01</span><div><h3>Open the folder</h3><p>Use the button above to view the available game files on Google Drive.</p></div></li>
                <li><span class="download-step-number" aria-hidden="true">02</span><div><h3>Download your files</h3><p>Save the game to your computer and follow any instructions included in the folder. If it is a ZIP file, extract it first.</p></div></li>
                <li><span class="download-step-number" aria-hidden="true">03</span><div><h3>Sign in and play</h3><p>Launch the game and use the same email and password as your website account.</p></div></li>
            </ol>
        </section>
        <p class="download-help">Need help accessing the folder? Contact your teacher or administrator.</p>
    </main>
</div>
@endsection
