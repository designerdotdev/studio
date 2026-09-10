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

        <div class="flex h-dvh">
            {{-- The rail: the menu at the top, then one icon per panel. Full height, no top border. --}}
            <nav class="s-rail" x-data aria-label="Panels">
                @isset($menu)
                    {{ $menu }}
                @endisset
                @isset($sidebar)
                    <div class="s-rail-gap"></div>
                    @if(\Designer\Studio\Support\DevMode::enabled())
                        <button type="button" class="s-rail-btn" :class="$store.studio.rail === 'assistant' && $store.studio.sidebar && 'is-active'" @click="$store.studio.setRail('assistant')" title="Assistant" aria-label="Assistant">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
                        </button>
                    @endif
                    <button type="button" class="s-rail-btn" :class="$store.studio.rail === 'sections' && $store.studio.sidebar && 'is-active'" @click="$store.studio.setRail('sections')" title="Sections" aria-label="Sections">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/></svg>
                    </button>
                    <button type="button" class="s-rail-btn" :class="$store.studio.rail === 'pages' && $store.studio.sidebar && 'is-active'" @click="$store.studio.setRail('pages')" title="Pages" aria-label="Pages">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                    </button>
                    <button type="button" class="s-rail-btn" :class="$store.studio.rail === 'content' && $store.studio.sidebar && 'is-active'" @click="$store.studio.setRail('content')" title="Content" aria-label="Content">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75m16.5 3.75v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75"/></svg>
                    </button>
                    <button type="button" class="s-rail-btn" :class="$store.studio.rail === 'media' && $store.studio.sidebar && 'is-active'" @click="$store.studio.setRail('media')" title="Media" aria-label="Media">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                    </button>
                    @if(\Designer\Studio\Support\DevMode::enabled())
                        {{-- Files belongs to Code mode; it appears with it --}}
                        <button type="button" x-show="$store.studio.mode === 'code'" x-cloak class="s-rail-btn" :class="$store.studio.rail === 'files' && $store.studio.sidebar && 'is-active'" @click="$store.studio.setRail('files')" title="Files" aria-label="Files">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.25 12.75V12a2.25 2.25 0 0 1 2.25-2.25h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                        </button>
                    @endif
                @endisset
            </nav>

            @isset($sidebar)
                {{-- The panel column. Its header row is 48px so it lines up with the topbar. --}}
                <aside
                    x-data
                    class="flex w-[320px] shrink-0 flex-col overflow-hidden bg-shell transition-[width] duration-200 ease-out"
                    :class="$store.studio.sidebar ? '' : '!w-0'"
                    :aria-hidden="!$store.studio.sidebar"
                    :inert="!$store.studio.sidebar"
                >
                    {{-- pt-2 lines the 48px header row up with the topbar inside the inset workspace card --}}
                    <div class="flex h-full w-[320px] shrink-0 flex-col overflow-hidden pt-2">
                        {{ $sidebar }}
                    </div>
                </aside>
            @endisset

            {{-- The workspace card: toolbar + canvas in one rounded surface, inset from the shell --}}
            <div class="s-workspace">
                @isset($topbar)
                    <header class="s-topbar">
                        {{ $topbar }}
                    </header>
                @endisset

                <main class="relative min-w-0 flex-1 overflow-hidden">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
        @studioScripts
    </body>
</html>
