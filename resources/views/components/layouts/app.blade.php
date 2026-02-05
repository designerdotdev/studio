<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Designer Studio</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Filament Styles -->
        @filamentStyles

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif

        @livewireStyles
    </head>
<body class="h-full min-h-screen w-full antialiased">
    <div class="w-full h-full min-h-screen flex items-stretch justify-stretch">
        <main class="flex-1 h-screen overflow-hidden">{{ $slot }}</main>
        <aside class="w-80 bg-gray-50 h-screen flex flex-col border-r border-gray-200 overflow-hidden">
            {{ $sidebar ?? '' }}
        </aside>
    </div>

    @livewire('notifications')

    @livewireScripts
    @filamentScripts
</body>
</html>
