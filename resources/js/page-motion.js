// Shared motion for the portals and account pages. The student home has its own choreography.
const page = document.body;

if (page && !document.querySelector('[data-home-motion]')) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const selectors = [
        '.tp-heading', '.admin-page-heading', '.scoreboard-heading',
        '.admin-home-intro > *', '.th-intro > *', '.about-intro-copy > *', '.brand-panel > *',
        '.admin-home-network', '.th-overview', '.admin-action-card', '.th-tool',
        '.tp-stat', '.scoreboard-stat', '.tp-panel', '.admin-guide', '.admin-form-panel',
        '.account-card', '.verify-story', '.verify-content', '.form-panel', '.recovery-card',
        '.about-chapter', '.about-chapter-nav', '.about-play-callout',
        '.download-art', '.download-details', '.download-start', '.scoreboard-results',
        '.activation-summary > div', '.enrollment-workflow > li', '.th-class-picker',
        '.th-section-heading', '.admin-home-section-heading', '.admin-home-guide',
    ].join(',');
    const candidates = [...page.querySelectorAll(selectors)].filter(element =>
        !element.closest('.workspace-navbar, [role="alert"], .alert, dialog')
    );
    const candidateSet = new Set(candidates);
    // Reveal the outer panel once, rather than hiding its fields and rows independently.
    const targets = candidates.filter(element => {
        for (let parent = element.parentElement; parent && parent !== page; parent = parent.parentElement) {
            if (candidateSet.has(parent)) return false;
        }
        return true;
    });
    const cards = page.querySelectorAll('.admin-action-card, .th-tool, .tp-stat, .scoreboard-stat, .scoreboard-leaderboard');
    page.querySelectorAll('.th-primary > span[aria-hidden], .admin-home-primary > span[aria-hidden], .about-play-button > span[aria-hidden]').forEach(arrow => {
        if (/^[→↗]$/.test(arrow.textContent.trim())) arrow.setAttribute('data-motion-arrow', '');
    });
    const ambient = [...page.querySelectorAll('.about-intro-emblem svg, .verify-art svg, .download-art > svg, .admin-network-icon')];
    let observer;
    let ambientObserver;

    const show = element => {
        element.classList.remove('page-reveal-pending');
        observer?.unobserve(element);
        // Table rows fade in only when their containing panel is visible, without moving controls.
        element.querySelectorAll('tbody').forEach(body => {
            body.querySelectorAll('tr').forEach((row, index) => row.style.setProperty('--row-delay', `${Math.min(index, 5) * 35}ms`));
            body.classList.add('page-rows-visible');
        });
    };
    const setup = () => {
        observer?.disconnect();
        ambientObserver?.disconnect();
        targets.forEach(target => target.classList.remove('page-reveal-pending'));
        page.classList.remove('page-motion-ready');
        page.querySelectorAll('.page-rows-visible').forEach(body => body.classList.remove('page-rows-visible'));
        if (reducedMotion.matches) return;
        page.classList.add('page-motion-ready');
        cards.forEach(card => card.setAttribute('data-page-card', ''));
        if (!('IntersectionObserver' in window)) return;
        observer = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting) show(entry.target); });
        }, { threshold: 0.01 });
        targets.forEach((target, index) => {
            target.setAttribute('data-page-reveal', '');
            target.style.setProperty('--page-delay', `${(index % 4) * 65}ms`);
            if (target.matches('.admin-home-network, .th-overview, .download-details')) target.setAttribute('data-page-direction', 'right');
            else if (target.closest('.brand-panel, .admin-home-intro, .th-intro, .about-intro-copy')) target.setAttribute('data-page-direction', 'left');
            target.classList.add('page-reveal-pending');
            observer.observe(target);
        });
        ambientObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => { entry.target.dataset.inView = String(entry.isIntersecting); });
        });
        ambient.forEach(element => { element.setAttribute('data-page-ambient', ''); ambientObserver.observe(element); });
    };
    page.addEventListener('focusin', event => {
        let element = event.target;
        while (element && element !== page) {
            if (element.classList.contains('page-reveal-pending')) show(element);
            element = element.parentElement;
        }
    });
    const setVisibility = () => { page.dataset.motionVisible = String(!document.hidden); };
    document.addEventListener('visibilitychange', setVisibility);
    reducedMotion.addEventListener('change', setup);
    setVisibility();
    setup();
}
