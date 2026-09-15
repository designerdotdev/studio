<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $template['name'] }} · Templates</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
    <style>
        :root {
            color-scheme: light;
            --ground: #f5f5f3; --panel: #ffffff; --raised: #e9e9e5;
            --line: rgba(22, 22, 20, .08); --line-strong: rgba(22, 22, 20, .18);
            --ink: #161614; --soft: #5c5c57; --faint: #8b8b85; --focus: #3b6ff0;
            --ease: cubic-bezier(.2, .7, .2, 1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --ground: #0c0c0d; --panel: #141416; --raised: #09090a;
                --line: rgba(255, 255, 255, .08); --line-strong: rgba(255, 255, 255, .18);
                --ink: #f3f3f1; --soft: #a3a39d; --faint: #73736e; --focus: #6b93ff;
            }
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        html, body { height: 100%; }
        body { margin: 0; display: grid; grid-template-rows: auto 1fr; background: var(--ground); color: var(--ink); font: 13px/1.4 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; overflow: hidden; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; color: inherit; }
        :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
        .mono { font-family: 'Geist Mono', ui-monospace, 'SF Mono', Menlo, monospace; }

        .bar { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 12px; height: 56px; padding: 0 12px; background: var(--panel); border-bottom: 1px solid var(--line); }
        .side { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .side.end { justify-content: flex-end; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 32px; padding: 0 10px; border: 0; border-radius: 8px; background: transparent; color: var(--soft); cursor: pointer; white-space: nowrap; transition: background .15s ease, color .15s ease; }
        .btn:hover { background: var(--raised); color: var(--ink); }
        .btn.icon { width: 32px; padding: 0; }
        .btn.outline { box-shadow: inset 0 0 0 1px var(--line-strong); color: var(--ink); }
        .sep { width: 1px; height: 20px; background: var(--line-strong); margin: 0 4px; flex: none; }

        .title { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .12); }
        .name { font-size: 14px; font-weight: 600; letter-spacing: -.01em; white-space: nowrap; }
        .facts { color: var(--faint); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .group { display: inline-flex; padding: 3px; gap: 2px; background: var(--raised); border-radius: 10px; }
        .group button { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 10px; border: 0; background: transparent; border-radius: 7px; color: var(--soft); cursor: pointer; transition: color .15s ease, background .15s ease; }
        .group button:hover { color: var(--ink); }
        .group button[aria-pressed="true"] { background: var(--panel); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, .08), 0 0 0 1px var(--line); }

        .step { color: var(--faint); font-variant-numeric: tabular-nums; font-size: 12px; padding: 0 4px; }
        .neighbour { max-width: 150px; }
        .neighbour span { overflow: hidden; text-overflow: ellipsis; }

        .stage { position: relative; display: flex; justify-content: center; min-height: 0; background: var(--raised); padding: 0; transition: padding .35s var(--ease); }
        .stage.framed { padding: 20px; }
        .device { position: relative; width: 100%; height: 100%; background: var(--panel); overflow: hidden; transition: width .35s var(--ease), border-radius .35s var(--ease), box-shadow .35s var(--ease); }
        .stage.framed .device { border-radius: 14px; box-shadow: 0 0 0 1px var(--line-strong), 0 30px 60px -30px rgba(0, 0, 0, .35); }
        .device img.placeholder { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: top; filter: blur(14px) saturate(1.1); scale: 1.06; opacity: .7; transition: opacity .4s ease; }
        .device iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; background: transparent; opacity: 0; transition: opacity .4s ease; }
        .device.ready iframe { opacity: 1; }
        .device.ready img.placeholder { opacity: 0; }
        .loading { position: absolute; left: 50%; top: 50%; translate: -50% -50%; display: inline-flex; align-items: center; gap: 8px; height: 32px; padding: 0 14px; border-radius: 999px; background: rgba(12, 12, 13, .8); color: #fff; font-size: 12px; transition: opacity .3s ease; }
        .device.ready .loading { opacity: 0; pointer-events: none; }
        .spinner { width: 12px; height: 12px; border-radius: 50%; border: 1.5px solid rgba(255, 255, 255, .3); border-top-color: #fff; animation: spin .8s linear infinite; }
        @keyframes spin { to { rotate: 360deg; } }

        @media (max-width: 1100px) {
            .neighbour span, .facts, .step { display: none; }
            .neighbour { max-width: none; }
        }
        @media (max-width: 760px) {
            .bar { grid-template-columns: 1fr auto; }
            .devices { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .stage, .device, .device iframe, .device img.placeholder { transition: none; }
        }
    </style>
</head>
<body>
    <header class="bar">
        <div class="side">
            <a class="btn" href="{{ route('studio.template-gallery.index') }}" title="All templates (Esc)">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 5.5 8l4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Templates
            </a>
            <span class="sep"></span>
            <div class="title">
                @if ($template['accent'])
                    <i class="dot" style="background: {{ $template['accent'] }}"></i>
                @endif
                <span class="name">{{ $template['name'] }}</span>
                <span class="facts mono">{{ collect([$template['category'], $template['theme']])->filter()->implode(' · ') }}</span>
            </div>
        </div>

        <div class="group devices" role="group" aria-label="Viewport width">
            <button type="button" data-width="full" aria-pressed="true" title="Desktop (1)">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1.75" y="2.75" width="12.5" height="8.5" rx="1.25" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 13.75h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                Desktop
            </button>
            <button type="button" data-width="768" aria-pressed="false" title="Tablet (2)">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3.25" y="1.75" width="9.5" height="12.5" rx="1.25" stroke="currentColor" stroke-width="1.4"/></svg>
                Tablet
            </button>
            <button type="button" data-width="390" aria-pressed="false" title="Mobile (3)">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="4.75" y="1.75" width="6.5" height="12.5" rx="1.25" stroke="currentColor" stroke-width="1.4"/></svg>
                Mobile
            </button>
        </div>

        <div class="side end">
            <a class="btn neighbour" href="{{ route('studio.template-gallery.show', $previous['slug']) }}" title="Previous: {{ $previous['name'] }} (←)">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3.5 5.5 8l4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>{{ $previous['name'] }}</span>
            </a>
            <span class="step">{{ $position }} / {{ $count }}</span>
            <a class="btn neighbour" href="{{ route('studio.template-gallery.show', $next['slug']) }}" title="Next: {{ $next['name'] }} (→)">
                <span>{{ $next['name'] }}</span>
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 3.5 10.5 8 6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <span class="sep"></span>
            <button type="button" class="btn icon" id="reload" title="Reload (R)" aria-label="Reload">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13.25 8A5.25 5.25 0 1 1 11.7 4.3M13.25 2.5v3h-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <a class="btn outline" href="{{ route('studio.template-preview.show', $template['slug']) }}" target="_blank" rel="noopener" title="Open full page in a new tab">
                Open
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M4.5 2.5h5v5M9.25 2.75 3 9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
        </div>
    </header>

    <div class="stage" id="stage">
        <div class="device" id="device">
            @if ($template['thumbnail'])
                <img class="placeholder" src="{{ route('studio.template-preview.thumbnail', $template['slug']) }}" alt="">
            @endif
            <span class="loading"><i class="spinner"></i> Loading {{ $template['name'] }}</span>
            <iframe id="frame" src="{{ route('studio.template-preview.show', $template['slug']) }}" title="{{ $template['name'] }} preview"></iframe>
        </div>
    </div>

    <script>
        (() => {
            const KEY = 'studio.template-viewer.width';
            const stage = document.getElementById('stage');
            const device = document.getElementById('device');
            const frame = document.getElementById('frame');
            const buttons = [...document.querySelectorAll('[data-width]')];
            const links = {
                prev: @json(route('studio.template-gallery.show', $previous['slug'])),
                next: @json(route('studio.template-gallery.show', $next['slug'])),
                index: @json(route('studio.template-gallery.index')),
            };

            const setWidth = (value) => {
                if (!buttons.some((b) => b.dataset.width === value)) value = 'full';
                const full = value === 'full';
                stage.classList.toggle('framed', !full);
                device.style.width = full ? '100%' : `min(${value}px, 100%)`;
                buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.width === value)));
                try { localStorage.setItem(KEY, value); } catch (e) {}
            };

            buttons.forEach((b) => b.addEventListener('click', () => setWidth(b.dataset.width)));
            try { setWidth(localStorage.getItem(KEY) || 'full'); } catch (e) { setWidth('full'); }

            frame.addEventListener('load', () => device.classList.add('ready'));

            document.getElementById('reload').addEventListener('click', () => {
                device.classList.remove('ready');
                frame.contentWindow ? frame.contentWindow.location.reload() : (frame.src = frame.src);
            });

            document.addEventListener('keydown', (event) => {
                if (event.metaKey || event.ctrlKey || event.altKey) return;
                const actions = {
                    ArrowLeft: () => location.assign(links.prev),
                    ArrowRight: () => location.assign(links.next),
                    Escape: () => location.assign(links.index),
                    r: () => document.getElementById('reload').click(),
                    1: () => setWidth('full'),
                    2: () => setWidth('768'),
                    3: () => setWidth('390'),
                };
                if (actions[event.key]) {
                    event.preventDefault();
                    actions[event.key]();
                }
            });
        })();
    </script>
</body>
</html>
