<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        $meta = $page->meta;
        $seoTitle = $meta['seo_title'] ?? $page->title ?? config('app.name', 'Laravel');
        $seoDescription = $meta['seo_description'] ?? $page->description ?? null;
        $canonical = !empty($meta['canonical_url']) ? $meta['canonical_url'] : url()->current();
        $ogImage = $meta['og_image'] ?? null;

        // One robots tag: draft previews are always noindex,nofollow;
        // otherwise combine the page's own indexing toggles.
        $robots = null;
        if (!empty($noindex)) {
            $robots = 'noindex, nofollow';
        } elseif (!empty($meta['noindex']) || !empty($meta['nofollow'])) {
            $robots = (!empty($meta['noindex']) ? 'noindex' : 'index')
                . ', '
                . (!empty($meta['nofollow']) ? 'nofollow' : 'follow');
        }

        $jsonLd = null;
        if (!empty($meta['json_ld'])) {
            $decoded = json_decode($meta['json_ld']);
            $jsonLd = $decoded === null ? null : json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    @endphp

    <title>{{ $seoTitle }}</title>
    <link rel="canonical" href="{{ $canonical }}">
    @if($seoDescription)
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    @if(!empty($meta['seo_keywords']))
        <meta name="keywords" content="{{ $meta['seo_keywords'] }}">
    @endif
    @if($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $seoTitle }}">
    @if($seoDescription)
        <meta property="og:description" content="{{ $seoDescription }}">
    @endif
    <meta property="og:type" content="{{ $meta['og_type'] ?? 'website' }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:site_name" content="{{ !empty($meta['og_site_name']) ? $meta['og_site_name'] : config('app.name') }}">
    <meta property="og:locale" content="{{ !empty($meta['og_locale']) ? $meta['og_locale'] : str_replace('-', '_', app()->getLocale()) }}">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        @if(!empty($meta['og_image_alt']))
            <meta property="og:image:alt" content="{{ $meta['og_image_alt'] }}">
        @endif
    @endif

    {{-- X / Twitter (falls back to Open Graph for title, description, image) --}}
    <meta name="twitter:card" content="{{ $meta['twitter_card'] ?? ($ogImage ? 'summary_large_image' : 'summary') }}">
    @if(!empty($meta['twitter_site']))
        <meta name="twitter:site" content="{{ $meta['twitter_site'] }}">
    @endif
    @if(!empty($meta['twitter_creator']))
        <meta name="twitter:creator" content="{{ $meta['twitter_creator'] }}">
    @endif

    {{-- Branding --}}
    @if(!empty($meta['favicon']))
        <link rel="icon" href="{{ $meta['favicon'] }}">
    @endif
    @if(!empty($meta['theme_color']))
        <meta name="theme-color" content="{{ $meta['theme_color'] }}">
    @endif

    {{-- Structured data --}}
    @if($jsonLd)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif

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

    {{-- Per-page custom head HTML --}}
    @if(!empty($meta['head_html']))
        {!! $meta['head_html'] !!}
    @endif
</head>
<body class="{{ config('studio.iframe.body_class', 'min-h-screen w-full') }}">
    @foreach($renderedSections as $html)
        {!! $html !!}
    @endforeach
</body>
</html>
