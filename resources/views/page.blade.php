<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $seoTitle = $page->meta['seo_title'] ?? $page->title ?? config('app.name', 'Laravel');
        $seoDescription = $page->meta['seo_description'] ?? $page->description ?? null;
    @endphp

    <title>{{ $seoTitle }}</title>
    @if($seoDescription)
        <meta name="description" content="{{ $seoDescription }}">
        <meta property="og:description" content="{{ $seoDescription }}">
    @endif
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    @if(config('studio.iframe.tailwind_cdn', true))
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif

    @if(config('studio.iframe.alpine_cdn', true))
        @foreach(array_keys(array_filter(config('studio.iframe.alpine_plugins', []))) as $plugin)
            <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/{{ $plugin }}@3.x.x/dist/cdn.min.js"></script>
        @endforeach
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endif

    <style>[x-cloak] { display: none !important; }</style>

    @foreach(config('studio.iframe.extra_styles', []) as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach

    @foreach(config('studio.iframe.extra_scripts', []) as $script)
        @if(is_array($script))
            <script src="{{ $script['src'] }}"@if(!empty($script['defer'])) defer @endif></script>
        @else
            <script src="{{ $script }}"></script>
        @endif
    @endforeach

    {!! config('studio.iframe.head_html', '') !!}
</head>
<body class="{{ config('studio.iframe.body_class', 'min-h-screen w-full') }}">
    @foreach($renderedSections as $html)
        {!! $html !!}
    @endforeach
</body>
</html>
