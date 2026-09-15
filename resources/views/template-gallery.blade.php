<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Templates</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
    <style>
        :root {
            color-scheme: light;
            --ground: #f5f5f3; --panel: #ffffff; --raised: #ebebe7;
            --line: rgba(22, 22, 20, .08); --line-strong: rgba(22, 22, 20, .18);
            --ink: #161614; --soft: #5c5c57; --faint: #8b8b85;
            --focus: #3b6ff0; --ok: #0b8a5f; --warn: #9a6207; --danger: #c63d3d; --info: #3b6ff0;
            --ease: cubic-bezier(.2, .7, .2, 1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --ground: #0c0c0d; --panel: #141416; --raised: #1c1c1f;
                --line: rgba(255, 255, 255, .08); --line-strong: rgba(255, 255, 255, .18);
                --ink: #f3f3f1; --soft: #a3a39d; --faint: #73736e;
                --focus: #6b93ff; --ok: #34d399; --warn: #fbbf24; --danger: #f37272; --info: #7ba0ff;
            }
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; background: var(--ground); color: var(--ink); font: 14px/1.5 'Geist', ui-sans-serif, system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        /* Scoped to the gallery so a host's site header (partials hook below) keeps its own link and button styles. */
        .intro a, .toolbar a, main a { color: inherit; text-decoration: none; }
        .toolbar button, .toolbar input, main button { font: inherit; color: inherit; }
        :focus-visible { outline: 2px solid var(--focus); outline-offset: 3px; }
        .mono { font-family: 'Geist Mono', ui-monospace, 'SF Mono', Menlo, monospace; }

        .wrap { max-width: 1560px; margin: 0 auto; padding: 0 32px; }

        .intro { padding-top: 56px; padding-bottom: 28px; }
        .eyebrow { font-size: 12px; color: var(--faint); letter-spacing: .02em; }
        h1 { margin: 10px 0 0; font-size: clamp(36px, 5vw, 56px); line-height: 1; font-weight: 600; letter-spacing: -.035em; }
        h1 sup { font-size: .32em; font-weight: 500; color: var(--faint); letter-spacing: 0; margin-left: 8px; vertical-align: top; position: relative; top: .35em; font-variant-numeric: tabular-nums; }
        .lede { margin: 14px 0 0; max-width: 56ch; color: var(--soft); font-size: 15px; }

        .toolbar { position: sticky; top: 0; z-index: 5; background: color-mix(in srgb, var(--ground) 86%, transparent); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid transparent; transition: border-color .2s ease; }
        .toolbar.stuck { border-bottom-color: var(--line); }
        .toolbar .wrap { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding-top: 12px; padding-bottom: 12px; }

        .search { position: relative; flex: 0 1 300px; min-width: 200px; }
        .search svg { position: absolute; left: 11px; top: 50%; translate: 0 -50%; color: var(--faint); pointer-events: none; }
        .search input { width: 100%; height: 36px; padding: 0 38px 0 34px; background: var(--panel); border: 1px solid var(--line-strong); border-radius: 9px; outline: none; transition: border-color .15s ease, box-shadow .15s ease; }
        .search input::placeholder { color: var(--faint); }
        .search input:focus { border-color: var(--focus); box-shadow: 0 0 0 3px color-mix(in srgb, var(--focus) 18%, transparent); }
        .search kbd { position: absolute; right: 9px; top: 50%; translate: 0 -50%; font: 500 11px 'Geist Mono', ui-monospace, monospace; color: var(--faint); border: 1px solid var(--line-strong); border-radius: 5px; padding: 0 5px; line-height: 17px; }

        .group { display: inline-flex; padding: 3px; gap: 2px; background: var(--raised); border-radius: 10px; }
        .group button { height: 30px; padding: 0 12px; border: 0; background: transparent; border-radius: 7px; color: var(--soft); cursor: pointer; text-transform: capitalize; white-space: nowrap; transition: color .15s ease, background .15s ease; }
        .group button:hover { color: var(--ink); }
        .group button[aria-pressed="true"] { background: var(--panel); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, .08), 0 0 0 1px var(--line); }

        .shown { margin-left: auto; color: var(--faint); font-size: 13px; font-variant-numeric: tabular-nums; }

        /* main.wrap, not main: .wrap's padding shorthand would otherwise zero the top gap and the sticky toolbar would cover the first row's top edge. */
        main.wrap { padding-top: 28px; padding-bottom: 96px; }
        .cards { display: grid; gap: 40px 28px; grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr)); }

        .card { display: block; opacity: 0; translate: 0 12px; animation: rise .6s var(--ease) forwards; animation-delay: calc(var(--i) * 30ms); }
        @keyframes rise { to { opacity: 1; translate: 0 0; } }

        .frame { position: relative; aspect-ratio: 16 / 10; border-radius: 12px; overflow: hidden; background: var(--raised); box-shadow: 0 0 0 1px var(--line), 0 1px 2px rgba(0, 0, 0, .04); transition: box-shadow .3s var(--ease), translate .3s var(--ease); }
        .frame img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: top; transition: scale .6s var(--ease); }
        .frame.empty { display: grid; place-items: center; color: var(--faint); font-size: 13px; }
        .frame .open { position: absolute; right: 12px; bottom: 12px; display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 999px; background: rgba(12, 12, 13, .86); color: #fff; font-size: 13px; font-weight: 500; opacity: 0; translate: 0 6px; transition: opacity .25s var(--ease), translate .25s var(--ease); backdrop-filter: blur(6px); }
        .card:hover .frame, .card:focus-visible .frame { translate: 0 -3px; box-shadow: 0 0 0 1px var(--line-strong), 0 18px 40px -18px rgba(0, 0, 0, .35); }
        .card:hover .frame img { scale: 1.025; }
        .card:hover .open, .card:focus-visible .open { opacity: 1; translate: 0 0; }
        .card:focus-visible { outline: none; }
        .card:focus-visible .frame { outline: 2px solid var(--focus); outline-offset: 3px; }

        .meta { padding: 14px 2px 0; }
        .row { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .12); }
        .name { font-size: 16px; font-weight: 600; letter-spacing: -.015em; }
        .status { margin-left: auto; font-size: 11px; line-height: 18px; padding: 0 8px; border-radius: 999px; border: 1px solid color-mix(in srgb, currentColor 40%, transparent); color: var(--faint); }
        .status.review { color: var(--info); }
        .status.revise, .status.building { color: var(--warn); }
        .status.killed { color: var(--danger); }
        .status.shipped { color: var(--ok); }
        .facts { margin-top: 3px; font-size: 12px; color: var(--faint); }
        .description { margin: 8px 0 0; color: var(--soft); font-size: 13.5px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .none { padding: 96px 0; text-align: center; color: var(--soft); }
        .none button { margin-top: 12px; height: 32px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--line-strong); background: var(--panel); cursor: pointer; }

        @media (max-width: 640px) {
            .wrap { padding: 0 18px; }
            .intro { padding-top: 40px; }
            .search { flex-basis: 100%; }
            .shown { display: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            .card { animation: none; opacity: 1; translate: none; }
            .frame, .frame img, .frame .open { transition: none; }
        }
    </style>
    {{-- Host hooks: an app can add its own head tags and a site header above the gallery (resources/views/vendor/studio/partials/…). --}}
    @includeIf('studio::partials.template-gallery-head')
</head>
<body>
    @includeIf('studio::partials.template-gallery-header')

    <header class="wrap intro">
        <div class="eyebrow mono">Designer Studio · local preview</div>
        <h1>Templates<sup>{{ count($templates) }}</sup></h1>
        <p class="lede">Every template, rendered live from its working tree. Pick one to open it in the viewer.</p>
    </header>

    <div class="toolbar" id="toolbar">
        <div class="wrap">
            <label class="search">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="4.75" stroke="currentColor" stroke-width="1.5"/><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <input id="q" type="search" placeholder="Search templates" autocomplete="off" aria-label="Search templates">
                <kbd>/</kbd>
            </label>

            @if (count($categories) > 1)
                <div class="group" role="group" aria-label="Category" data-filter="category">
                    <button type="button" data-value="all" aria-pressed="true">All</button>
                    @foreach ($categories as $category)
                        <button type="button" data-value="{{ $category }}" aria-pressed="false">{{ $category }}</button>
                    @endforeach
                </div>
            @endif

            <div class="group" role="group" aria-label="Theme" data-filter="theme">
                <button type="button" data-value="all" aria-pressed="true">Any theme</button>
                <button type="button" data-value="light" aria-pressed="false">Light</button>
                <button type="button" data-value="dark" aria-pressed="false">Dark</button>
            </div>

            <span class="shown" id="shown">{{ count($templates) }} shown</span>
        </div>
    </div>

    <main class="wrap">
        <div class="cards" id="grid">
            @foreach ($templates as $template)
                <a class="card"
                   href="{{ route('studio.template-gallery.show', $template['slug']) }}"
                   style="--i: {{ min($loop->index, 16) }}"
                   data-card
                   data-category="{{ $template['category'] }}"
                   data-theme="{{ $template['theme'] }}"
                   data-search="{{ Str::lower($template['name'] . ' ' . $template['slug'] . ' ' . $template['category'] . ' ' . $template['description']) }}">
                    @if ($template['thumbnail'])
                        <div class="frame">
                            <img src="{{ route('studio.template-preview.thumbnail', $template['slug']) }}" alt="" loading="lazy" decoding="async" width="1440" height="900">
                            <span class="open">View template <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 6h7m-3-3 3 3-3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </div>
                    @else
                        <div class="frame empty">No thumbnail yet</div>
                    @endif
                    <div class="meta">
                        <div class="row">
                            @if ($template['accent'])
                                <i class="dot" style="background: {{ $template['accent'] }}"></i>
                            @endif
                            <span class="name">{{ $template['name'] }}</span>
                            @if ($template['status'])
                                <span class="status {{ $template['status'] }}">{{ $template['status'] }}</span>
                            @endif
                        </div>
                        <div class="facts mono">
                            {{ collect([$template['category'], $template['theme'], count($template['pages']) ? count($template['pages']) . ' ' . Str::plural('page', count($template['pages'])) : null])->filter()->implode(' · ') }}
                        </div>
                        @if ($template['description'])
                            <p class="description">{{ $template['description'] }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="none" id="none" hidden>
            <div>No templates match those filters.</div>
            <button type="button" id="reset">Clear filters</button>
        </div>
    </main>

    <script>
        (() => {
            const KEY = 'studio.template-gallery';
            const cards = [...document.querySelectorAll('[data-card]')];
            const q = document.getElementById('q');
            const shown = document.getElementById('shown');
            const none = document.getElementById('none');
            const groups = [...document.querySelectorAll('[data-filter]')];
            let state = { q: '', category: 'all', theme: 'all' };

            try { state = { ...state, ...JSON.parse(sessionStorage.getItem(KEY) || '{}') }; } catch (e) {}

            const apply = () => {
                const needle = state.q.trim().toLowerCase();
                let count = 0;

                for (const card of cards) {
                    const match = (state.category === 'all' || card.dataset.category === state.category)
                        && (state.theme === 'all' || card.dataset.theme === state.theme)
                        && (!needle || card.dataset.search.includes(needle));
                    card.hidden = !match;
                    count += match ? 1 : 0;
                }

                for (const group of groups) {
                    const value = state[group.dataset.filter];
                    const known = group.querySelector(`[data-value="${CSS.escape(value)}"]`);
                    if (!known) state[group.dataset.filter] = 'all';
                    for (const button of group.querySelectorAll('button')) {
                        button.setAttribute('aria-pressed', String(button.dataset.value === state[group.dataset.filter]));
                    }
                }

                q.value = state.q;
                shown.textContent = `${count} shown`;
                none.hidden = count > 0;
                try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
            };

            q.addEventListener('input', () => { state.q = q.value; apply(); });

            for (const group of groups) {
                group.addEventListener('click', (event) => {
                    const button = event.target.closest('button');
                    if (!button) return;
                    state[group.dataset.filter] = button.dataset.value;
                    apply();
                });
            }

            document.getElementById('reset').addEventListener('click', () => {
                state = { q: '', category: 'all', theme: 'all' };
                apply();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === '/' && document.activeElement !== q) {
                    event.preventDefault();
                    q.focus();
                } else if (event.key === 'Escape' && document.activeElement === q) {
                    state.q = '';
                    apply();
                    q.blur();
                }
            });

            const toolbar = document.getElementById('toolbar');
            new IntersectionObserver(([entry]) => toolbar.classList.toggle('stuck', !entry.isIntersecting))
                .observe(document.querySelector('.intro'));

            apply();
        })();
    </script>
</body>
</html>
