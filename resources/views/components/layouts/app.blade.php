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
        @isset($sidebar)
            {{-- The editor boots hidden. The dock is placed by script, the
                 open panel anchors to the dock, and the canvas paints blank
                 until its document loads — shown as they arrive, they jump.
                 So until all of it has settled (Alpine up, fonts in, the
                 canvas loaded, the dock placed against the real font) they
                 stay invisible, still laid out so they can be measured, and
                 then fade in together. Inline, so it holds from first paint;
                 a timeout reveals regardless, so nothing can stay hidden. --}}
            <style>
                html.studio-booting .s-dock,
                html.studio-booting .s-joined,
                html.studio-booting .s-float,
                html.studio-booting .s-scrim,
                html.studio-booting .s-stage {
                    visibility: hidden;
                    opacity: 0;
                    transition: none !important;
                    animation: none !important;
                    pointer-events: none;
                }

                html.studio-revealing .s-dock,
                html.studio-revealing .s-joined,
                html.studio-revealing .s-stage,
                html.studio-revealing .s-float,
                html.studio-revealing .s-scrim {
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

                    // Alpine has rendered the stores and run the dock's first place()
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
                        // Re-place the dock and panel against the loaded font, then
                        // show them only once that position has been painted
                        window.dispatchEvent(new CustomEvent('studio:reflow'));
                        await frames(2);
                        root.classList.add('studio-revealing');
                        root.classList.remove('studio-booting');
                        setTimeout(() => root.classList.remove('studio-revealing'), 250);
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
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/[0.04] ring-1 ring-white/10">
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

        {{-- The app root. A pinned toolbar reserves its edge here as padding
             (appInsets), so the rail below is flush and the site is pushed
             over rather than covered. --}}
        <div class="flex h-dvh flex-col" x-data :style="$store.studio?.appInsets || {}">
            @isset($sidebar)
                {{-- One row: the docked panel (when the toolbar is pinned)
                     and the stage. A right rail flips the row so the panel
                     sits beside it. --}}
                <div class="flex min-h-0 min-w-0 flex-1" :class="$store.studio.docked && $store.studio.panelSide === 'right' && 'flex-row-reverse'">

                {{-- The floating surface: every panel lives here, one visible
                     at a time. Anchored to its dock button (popover), centred
                     (sheet), or — with the toolbar pinned — a real column
                     beside the site (docked). The popover height is fixed to
                     what fits so the panels' own scroll regions keep working. --}}
                <aside
                    class="s-float"
                    :class="{
                        'is-sheet': $store.studio.sidebar && $store.studio.frame === 'sheet',
                        'is-docked': $store.studio.docked,
                        'at-right': $store.studio.docked && $store.studio.panelSide === 'right',
                        'is-ghost': !$store.studio.sidebar && $store.studio.chatFloating,
                    }"
                    {{-- An object binding: a string would replace the style
                         attribute and wipe the display:none x-show sets --}}
                    :style="$store.studio.docked ? { width: $store.studio.panelWidth + 'px' } : ($store.studio.frame === 'popover' ? style : {})"
                    x-data="{
                        style: {},
                        place() {
                            if ($store.studio.frame !== 'popover' || $store.studio.docked) return;
                            const button = document.querySelector(`#studio-dock [data-panel='${$store.studio.rail}']`);
                            {{-- Joined, the row is only the top of a taller
                                 object: anchoring to it would open the
                                 popover straight onto the composer. The
                                 shell is what has to be cleared. --}}
                            const anchor = $store.studio.joined
                                ? document.getElementById('studio-joined')
                                : document.getElementById('studio-dock');
                            if (!button || !anchor) return;
                            // A floating dock carries its own left/top. Until it
                            // has them — the frame it is in has just changed, and
                            // its effect runs after this one — its rect says
                            // nothing, so stay out of sight rather than anchor to
                            // the position it is leaving. It re-fires
                            // studio:dock-moved the moment it lands.
                            if (!anchor.style.left) { this.style = { visibility: 'hidden' }; return; }
                            const gap = 12, edgePad = 12;
                            const W = window.innerWidth, H = window.innerHeight;
                            const b = button.getBoundingClientRect();
                            const d = anchor.getBoundingClientRect();
                            const edge = $store.studio.dock.edge;
                            let w = $store.studio.floatWidth;
                            let left, top, maxH;
                            if (edge === 'bottom' || edge === 'top') {
                                // Along a horizontal edge the popover takes the
                                // dock's own width and shares its edges, so the
                                // two read as one object
                                w = Math.max(w, Math.round(d.width));
                                left = d.left + d.width / 2 - w / 2;
                                maxH = Math.min(H * 0.7, H - d.height - gap - edgePad * 2);
                                top = edge === 'bottom' ? d.top - gap - maxH : d.bottom + gap;
                            } else {
                                maxH = Math.min(H * 0.7, H - edgePad * 2);
                                top = b.top + b.height / 2 - maxH / 2;
                                left = edge === 'left' ? d.right + gap : d.left - gap - w;
                            }
                            left = Math.round(Math.max(edgePad, Math.min(left, W - w - edgePad)));
                            top = Math.round(Math.max(edgePad, Math.min(top, H - maxH - edgePad)));
                            this.style = { left: `${left}px`, top: `${top}px`, width: `${w}px`, height: `${Math.round(maxH)}px` };
                        },
                    }"
                    x-effect="$store.studio.rail; $store.studio.sidebar; $store.studio.dock; $store.studio.mode; $nextTick(() => place())"
                    @resize.window.debounce.50ms="place()"
                    @studio:dock-moved.window="place()"
                    @studio:reflow.window="place()"
                    {{-- With the chat floating the aside stays mounted as an
                         invisible ghost (is-ghost): the chat card is one of
                         its children, fixed over the site --}}
                    x-show="$store.studio.sidebar || $store.studio.chatFloating"
                    x-cloak
                    :aria-hidden="!$store.studio.sidebar && !$store.studio.chatFloating"
                    :inert="!$store.studio.sidebar && !$store.studio.chatFloating"
                >
                    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
                        {{ $sidebar }}
                    </div>

                    {{-- Docked: a drag seam on the panel's inner edge sets
                         its width. A shield covers the window while it is
                         held so the canvas iframe can't swallow the pointer. --}}
                    <div
                        x-show="$store.studio.docked"
                        x-cloak
                        class="s-panel-seam"
                        role="separator"
                        aria-label="Resize the panel"
                        @mousedown.prevent="
                            const aside = $el.closest('aside');
                            const right = $store.studio.panelSide === 'right';
                            const shield = document.createElement('div');
                            shield.className = 's-drag-shield is-col-resize';
                            document.body.appendChild(shield);
                            const move = (event) => {
                                const box = aside.getBoundingClientRect();
                                $store.studio.setPanelWidth(right ? box.right - event.clientX : event.clientX - box.left);
                            };
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

                {{-- The site is the screen: the stage fills what is left. --}}
                <main class="s-stage">
                    {{ $slot }}
                </main>

                </div>

                {{-- Behind a sheet only --}}
                <div
                    x-data
                    x-show="$store.studio.sidebar && $store.studio.frame === 'sheet'"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="s-scrim"
                    @click="$store.studio.closePanel()"
                ></div>

                {{-- The joined unit's shell. It is the only thing that
                     carries the material and the radius when the toolbar and
                     the composer are one object, so the outer corner is a
                     single continuous curve rather than two that have to be
                     kept in step. It paints and nothing else: the row and the
                     composer sit over it and go transparent, and it never
                     takes a pointer event. Placed by the chat card, which is
                     the only thing that knows the composer's height. --}}
                <div
                    id="studio-joined"
                    x-data
                    class="s-joined"
                    x-show="$store.studio.joined"
                    x-cloak
                    aria-hidden="true"
                ></div>

                @include('studio::partials.dock')
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
