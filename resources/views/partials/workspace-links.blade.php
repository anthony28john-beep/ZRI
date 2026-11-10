@foreach ($links as $link)
    <a href="{{ $link['url'] }}" class="{{ $role === 'admin' ? 'admin-nav-link' : 'portal-nav-link' }} {{ $link['active'] ? ($role === 'admin' ? 'is-active' : 'active') : '' }}" @if ($link['active']) aria-current="page" @endif @if ($link['icon'] === 'download') data-download-link @endif>
        <x-nav-icon :name="$link['icon']" />
        <span>{{ $link['label'] }}</span>
        @if (($link['count'] ?? 0) > 0)<span class="workspace-count" aria-label="{{ $link['count'] }} pending applications">{{ $link['count'] }}</span>@endif
    </a>
@endforeach
