<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview</title>

    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Blade.js for client-side rendering -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/app.js'])
    @endif

    <style>
        [data-component] {
            cursor: pointer;
            position: relative;
        }
        [data-component]::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 9999;
            transition: box-shadow 0.15s ease;
        }
        [data-component]:hover::before {
            box-shadow: inset 0 0 0 2px #3b82f6;
        }
        [data-component].selected::before {
            box-shadow: inset 0 0 0 3px #3b82f6;
        }
    </style>
</head>
<body class="min-h-screen w-full">
    {{ $slot }}
</body>
</html>
