document.querySelectorAll('[data-notification-url]').forEach(dropdown => {
    let loading = false;
    let loadedAt = 0;
    const load = async (force = false) => {
        if (loading || (!force && Date.now() - loadedAt < 30000)) return;
        // Avoid replacing a link while someone is navigating the open panel by keyboard.
        const panel = dropdown.querySelector('.workspace-notification-panel');
        if (!force && panel?.contains(document.activeElement)) return;
        loading = true;
        dropdown.setAttribute('aria-busy', 'true');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(dropdown.dataset.notificationUrl, {
                credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Notifications unavailable');
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Invalid notification response');
            const restoreFocus = dropdown.contains(document.activeElement);
            const wasOpen = dropdown.open;
            // Same-origin, authenticated Blade markup escapes all notification content.
            dropdown.innerHTML = data.html;
            dropdown.open = wasOpen;
            if (restoreFocus) dropdown.querySelector('summary')?.focus();
            if (data.role === 'teacher') {
                document.querySelectorAll('[data-application-count]').forEach(badge => {
                    badge.hidden = !(data.count > 0);
                    badge.textContent = data.count > 99 ? '99+' : String(data.count ?? '');
                    badge.setAttribute('aria-label', `${data.count ?? 0} pending applications`);
                });
            }
            loadedAt = Date.now();
        } catch {
            const status = dropdown.querySelector('[data-notification-status]') || document.createElement('p');
            status.className = 'workspace-notification-caption';
            status.dataset.notificationStatus = '';
            status.setAttribute('role', 'status');
            status.textContent = 'Updates could not be loaded. You can retry or use the link below.';
            if (!status.isConnected) dropdown.querySelector('.workspace-notification-panel')?.prepend(status);
            if (!dropdown.querySelector('[data-notification-retry]')) {
                const retry = document.createElement('button');
                retry.type = 'button'; retry.className = 'text-button'; retry.dataset.notificationRetry = '';
                retry.textContent = 'Retry notifications';
                status.after(retry);
            }
        } finally {
            clearTimeout(timeout);
            dropdown.removeAttribute('aria-busy');
            loading = false;
        }
    };
    dropdown.addEventListener('toggle', () => { if (dropdown.open) load(); });
    dropdown.addEventListener('click', event => { if (event.target.closest('[data-notification-retry]')) load(true); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
    const schedule = () => {
        if ('requestIdleCallback' in window) window.requestIdleCallback(() => load(), { timeout: 1500 });
        else setTimeout(() => load(), 0);
    };
    if (document.readyState === 'complete') schedule();
    else window.addEventListener('load', schedule, { once: true });
});
