<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? 'Designer Studio' }}</title>

        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230b0b0d'/%3E%3Cg transform='translate(7.4 7.3) scale(0.24)'%3E%3Cpath fill='white' fill-rule='evenodd' d='M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z' clip-rule='evenodd'/%3E%3C/g%3E%3C/svg%3E">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">

        <script>
            // Apply the saved editor theme before first paint (dark is the default)
            if (localStorage.getItem('studio.theme') === 'light') document.documentElement.classList.add('studio-light');
        </script>
        @isset($inspector)
            {{-- The editor boots hidden: the columns are laid out at once
                 but stay invisible until Alpine is up, the fonts are in and
                 the canvas document has loaded, then fade in together.
                 Inline, so it holds from first paint; a timeout reveals
                 regardless, so nothing can stay hidden. --}}
            <style>
                html.studio-booting .s-topbar,
                html.studio-booting .s-rail,
                html.studio-booting .s-inspector,
                html.studio-booting .s-stage {
                    visibility: hidden;
                    opacity: 0;
                    transition: none !important;
                    animation: none !important;
                    pointer-events: none;
                }

                html.studio-revealing .s-topbar,
                html.studio-revealing .s-rail,
                html.studio-revealing .s-inspector,
                html.studio-revealing .s-stage {
                    transition: opacity 180ms ease;
                }
            </style>
            <script>
                (() => {
                    const root = document.documentElement;
                    root.classList.add('studio-booting');

                    const frames = (n) => new Promise((resolve) => {
                        const step = () => (n-- > 0 ? requestAnimationFrame(step) : resolve());
                        step();
                    });

                    const alpine = new Promise((resolve) => {
                        if (window.Alpine?.version) return resolve();
                        document.addEventListener('alpine:initialized', resolve, { once: true });
                    }).then(() => frames(2));

                    // The canvas document, its images and its own fonts. A capturing
                    // listener catches the iframe's load even though load doesn't bubble.
                    const canvas = new Promise((resolve) => {
                        const done = (frame) => {
                            let fonts;
                            try { fonts = frame.contentDocument?.fonts?.ready; } catch (e) { /* cross-origin */ }
                            Promise.resolve(fonts).then(resolve, resolve);
                        };
                        document.addEventListener('load', (event) => {
                            if (event.target?.id === 'studio-canvas-frame') done(event.target);
                        }, true);
                        document.addEventListener('DOMContentLoaded', () => {
                            const frame = document.getElementById('studio-canvas-frame');
                            if (!frame) return resolve();
                            try {
                                if (frame.contentDocument?.readyState === 'complete' && frame.contentWindow.location.href !== 'about:blank') done(frame);
                            } catch (e) { /* the load listener still fires */ }
                        });
                    });

                    const timeout = new Promise((resolve) => setTimeout(resolve, 3000));

                    let revealed = false;
                    const reveal = async () => {
                        if (revealed) return;
                        revealed = true;
                        window.dispatchEvent(new CustomEvent('studio:reflow'));
                        await frames(2);
                        root.classList.add('studio-revealing');
                        root.classList.remove('studio-booting');
                        setTimeout(() => root.classList.remove('studio-revealing'), 250);
                        window.dispatchEvent(new CustomEvent('studio:revealed'));
                    };

                    Promise.race([
                        Promise.all([alpine, document.fonts.ready, canvas]),
                        timeout,
                    ]).then(reveal, reveal);
                })();
            </script>
        @endisset
        @studioStyles
        @livewireStyles
    </head>
    <body class="studio-app h-full overflow-hidden font-sans">
        {{-- Small screens: the editor is desktop-tuned — say so gracefully --}}
        <div
            x-data="{ dismissed: sessionStorage.getItem('studio.smallscreen') === '1' }"
            x-show="!dismissed"
            x-cloak
            class="fixed inset-0 z-[120] flex flex-col items-center justify-center gap-3 bg-shell px-8 text-center lg:hidden"
        >
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-wash ring-1 ring-line-strong">
                <svg class="h-7 w-auto text-ink" viewBox="0 0 72 75" fill="none">
                    <path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h2 class="mt-2 text-lg font-semibold tracking-tight text-ink">Studio works best on a desktop</h2>
            <p class="max-w-xs text-[13px] leading-relaxed text-soft">The editor is designed for larger screens — some tools may be cramped at this size.</p>
            <button
                class="s-btn-outline mt-2"
                @click="dismissed = true; sessionStorage.setItem('studio.smallscreen', '1')"
            >
                Continue anyway
            </button>
        </div>

        {{-- The app root. In the editor it is the frame: the top bar and
             the Design view's rail sit straight on it, and the stage and
             the right column are rounded containers laid on it a gutter
             apart. --}}
        <div class="flex h-dvh flex-col @isset($inspector) s-app @endisset" x-data>
            @isset($inspector)
                @include('studio::partials.topbar')

                <div class="s-app-row flex min-h-0 min-w-0 flex-1">

                    {{-- The rail: Design's tools. It stays in the row in the
                         other views, folded to nothing, so changing view is
                         one width transition. --}}
                    @include('studio::partials.rail')

                    {{-- The stage: whatever the top bar's view chose --}}
                    <main class="s-stage">
                        {{ $slot }}

                        {{-- The flyout: Pages or Media, over the stage's left
                             edge. It floats — nothing moves to make room —
                             and a click on the canvas puts it away. --}}
                        @isset($flyout)
                            <aside
                                class="s-flyout s-light"
                                x-show="$store.studio.drawer"
                                x-cloak
                                x-transition:enter="transition duration-200 ease-[cubic-bezier(.22,1,.36,1)]"
                                x-transition:enter-start="-translate-x-2 opacity-0"
                                x-transition:enter-end="translate-x-0 opacity-100"
                                x-transition:leave="transition duration-150 ease-in"
                                x-transition:leave-start="translate-x-0 opacity-100"
                                x-transition:leave-end="-translate-x-1.5 opacity-0"
                                :style="{ width: $store.studio.drawerWidth + 'px' }"
                                :aria-label="$store.studio.drawerShown === 'media' ? 'Media' : 'Pages'"
                            >
                                {{ $flyout }}

                                <div
                                    class="s-panel-seam"
                                    role="separator"
                                    aria-label="Resize the panel"
                                    @mousedown.prevent="
                                        const panel = $el.closest('aside');
                                        const shield = document.createElement('div');
                                        shield.className = 's-drag-shield';
                                        document.body.appendChild(shield);
                                        const move = (event) => $store.studio.setDrawerWidth(event.clientX - panel.getBoundingClientRect().left);
                                        const stop = () => {
                                            shield.remove();
                                            document.removeEventListener('mousemove', move);
                                            document.removeEventListener('mouseup', stop);
                                            window.removeEventListener('blur', stop);
                                            document.body.classList.remove('select-none');
                                        };
                                        document.body.classList.add('select-none');
                                        document.addEventListener('mousemove', move);
                                        document.addEventListener('mouseup', stop);
                                        window.addEventListener('blur', stop);
                                    "
                                ></div>
                            </aside>
                        @endisset
                    </main>

                    {{-- The right column: the section being edited, the
                         page's settings, or the Assistant — one at a time.
                         It stays in the row open or shut, so opening and
                         closing is one width transition. A light surface,
                         except beside the Code view, where it follows the
                         editor's theme. --}}
                    <aside
                        class="s-inspector"
                        :class="{ 'is-collapsed': !$store.studio.rightPanel, 's-light': $store.studio.view !== 'code' }"
                        :style="{ width: ($store.studio.rightPanel ? $store.studio.rightWidth : 0) + 'px' }"
                        :aria-hidden="!$store.studio.rightPanel"
                        :inert="!$store.studio.rightPanel"
                    >
                        <div class="s-column-inner" :style="{ width: $store.studio.rightWidth + 'px', minWidth: $store.studio.rightWidth + 'px' }">
                            {{ $inspector }}
                        </div>
                        <div
                            class="s-panel-seam"
                            role="separator"
                            aria-label="Resize the column"
                            @mousedown.prevent="
                                const aside = $el.closest('aside');
                                const shield = document.createElement('div');
                                shield.className = 's-drag-shield';
                                document.body.appendChild(shield);
                                const move = (event) => $store.studio.setRightWidth(aside.getBoundingClientRect().right - event.clientX);
                                const stop = () => {
                                    shield.remove();
                                    document.removeEventListener('mousemove', move);
                                    document.removeEventListener('mouseup', stop);
                                    window.removeEventListener('blur', stop);
                                    document.body.classList.remove('select-none');
                                };
                                document.body.classList.add('select-none');
                                document.addEventListener('mousemove', move);
                                document.addEventListener('mouseup', stop);
                                window.addEventListener('blur', stop);
                            "
                        ></div>
                    </aside>
                </div>
            @else
                <main class="relative min-h-0 min-w-0 flex-1 overflow-hidden">
                    {{ $slot }}
                </main>
            @endisset
        </div>

        @livewireScripts
        @studioScripts
    </body>
</html>
