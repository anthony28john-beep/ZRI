import './theme';
import './home-motion';
import './page-motion';

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
    const top = Math.max(12, navbar.getBoundingClientRect().bottom + 8);
    navbar.style.setProperty('--workspace-panel-top', `${Math.round(top)}px`);
};
positionMobilePanels();
window.addEventListener('resize', positionMobilePanels);
const updateTableScrolling = () => {
    document.querySelectorAll('.tp-table-wrap, .scoreboard-table-scroll').forEach(region => {
        const overflow = region.scrollWidth > region.clientWidth + 1;
        region.dataset.scrollable = String(overflow);
        if (!overflow || region.hasAttribute('tabindex')) return;
        region.tabIndex = 0;
        region.setAttribute('role', 'region');
        if (!region.hasAttribute('aria-label')) {
            const heading = region.closest('section')?.querySelector('h2, h3');
            region.setAttribute('aria-label', `${heading?.textContent.trim() || 'Data table'} — scroll horizontally for more columns`);
        }
    });
};
updateTableScrolling();
window.addEventListener('load', updateTableScrolling);
window.addEventListener('resize', updateTableScrolling);
document.addEventListener('click', event => {
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
