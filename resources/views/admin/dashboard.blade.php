@extends('layouts.app')
@section('title', 'Admin Home | ZeroRiskInitiative')
@section('content')
<div class="admin-page">
    @include('admin.partials.navbar')
    <main class="admin-container admin-main admin-home">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="admin-home-hero" aria-labelledby="admin-home-heading">
            <div class="admin-home-intro">
                <span class="admin-home-access"><span aria-hidden="true"></span>Administrator access</span>
                <p class="admin-home-greeting">Welcome back, {{ $user['name'] }}.</p>
                <h1 id="admin-home-heading">A stronger start.<br><span>A safer tomorrow.</span></h1>
                <p class="admin-description">Bring your learning community together. Set up accounts, organize student blocks, and connect every class with its teacher.</p>
                <div class="admin-home-actions">
                    <a class="button admin-home-primary" href="{{ route('admin.users.create') }}">Create an account <span aria-hidden="true">&rarr;</span></a>
                    <a class="admin-home-secondary" href="{{ route('admin.classes') }}">Manage classes <span aria-hidden="true">&rarr;</span></a>
                </div>
            </div>

            <aside class="admin-home-network" aria-label="How your learning community connects">
                <div class="admin-network-heading"><span class="admin-eyebrow">YOUR LEARNING COMMUNITY</span><span class="admin-network-mark" aria-hidden="true">ZRI</span></div>
                <div class="admin-network-node"><span class="admin-network-icon" aria-hidden="true">01</span><div><h2>Student blocks</h2><p>A place for every learner.</p></div></div>
                <div class="admin-network-connector" aria-hidden="true"></div>
                <div class="admin-network-node"><span class="admin-network-icon" aria-hidden="true">02</span><div><h2>Teacher guidance</h2><p>The right support for each class.</p></div></div>
                <div class="admin-network-connector" aria-hidden="true"></div>
                <div class="admin-network-node is-final"><span class="admin-network-icon" aria-hidden="true">03</span><div><h2>Safer choices</h2><p>Learning through play and progress.</p></div></div>
                <div class="admin-home-blocks">
                    <strong>{{ $blocksUnavailable ? '—' : count($blocks) }}</strong>
                    <span>{{ $blocksUnavailable ? 'Block information temporarily unavailable' : 'Blocks available for student enrollment' }}</span>
                    <a href="{{ route('admin.classes') }}" aria-label="Manage student blocks">&rarr;</a>
                </div>
            </aside>
        </section>

        <section aria-labelledby="admin-tools-heading" class="admin-home-tools">
            <div class="admin-home-section-heading"><div><span class="admin-eyebrow">YOUR WORKSPACE</span><h2 id="admin-tools-heading">What would you like to do?</h2></div><p>Everything you need, one step away.</p></div>
            <div class="admin-home-card-grid">
                <a class="admin-action-card" href="{{ route('admin.users.create') }}">
                    <span class="admin-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M19 7v6M16 10h6"/></svg></span>
                    <span class="admin-action-label">PEOPLE &amp; ACCESS</span><h3>Create teacher accounts</h3>
                    <p>Add teachers with a temporary password and connect them to your classes.</p>
                    <span class="admin-action-link">Create Account <span aria-hidden="true">&rarr;</span></span>
                </a>
                <a class="admin-action-card" href="{{ route('admin.students.import') }}">
                    <span class="admin-action-label">STUDENT ENROLLMENT</span><h3>Import your student list</h3>
                    <p>Upload Fullname, email, and block. Students verify their email and set their own password.</p>
                    <span class="admin-action-link">Import Students <span aria-hidden="true">&rarr;</span></span>
                </a>
                <a class="admin-action-card admin-action-card-orange" href="{{ route('admin.classes') }}">
                    <span class="admin-action-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><path d="M14 6h4v4M6 14v4h4M10 6h4M6 10v4"/></svg></span>
                    <span class="admin-action-label">BLOCKS &amp; CLASSROOMS</span><h3>Manage classes &amp; teacher assignments</h3>
                    <p>Organize blocks, assign teachers, and keep class rosters connected automatically.</p>
                    <span class="admin-action-link">Manage Classes <span aria-hidden="true">&rarr;</span></span>
                </a>
            </div>
        </section>

        <section class="admin-home-guide" aria-labelledby="admin-setup-heading">
            <div class="admin-home-section-heading"><div><span class="admin-eyebrow">A SIMPLE START</span><h2 id="admin-setup-heading">Build your classroom in three steps</h2></div><span class="admin-home-guide-tag">Setup guide</span></div>
            <ol class="admin-setup-steps">
                <li><span class="admin-setup-number" aria-hidden="true">01</span><div><h3>Add your blocks</h3><p>Set up your school's block names to match the student CSV.</p><a href="{{ route('admin.classes') }}">Set up blocks &rarr;</a></div></li>
                <li><span class="admin-setup-number" aria-hidden="true">02</span><div><h3>Welcome your people</h3><p>Create teachers and import student invitations. Students activate through New User.</p><a href="{{ route('admin.students.import') }}">Import students &rarr;</a></div></li>
                <li><span class="admin-setup-number" aria-hidden="true">03</span><div><h3>Connect the classroom</h3><p>Assign a block to a teacher. Its students join the class roster automatically.</p><a href="{{ route('admin.classes') }}">Assign a teacher &rarr;</a></div></li>
            </ol>
        </section>
        <footer class="admin-footer">ZeroRiskInitiative &middot; Better choices. Lower risks.</footer>
    </main>
</div>
@endsection
