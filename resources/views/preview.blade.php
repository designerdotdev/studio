{{-- Non-interactive rendered preview used for picker + onboarding thumbnails --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Preview</title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        html, body { pointer-events: none; overflow: hidden; }
        ::-webkit-scrollbar { display: none; }
        * { animation-play-state: paused !important; }
    </style>
</head>
<body class="min-h-screen w-full bg-white antialiased">
    @foreach($sections as $html)
        {!! $html !!}
    @endforeach
</body>
</html>
