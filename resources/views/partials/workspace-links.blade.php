@foreach ($links as $link)
    <a href="{{ $link['url'] }}" class="{{ $role === 'admin' ? 'admin-nav-link' : 'portal-nav-link' }} {{ $link['active'] ? ($role === 'admin' ? 'is-active' : 'active') : '' }}" @if ($link['active']) aria-current="page" @endif @if ($link['icon'] === 'download') data-download-link @endif>
        <x-nav-icon :name="$link['icon']" />
        <span>{{ $link['label'] }}</span>
        @if ($role === 'teacher' && $link['icon'] === 'inbox')<span class="workspace-count" data-application-count hidden></span>@endif
    </a>
@endforeach
