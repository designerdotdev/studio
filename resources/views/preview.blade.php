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

        /* A section shorter than the thumbnail (a nav bar, a logo strip)
           sits in the middle of it instead of at the top of an empty card.
           Auto margins, so a taller one still starts at the top. */
        html, body { min-height: 100vh; }
        body { display: flex; flex-direction: column; }
        .studio-preview-fit { width: 100%; margin-block: auto; }
        ::-webkit-scrollbar { display: none; }

        /* A thumbnail is a still of the finished section: every animation is
           taken straight to its last frame. Pausing them instead froze an
           entrance on its first one — a logo mark that rises on load stayed
           below its edge, so the thumbnail showed no mark at all. */
        *, *::before, *::after {
            animation-duration: 0s !important;
            animation-delay: 0s !important;
            animation-iteration-count: 1 !important;
        }

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
    <div class="studio-preview-fit">
        @foreach($sections as $html)
            {!! $html !!}
        @endforeach
    </div>
</body>
</html>
