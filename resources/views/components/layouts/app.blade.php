<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ?? 'Designer Studio' }}</title>

        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230b0b0d'/%3E%3Cpath d='M10 9.5h7.25a6.5 6.5 0 0 1 0 13H10v-13Z' fill='none' stroke='%23fff' stroke-width='2.5'/%3E%3Ccircle cx='23.5' cy='23.5' r='2.5' fill='%234c7dfa'/%3E%3C/svg%3E">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">

        @studioStyles
        @livewireStyles
    </head>
    <body class="studio-app h-full overflow-hidden font-sans">
        <div class="flex h-dvh flex-col">
            @isset($topbar)
                <header class="s-topbar">
                    {{ $topbar }}
                </header>
            @endisset

            <div class="flex min-h-0 flex-1 items-stretch">
                <main class="relative min-w-0 flex-1 overflow-hidden">
                    {{ $slot }}
                </main>

                @isset($sidebar)
                    <aside
                        x-data
                        class="flex w-[320px] shrink-0 flex-col overflow-hidden border-l border-line bg-panel transition-[width] duration-200 ease-out"
                        :class="$store.studio.sidebar ? '' : '!w-0 !border-l-0'"
                        :aria-hidden="!$store.studio.sidebar"
                        :inert="!$store.studio.sidebar"
                    >
                        <div class="flex h-full w-[320px] shrink-0 flex-col overflow-hidden">
                            {{ $sidebar }}
                        </div>
                    </aside>
                @endisset
            </div>
        </div>

        @livewireScripts
        @studioScripts
    </body>
</html>
