<header class="admin-topbar admin-navigation">
    <nav class="admin-container admin-topbar-inner" aria-label="Admin navigation">
        <a class="admin-brand" href="{{ route('dashboard') }}" aria-label="ZeroRiskInitiative admin home">
            <span class="admin-brand-mark" aria-hidden="true">
                <svg width="25" height="28" viewBox="0 0 24 26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2 21 6v7c0 5-4 8-9 11-5-3-9-6-9-11V6l9-4Z"/>
                    <path d="m8 13 3 3 5-6"/>
                </svg>
            </span>
            <span><span class="admin-brand-name">ZeroRisk<span>Initiative</span></span><span class="admin-brand-caption">Administration workspace</span></span>
        </a>
        <div class="admin-nav-links">
            @foreach (['dashboard' => 'Home', 'admin.users.create' => 'Create Account', 'admin.users.index' => 'Manage Accounts', 'admin.classes' => 'Manage Classes'] as $destination => $label)
                @php($active = request()->routeIs($destination) || ($destination === 'admin.users.index' && request()->routeIs('admin.users.delete')))
                <a href="{{ route($destination) }}" class="admin-nav-link {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <form action="{{ route('logout') }}" method="POST" class="admin-nav-logout">
            @csrf
            <button type="submit" class="button">Log Out <span aria-hidden="true">&rarr;</span></button>
        </form>
    </nav>
</header>
