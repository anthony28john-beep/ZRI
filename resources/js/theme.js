const themeKey = 'zri-theme';
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
const themeToggles = document.querySelectorAll('[data-theme-toggle]');

const storedTheme = () => {
    try { return localStorage.getItem(themeKey); } catch { return null; }
};

const applyTheme = theme => {
    document.documentElement.dataset.theme = theme;
    themeToggles.forEach(toggle => {
        toggle.setAttribute('aria-checked', String(theme === 'dark'));
        toggle.querySelector('[data-theme-status]').textContent = theme === 'dark' ? 'On' : 'Off';
    });
};

const preferredTheme = () => {
    const saved = storedTheme();
    return saved === 'dark' || saved === 'light' ? saved : (systemTheme.matches ? 'dark' : 'light');
};

applyTheme(document.documentElement.dataset.theme || preferredTheme());
themeToggles.forEach(toggle => {
    toggle.addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(theme);
        try { localStorage.setItem(themeKey, theme); } catch { /* The toggle still works when browser storage is unavailable. */ }
    });
});
systemTheme.addEventListener('change', () => {
    if (!['dark', 'light'].includes(storedTheme())) applyTheme(preferredTheme());
});
window.addEventListener('storage', event => {
    if (event.key === themeKey || event.key === null) applyTheme(preferredTheme());
});
