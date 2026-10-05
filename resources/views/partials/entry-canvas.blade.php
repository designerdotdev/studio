    {{-- A collection's page on the editor's canvas (?entry=): the page as the
         draft preview renders it, looked at rather than edited. The editor
         is told what the canvas would tell it — a press (so a flyout over
         the canvas closes) and a followed link (so the page switcher, the
         panel and the canvas move together). --}}
    <script>
        (function () {
            if (window.parent === window) return;

            const post = (type, payload = {}) => window.parent.postMessage({ type, ...payload }, window.location.origin);

            document.addEventListener('pointerdown', () => post('studio:canvas-down'), true);

            document.addEventListener('click', (event) => {
                const link = event.target.closest && event.target.closest('a[href]');
                if (!link) return;

                const href = link.getAttribute('href');
                if (!href || href.startsWith('#')) return;

                let url;
                try {
                    url = new URL(href, window.location.href);
                } catch (e) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                // Anything off-site opens where it belongs — a new tab
                if (url.origin !== window.location.origin) {
                    window.open(url.href, '_blank', 'noopener');
                    return;
                }

                post('studio:navigate', { path: url.pathname });
            }, true);

            document.addEventListener('submit', (event) => event.preventDefault(), true);
        })();
    </script>
