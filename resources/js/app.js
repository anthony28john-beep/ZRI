import './theme';
import './home-motion';
import './page-motion';
import './notifications';

window.addEventListener('pageshow', event => {
    if (event.persisted) { window.location.reload(); return; }
    document.querySelectorAll('form[aria-busy="true"]').forEach(form => {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[data-original-label]').forEach(button => {
            button.innerHTML = button.dataset.originalLabel;
            button.disabled = false;
        });
    });
});
document.querySelectorAll('.field-error[id]').forEach(error => {
    const name = error.id.replace(/-error$/, '');
    const field = document.getElementById(name);
    if (!field) return;
    field.setAttribute('aria-invalid', 'true');
    const descriptions = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    descriptions.add(error.id);
    field.setAttribute('aria-describedby', [...descriptions].join(' '));
});
document.querySelector('[data-error-summary]')?.focus();

const navigationDropdowns = document.querySelectorAll('[data-navigation-dropdown]');
const positionMobilePanels = () => {
    const navbar = document.querySelector('.workspace-navbar');
    if (!navbar) return;
    const viewport = window.visualViewport;
    const height = viewport?.height || window.innerHeight;
    const offset = viewport?.offsetTop || 0;
    // On short screens or with the keyboard open, use the available viewport instead of covering it.
    const preferredTop = navbar.getBoundingClientRect().bottom + 8;
    const top = offset + (height < 480 ? 8 : Math.max(8, Math.min(preferredTop - offset, height - 180)));
    navbar.style.setProperty('--workspace-panel-top', `${Math.round(top)}px`);
    navbar.style.setProperty('--workspace-panel-height', `${Math.max(80, Math.floor(height - (top - offset) - 12))}px`);
};
positionMobilePanels();
window.addEventListener('resize', positionMobilePanels);
window.addEventListener('scroll', () => {
    if (document.querySelector('[data-navigation-dropdown][open]')) positionMobilePanels();
}, { passive: true });
window.visualViewport?.addEventListener('resize', positionMobilePanels);
window.visualViewport?.addEventListener('scroll', positionMobilePanels);
if ('ResizeObserver' in window) {
    const navbar = document.querySelector('.workspace-navbar');
    if (navbar) new ResizeObserver(positionMobilePanels).observe(navbar);
}
const updateTableScrolling = () => {
    document.querySelectorAll('.tp-table-wrap, .scoreboard-table-scroll').forEach(region => {
        const overflow = region.scrollWidth > region.clientWidth + 1;
        region.dataset.scrollable = String(overflow);
        if (!overflow && region.dataset.autoTabstop === 'true') {
            region.removeAttribute('tabindex');
            delete region.dataset.autoTabstop;
        }
        if (!overflow) return;
        if (!region.hasAttribute('tabindex')) {
            region.tabIndex = 0;
            region.dataset.autoTabstop = 'true';
        }
        if (!region.hasAttribute('role')) region.setAttribute('role', 'region');
        if (!region.hasAttribute('aria-label')) {
            const heading = region.closest('section')?.querySelector('h2, h3');
            region.setAttribute('aria-label', `${heading?.textContent.trim() || 'Data table'} — scroll horizontally for more columns`);
        }
    });
};
updateTableScrolling();
window.addEventListener('load', updateTableScrolling);
window.addEventListener('resize', updateTableScrolling);
document.addEventListener('toggle', updateTableScrolling, true);
document.addEventListener('click', event => {
    const close = event.target.closest('[data-navigation-close]');
    if (close) {
        const dropdown = close.closest('[data-navigation-dropdown]');
        if (dropdown) { dropdown.open = false; dropdown.querySelector('summary')?.focus(); }
        return;
    }
    navigationDropdowns.forEach(dropdown => {
        if (dropdown.open && !dropdown.contains(event.target)) dropdown.open = false;
    });
});
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    navigationDropdowns.forEach(dropdown => {
        if (!dropdown.open) return;
        const focusInside = dropdown.contains(document.activeElement);
        dropdown.open = false;
        if (focusInside) dropdown.querySelector('summary').focus();
    });
});
navigationDropdowns.forEach(dropdown => {
    dropdown.addEventListener('toggle', () => {
        if (!dropdown.open) return;
        positionMobilePanels();
        navigationDropdowns.forEach(other => { if (other !== dropdown) other.open = false; });
    });
    dropdown.addEventListener('focusout', event => {
        if (!dropdown.contains(event.relatedTarget)) dropdown.open = false;
    });
});

const desktopNavigation = window.matchMedia('(min-width: 1360px)');
desktopNavigation.addEventListener('change', event => {
    if (event.matches) document.querySelectorAll('.workspace-mobile-menu[open]').forEach(menu => { menu.open = false; });
});
document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('button[type="submit"]').forEach(button => {
        button.dataset.originalLabel = button.innerHTML;
        button.disabled = true;
        button.textContent = 'Please wait…';
    });
});
