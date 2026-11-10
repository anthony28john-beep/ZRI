@props(['name' => 'home'])
<svg {{ $attributes->class(['workspace-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home') <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/> @break
        @case('download') <path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/> @break
        @case('book') <path d="M12 5v16m0-16C9 3 5 3 3 4v15c3-1 6-1 9 2 3-3 6-3 9-2V4c-2-1-6-1-9 1Z"/> @break
        @case('trophy') <path d="M8 3h8v7a4 4 0 0 1-8 0V3ZM8 5H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4m-4 2v6m-4 1h8"/> @break
        @case('users') <circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m2 5a5 5 0 0 1 3 5v1"/> @break
        @case('chart') <path d="M4 3v18h17M8 16v-4m5 4V8m5 8V5"/> @break
        @case('inbox') <path d="M4 4h16l2 12v4H2v-4L4 4ZM2 16h6l2 3h4l2-3h6"/> @break
        @case('add-user') <circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M19 6v6m-3-3h6"/> @break
        @case('upload') <path d="M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5"/> @break
        @case('classes') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><path d="M14 6h4v4M6 14v4h4M10 6h4M6 10v4"/> @break
        @case('user') <circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/> @break
        @case('logout') <path d="M9 4H4v16h5m5-12 4 4-4 4M9 12h12"/> @break
    @endswitch
</svg>
