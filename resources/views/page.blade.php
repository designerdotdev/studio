@php $chrome = app(\Designer\Studio\Support\SiteChrome::class); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $chrome->htmlClass() }}">
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

    {{-- The site's own fonts, theme tokens, and scripts (set by a template import) --}}
    {!! $chrome->head() !!}

    {{-- Per-page custom head HTML --}}
    @if(!empty($meta['head_html']))
        {!! $meta['head_html'] !!}
    @endif
</head>
<body class="{{ $chrome->bodyClass() }}">
    @foreach($renderedSections as $html)
        {!! $html !!}
    @endforeach

    @if(!empty($preview))
        {{-- Draft preview mode: internal links stay inside the preview, and a
             badge makes the parallel-universe nature of this page obvious --}}
        <script>
            (function () {
                const base = @json(parse_url(route('studio.preview.home'), PHP_URL_PATH));
                const studioPath = @json('/' . trim(config('studio.path', 'studio'), '/'));

                const rewrite = (href) => {
                    if (!href || !href.startsWith('/') || href.startsWith('//')) return null;
                    if (href === studioPath || href.startsWith(studioPath + '/')) return null;

                    return href === '/' ? base : base + href;
                };

                // Rewrite in place so hover previews, cmd+click, and
                // middle-click all stay inside the draft preview too
                document.querySelectorAll('a[href]').forEach((a) => {
                    const to = rewrite(a.getAttribute('href'));
                    if (to) a.setAttribute('href', to);
                });

                // Safety net for links injected after load
                document.addEventListener('click', (event) => {
                    const link = event.target.closest && event.target.closest('a[href]');
                    if (!link) return;

                    const to = rewrite(link.getAttribute('href'));
                    if (to) {
                        event.preventDefault();
                        window.location.href = to;
                    }
                });
            })();
        </script>

        <div style="position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%); z-index: 2147483000; display: flex; align-items: center; gap: 10px; padding: 8px 8px 8px 14px; border-radius: 999px; background: rgba(11, 11, 13, 0.92); border: 1px solid rgba(255, 255, 255, 0.14); box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.55); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); font-family: ui-sans-serif, system-ui, sans-serif; font-size: 12.5px; font-weight: 500; color: rgba(244, 244, 246, 0.92); white-space: nowrap;">
            <span style="display: inline-flex; align-items: center; gap: 7px;">
                <span style="width: 7px; height: 7px; border-radius: 999px; background: #fbbf24;"></span>
                Draft preview
            </span>
            <a href="{{ route('studio.index', ['page' => $page->slug]) }}" style="display: inline-flex; align-items: center; height: 26px; padding: 0 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.1); color: #fff; text-decoration: none; font-weight: 600; transition: background 120ms ease;" onmouseover="this.style.background='rgba(255,255,255,0.18)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                Edit page
            </a>
        </div>
    @endif
</body>
</html>
