@props(['role', 'links', 'pendingApplicationCount' => null, 'applicationNotifications' => [], 'studentNotifications' => []])
@php
    $identity = session('firebase_user', []);
    $fullName = $identity['name'] ?? 'Account';
    $roleLabel = $role === 'admin' ? 'Administrator' : ucfirst($role);
    $accountActive = $role !== 'admin' && request()->routeIs($role.'.account*');
    $initials = collect(preg_split('/\s+/u', trim($fullName)))->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode('');
@endphp
<header class="workspace-navbar">
    <nav class="workspace-navbar-inner {{ in_array($role, ['teacher', 'student']) ? 'workspace-teacher-nav' : '' }}" aria-label="{{ $roleLabel }} navigation">
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
        @if ($role === 'teacher')
            @php($notificationLabel = $pendingApplicationCount === null ? 'Block application notifications unavailable. Open applications to retry.' : ($pendingApplicationCount > 0 ? $pendingApplicationCount.' pending block '.\Illuminate\Support\Str::plural('application', $pendingApplicationCount).'. Review applications.' : 'No pending block applications. View applications.'))
            <details class="workspace-notification-dropdown" data-navigation-dropdown data-application-notifications>
                <summary class="workspace-notifications" aria-label="Open notifications. {{ $notificationLabel }}" title="Notifications">
                    <x-nav-icon name="bell" />
                    @if ($pendingApplicationCount === null || $pendingApplicationCount > 0)<span class="workspace-notification-count" aria-hidden="true">{{ $pendingApplicationCount === null ? '!' : ($pendingApplicationCount > 99 ? '99+' : $pendingApplicationCount) }}</span>@endif
                </summary>
                <section class="workspace-notification-panel" aria-label="Block application notifications">
                    <div class="workspace-notification-heading"><h2>Notifications</h2><span>{{ $pendingApplicationCount === null ? 'Unavailable' : $pendingApplicationCount.' pending' }}</span></div>
                    @if ($pendingApplicationCount === null)
                        <p class="workspace-notification-empty">Notifications are temporarily unavailable. Open Block Applications to try again.</p>
                    @elseif ($pendingApplicationCount === 0)
                        <div class="workspace-notification-empty"><x-nav-icon name="inbox" /><strong>You're all caught up</strong><p>No pending block applications right now.</p></div>
                    @else
                        <p class="workspace-notification-caption">Students waiting for your approval</p>
                        <ul class="workspace-notification-list">
                            @foreach ($applicationNotifications as $notification)
                                <li><a href="{{ route('teacher.applications') }}#application-{{ rawurlencode($notification['id']) }}"><span class="workspace-notification-avatar" aria-hidden="true"><x-nav-icon name="user" /></span><span><strong>{{ $notification['name'] }}</strong><span>wants to join <strong>{{ $notification['block'] }}</strong></span><small>Pending your review</small></span><span class="workspace-notification-dot" aria-hidden="true"></span></a></li>
                            @endforeach
                        </ul>
                        @if ($pendingApplicationCount > count($applicationNotifications))<p class="workspace-notification-caption">Showing the latest {{ count($applicationNotifications) }} of {{ $pendingApplicationCount }} requests.</p>@endif
                    @endif
                    <a class="workspace-notification-footer" href="{{ route('teacher.applications') }}">View all block applications <span aria-hidden="true">&rarr;</span></a>
                </section>
            </details>
        @endif
        @if ($role === 'student')
            <details class="workspace-notification-dropdown" data-navigation-dropdown data-student-notifications>
                <summary class="workspace-notifications" aria-label="Open student notifications. {{ $studentNotifications['count'] ?? 0 }} updates." title="Notifications">
                    <x-nav-icon name="bell" />
                    @if (($studentNotifications['count'] ?? 0) > 0)<span class="workspace-notification-count" aria-hidden="true">{{ $studentNotifications['count'] > 99 ? '99+' : $studentNotifications['count'] }}</span>@elseif (!empty($studentNotifications['unavailable']))<span class="workspace-notification-count" aria-hidden="true">!</span>@endif
                </summary>
                <section class="workspace-notification-panel" aria-label="Your block and level notifications">
                    <div class="workspace-notification-heading"><h2>Notifications</h2><span>{{ $studentNotifications['count'] ?? 0 }} updates</span></div>
                    @foreach ($studentNotifications['unavailable'] ?? [] as $message)<p class="workspace-notification-caption">{{ $message }} Refresh this page to try again.</p>@endforeach
                    @if (!empty($studentNotifications['items']))
                        <p class="workspace-notification-caption">Your recent game activity and block updates</p>
                        <ul class="workspace-notification-list">
                            @foreach ($studentNotifications['items'] as $notification)
                                <li><a href="{{ $notification['url'] }}"><span class="workspace-notification-avatar" aria-hidden="true"><x-nav-icon :name="$notification['icon']" /></span><span><strong>{{ $notification['title'] }}</strong><span>{{ $notification['message'] }}</span>@if (!empty($notification['recorded_at']))<small>{{ $notification['recorded_at'] }}</small>@endif<small>{{ $notification['icon'] === 'trophy' ? 'View scoreboard' : 'View your block' }}</small></span></a></li>
                            @endforeach
                        </ul>
                    @elseif (empty($studentNotifications['unavailable']))
                        <div class="workspace-notification-empty"><x-nav-icon name="inbox" /><strong>No updates yet</strong><p>Your block acceptance and finished game attempts will appear here.</p></div>
                    @endif
                    <p class="workspace-notification-caption">Each finished run has its own update, including replays. Showing your 10 latest updates. Refresh the page for new activity; game updates may take up to 30 seconds.</p>
                    <a class="workspace-notification-footer" href="{{ route('student.scoreboard') }}">View scoreboard <span aria-hidden="true">&rarr;</span></a>
                </section>
            </details>
        @endif
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
                <button type="button" class="workspace-account-link workspace-theme-toggle" role="switch" aria-checked="false" aria-label="Dark mode" data-theme-toggle>
                    <x-nav-icon name="moon" />
                    <span>Dark mode</span>
                    <span class="workspace-theme-state" data-theme-status aria-hidden="true">Off</span>
                    <span class="workspace-theme-switch" aria-hidden="true"><span></span></span>
                </button>
                <form action="{{ route('logout') }}" method="POST" class="{{ $role === 'admin' ? 'admin-nav-logout' : 'portal-logout' }}">
                    @csrf
                    <button type="submit" class="workspace-account-link workspace-signout"><x-nav-icon name="logout" />Log Out</button>
                </form>
            </div>
        </details>
    </nav>
</header>
