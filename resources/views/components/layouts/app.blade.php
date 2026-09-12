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
                <svg class="h-7 w-auto text-neutral-100" viewBox="0 0 72 75" fill="none">
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

        <div class="flex h-dvh flex-col">
            @isset($sidebar)
                {{-- The site is the screen: the stage fills the window. --}}
                <main class="s-stage">
                    {{ $slot }}
                </main>

                {{-- Behind a sheet only --}}
                <div
                    x-data
                    x-show="$store.studio.sidebar && $store.studio.frame === 'sheet'"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="s-scrim"
                    @click="$store.studio.closePanel()"
                ></div>

                {{-- The floating surface: every panel lives here, one visible
                     at a time. Anchored to its dock button (popover) or
                     centred (sheet). The height is fixed to what fits so the
                     panels' own scroll regions keep working. --}}
                <aside
                    class="s-float"
                    :class="$store.studio.frame === 'sheet' && 'is-sheet'"
                    {{-- An object binding: a string would replace the style
                         attribute and wipe the display:none x-show sets --}}
                    :style="$store.studio.frame === 'popover' ? style : {}"
                    x-data="{
                        style: {},
                        place() {
                            if ($store.studio.frame !== 'popover') return;
                            const button = document.querySelector(`#studio-dock [data-panel='${$store.studio.rail}']`);
                            const dock = document.getElementById('studio-dock');
                            if (!button || !dock) return;
                            const gap = 12, edgePad = 12;
                            const W = window.innerWidth, H = window.innerHeight;
                            const b = button.getBoundingClientRect();
                            const d = dock.getBoundingClientRect();
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
                    x-show="$store.studio.sidebar"
                    x-cloak
                    :aria-hidden="!$store.studio.sidebar"
                    :inert="!$store.studio.sidebar"
                >
                    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
                        {{ $sidebar }}
                    </div>
                </aside>

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
