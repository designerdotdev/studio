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
                @isset($sidebar)
                    <aside
                        x-data
                        class="flex w-[320px] shrink-0 flex-col overflow-hidden border-r border-line bg-panel transition-[width] duration-200 ease-out"
                        :class="$store.studio.sidebar ? '' : '!w-0 !border-r-0'"
                        :aria-hidden="!$store.studio.sidebar"
                        :inert="!$store.studio.sidebar"
                    >
                        <div class="flex h-full w-[320px] shrink-0 flex-col overflow-hidden">
                            {{ $sidebar }}
                        </div>
                    </aside>
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
