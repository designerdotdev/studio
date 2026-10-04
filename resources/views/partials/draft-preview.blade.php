    {{-- Draft preview mode: internal links stay inside the preview, and a
         badge makes the parallel-universe nature of this page obvious --}}
    <script>
        (function () {
            const base = @json(parse_url(route('studio.preview.home'), PHP_URL_PATH));
            const studioPath = @json('/' . trim(config('studio.path', 'studio'), '/'));

            const rewrite = (href) => {
                if (!href || !href.startsWith('/') || href.startsWith('//')) return null;
                if (href === studioPath || href.startsWith(studioPath + '/')) return null;

                return href === '/' ? base : base + href;
            };

            // Rewrite in place so hover previews, cmd+click, and
            // middle-click all stay inside the draft preview too
            document.querySelectorAll('a[href]').forEach((a) => {
                const to = rewrite(a.getAttribute('href'));
                if (to) a.setAttribute('href', to);
            });

            // Safety net for links injected after load
            document.addEventListener('click', (event) => {
                const link = event.target.closest && event.target.closest('a[href]');
                if (!link) return;

                const to = rewrite(link.getAttribute('href'));
                if (to) {
                    event.preventDefault();
                    window.location.href = to;
                }
            });
        })();
    </script>

    <div style="position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 2147483000; display: flex; align-items: center; gap: 10px; padding: 8px 8px 8px 14px; border-radius: 999px; background: rgba(11, 11, 13, 0.92); border: 1px solid rgba(255, 255, 255, 0.14); box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.55); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); font-family: ui-sans-serif, system-ui, sans-serif; font-size: 12.5px; font-weight: 500; color: rgba(244, 244, 246, 0.92); white-space: nowrap;">
        <span style="display: inline-flex; align-items: center; gap: 7px;">
            <span style="width: 7px; height: 7px; border-radius: 999px; background: #fbbf24;"></span>
            Draft preview
        </span>
        <a href="{{ route('studio.index', isset($page) ? ['page' => $page->slug] : []) }}" style="display: inline-flex; align-items: center; height: 26px; padding: 0 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); color: #fff; text-decoration: none; font-weight: 600; transition: background 120ms ease;" onmouseover="this.style.background='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
            {{ isset($page) ? 'Edit page' : 'Open editor' }}
        </a>
    </div>
