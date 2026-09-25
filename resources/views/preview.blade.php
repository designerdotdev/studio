{{-- Non-interactive rendered preview used for the section picker thumbnails --}}
@php $chrome = app(\Designer\Studio\Support\SiteChrome::class); @endphp
<!DOCTYPE html>
<html lang="en" class="{{ $chrome->htmlClass() }}">
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

        /* A thumbnail never scrolls, so scroll-reveal content would stay hidden */
        [data-reveal] {
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
            translate: none !important;
            filter: none !important;
            transition: none !important;
        }
    </style>

    {{-- The site's fonts and theme, so a section looks like it will on the page --}}
    {!! $chrome->head() !!}
</head>
<body class="{{ $chrome->bodyClass() }}">
    @foreach($sections as $html)
        {!! $html !!}
    @endforeach
</body>
</html>
