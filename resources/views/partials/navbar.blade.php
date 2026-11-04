<nav class="portal-navbar {{ in_array($portalRole, ['student', 'teacher']) ? 'student-navigation' : '' }}" aria-label="{{ ucfirst($portalRole) }} navigation">
    <div class="portal-navbar-inner">
        <a href="{{ route('dashboard') }}" class="portal-brand">
            ZeroRisk<span>Initiative</span>
        </a>

        <div class="portal-nav-links">
        @if ($portalRole === 'student')
            <a href="{{ route('dashboard') }}" class="portal-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>
                Home
            </a>
            <a href="{{ route('student.play-game') }}" class="portal-nav-link {{ request()->routeIs('student.play-game') ? 'active' : '' }}" @if (request()->routeIs('student.play-game')) aria-current="page" @endif>
                Play Game
            </a>
            <a href="{{ route('student.about-game') }}" class="portal-nav-link {{ request()->routeIs('student.about-game') ? 'active' : '' }}" @if (request()->routeIs('student.about-game')) aria-current="page" @endif>
                About the Game
            </a>
            <a href="{{ route('student.scoreboard') }}" class="portal-nav-link {{ request()->routeIs('student.scoreboard') ? 'active' : '' }}" @if (request()->routeIs('student.scoreboard')) aria-current="page" @endif>
                Scoreboard
            </a>
        @endif
        @if ($portalRole === 'teacher')
            @foreach (['home' => 'Home', 'students' => 'Students', 'progress' => 'Progress', 'leaderboard' => 'Leaderboard', 'applications' => 'Block Applications'] as $section => $label)
                <a href="{{ route('teacher.'.$section, request()->only('class')) }}" class="portal-nav-link {{ request()->routeIs('teacher.'.$section, 'teacher.'.$section.'.*') ? 'active' : '' }}" @if (request()->routeIs('teacher.'.$section, 'teacher.'.$section.'.*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        @endif
        <a
            href="{{ route($portalRole.'.account') }}"
            class="portal-nav-link
                {{ request()->routeIs($portalRole.'.account*') ? 'active' : '' }}"
            @if (request()->routeIs($portalRole.'.account*')) aria-current="page" @endif
        >
            Account Information
        </a>
        </div>
        @if (in_array($portalRole, ['student', 'teacher']))
            <form action="{{ route('logout') }}" method="POST" class="portal-logout">
                @csrf
                <button type="submit" class="button portal-logout-button">Log Out</button>
            </form>
        @endif
    </div>
</nav>
