@props(['label' => 'Navigation'])

<details {{ $attributes->class(['navigation-dropdown']) }} data-navigation-dropdown>
    <summary class="navigation-toggle" aria-label="{{ $label }}">
        <svg class="navigation-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span>Menu</span>
        <svg class="navigation-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg>
    </summary>
    <div class="navigation-panel">
        <p class="navigation-panel-heading">{{ $label }}</p>
        {{ $slot }}
    </div>
</details>
