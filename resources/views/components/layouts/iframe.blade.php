<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview</title>

    @if($tailwindCdn)
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif

    @if($alpineCdn)
        @foreach($alpinePlugins as $plugin)
            <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/{{ $plugin }}@3.x.x/dist/cdn.min.js"></script>
        @endforeach
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endif

    @foreach($extraStyles as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach

    @foreach($extraScripts as $script)
        @if(is_array($script))
            <script src="{{ $script['src'] }}"@if(!empty($script['defer'])) defer @endif></script>
        @else
            <script src="{{ $script }}"></script>
        @endif
    @endforeach

    @studioIframeCore

    {!! $headHtml !!}

    @stack('iframe-head')
</head>
<body class="{{ $bodyClass }}">
    @stack('iframe-body-start')

    {{ $slot }}

    @stack('iframe-body-end')
</body>
</html>
