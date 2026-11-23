const home = document.querySelector('[data-home-motion]');

if (home) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    const hero = home.querySelector('.game-hero');
    const menu = home.querySelector('.game-ambient-menu');
    const targets = [...home.querySelectorAll('[data-home-reveal]')];
    let revealObserver;
    let heroObserver;
    let frame = 0;
    let x = 0;
    let y = 0;
    let heroVisible = true;

    const resetParallax = () => {
        cancelAnimationFrame(frame);
        frame = 0;
        menu?.style.removeProperty('--menu-x');
        menu?.style.removeProperty('--menu-y');
    };
    const setAmbientState = () => {
        home.dataset.heroActive = String(heroVisible && !document.hidden && !reducedMotion.matches);
    };
    const setup = () => {
        revealObserver?.disconnect();
        heroObserver?.disconnect();
        resetParallax();
        targets.forEach(target => target.classList.remove('home-reveal-pending'));
        home.classList.remove('home-motion-ready');
        setAmbientState();
        if (reducedMotion.matches) return;
        home.classList.add('home-motion-ready');
        if (!('IntersectionObserver' in window)) return;
        revealObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.remove('home-reveal-pending');
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.12 });
        targets.forEach(target => { target.classList.add('home-reveal-pending'); revealObserver.observe(target); });
        // Reveal focused content immediately, including cards reached with the keyboard.
        heroObserver = new IntersectionObserver(([entry]) => {
            heroVisible = entry.isIntersecting;
            setAmbientState();
        });
        heroObserver.observe(hero);
    };

    hero?.addEventListener('pointermove', event => {
        if (reducedMotion.matches || !finePointer.matches || !heroVisible || document.hidden) return;
        const bounds = hero.getBoundingClientRect();
        x = ((event.clientX - bounds.left) / bounds.width - 0.5) * 14;
        y = ((event.clientY - bounds.top) / bounds.height - 0.5) * 10;
        if (frame) return;
        frame = requestAnimationFrame(() => {
            menu?.style.setProperty('--menu-x', `${x.toFixed(2)}px`);
            menu?.style.setProperty('--menu-y', `${y.toFixed(2)}px`);
            frame = 0;
        });
    }, { passive: true });
    hero?.addEventListener('pointerleave', resetParallax);
    home.addEventListener('focusin', event => event.target.closest('[data-home-reveal]')?.classList.remove('home-reveal-pending'));
    document.addEventListener('visibilitychange', () => { setAmbientState(); if (document.hidden) resetParallax(); });
    reducedMotion.addEventListener('change', setup);
    finePointer.addEventListener('change', resetParallax);
    setup();
}
