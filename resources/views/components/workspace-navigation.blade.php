@props(['role', 'links'])
@php
    $identity = session('firebase_user', []);
    $fullName = $identity['name'] ?? 'Account';
    $roleLabel = $role === 'admin' ? 'Administrator' : ucfirst($role);
    $accountActive = $role !== 'admin' && request()->routeIs($role.'.account*');
    $initials = collect(preg_split('/\s+/u', trim($fullName)))->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode('');
@endphp
<header class="workspace-navbar">
    <nav class="workspace-navbar-inner" aria-label="{{ $roleLabel }} navigation">
        <a class="workspace-brand" href="{{ route('dashboard') }}" aria-label="ZeroRiskInitiative {{ strtolower($roleLabel) }} home">
            <span>ZeroRisk<span>Initiative</span></span>
            <small>{{ $roleLabel }} workspace</small>
        </a>
        <div class="workspace-desktop-links workspace-links">
            @include('partials.workspace-links')
        </div>
        <x-navigation-menu :label="$roleLabel.' navigation'" class="workspace-mobile-menu">
            <div class="workspace-links">@include('partials.workspace-links')</div>
        </x-navigation-menu>
        <details class="workspace-account" data-navigation-dropdown>
            <summary class="workspace-account-toggle {{ $accountActive ? 'is-active' : '' }}" aria-label="Open account menu for {{ $fullName }}">
                <span class="workspace-avatar" aria-hidden="true">{{ $initials }}</span>
                <span class="workspace-account-name"><strong>{{ $fullName }}</strong><small>{{ $roleLabel }}</small></span>
                <svg class="navigation-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
            </summary>
            <div class="workspace-account-panel">
                <div class="workspace-account-heading"><strong>{{ $fullName }}</strong><span>{{ $roleLabel }} account</span></div>
                @if ($role !== 'admin')
                    <a class="workspace-account-link {{ $accountActive ? 'is-active' : '' }}" href="{{ route($role.'.account') }}" @if ($accountActive) aria-current="page" @endif><x-nav-icon name="user" />Account Information</a>
                @endif
                <form action="{{ route('logout') }}" method="POST" class="{{ $role === 'admin' ? 'admin-nav-logout' : 'portal-logout' }}">
                    @csrf
                    <button type="submit" class="workspace-account-link workspace-signout"><x-nav-icon name="logout" />Log Out</button>
                </form>
            </div>
        </details>
    </nav>
</header>
