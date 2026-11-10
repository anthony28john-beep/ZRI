<script>
    (() => {
        let saved;
        try { saved = localStorage.getItem('zri-theme'); } catch (_) {}
        const theme = saved === 'dark' || saved === 'light'
            ? saved : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.dataset.theme = theme;
    })();
</script>
