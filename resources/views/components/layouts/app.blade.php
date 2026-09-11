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
            {{-- The topbar spans the full width: menu first, then the page's own chrome --}}
            @isset($topbar)
                <header class="s-topbar">
                    @isset($menu)
                        {{ $menu }}
                    @endisset
                    {{ $topbar }}
                </header>
            @endisset

            @isset($sidebar)
                {{-- The workspace: the sidebar and the canvas are rounded cards
                     inset on the shell, so the topbar flows around them. The
                     activity bar (panel switcher) sits where the user put it:
                     across the sidebar's top (default) or bottom — both collapse
                     with it for a full-page canvas — as a strip to its left,
                     or hidden. --}}
                <div class="s-workspace" x-data>
                    <template x-if="$store.studio.activityBar === 'left'">
                        @include('studio::partials.activity-bar', ['orientation' => 'vertical'])
                    </template>

                    <aside
                        class="s-card s-sidebar"
                        :class="!$store.studio.sidebar && 'is-collapsed'"
                        :aria-hidden="!$store.studio.sidebar"
                        :inert="!$store.studio.sidebar"
                    >
                        <div class="flex h-full w-[320px] shrink-0 flex-col overflow-hidden">
                            <template x-if="$store.studio.activityBar === 'top'">
                                @include('studio::partials.activity-bar', ['orientation' => 'horizontal', 'class' => 'is-top'])
                            </template>

                            <div class="flex min-h-0 flex-1 flex-col">
                                {{ $sidebar }}
                            </div>

                            <template x-if="$store.studio.activityBar === 'bottom'">
                                @include('studio::partials.activity-bar', ['orientation' => 'horizontal', 'class' => 'is-bottom'])
                            </template>
                        </div>
                    </aside>

                    <main class="s-card s-stage">
                        {{ $slot }}
                    </main>
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
