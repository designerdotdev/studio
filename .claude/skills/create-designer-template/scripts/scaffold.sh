#!/usr/bin/env bash
#
# scaffold.sh <slug> "<Name>" [light|dark] [--dir <templates-dir>]
#
# Creates ~/Sites/site-templates/<slug> (or --dir) in the site-templates format
# with the token layer, a layout, a nav with dropdowns + Sign in + CTA, a
# footer, one closing-CTA section, the home and 404 pages, main.js, and a
# first commit. Everything it writes passes lint-site.py; the hero and the
# rest of the sections are yours to design.
set -euo pipefail
SLUG="${1:-}"; NAME="${2:-}"; THEME="${3:-light}"; [[ "$THEME" == --* ]] && THEME=light
[ -n "$SLUG" ] && [ -n "$NAME" ] || { sed -n 3,9p "$0"; exit 2; }
[[ "$SLUG" =~ ^[a-z][a-z0-9-]*$ ]] || { echo "slug must be lowercase letters, digits, hyphens"; exit 2; }
DIR="$HOME/Sites/site-templates"
for ((i=1;i<=$#;i++)); do [ "${!i}" = "--dir" ] && j=$((i+1)) && DIR="${!j}"; done
T="$DIR/$SLUG"
[ -e "$T" ] && { echo "$T already exists"; exit 1; }
mkdir -p "$T/files/public/js" "$T/files/public/images" "$T/files/resources/css" "$T/files/resources/data/collections" \
         "$T/files/resources/views/components/layouts" "$T/files/resources/views/components/sections" "$T/files/resources/views/pages"
R="$T/files/resources"; P="$T/files/public"

if [ "$THEME" = "dark" ]; then
  TOKENS='    --color-canvas: #0b0b0d;
    --color-panel: #121216;
    --color-raised: #1a1a20;
    --color-line: rgba(255, 255, 255, 0.10);
    --color-line-strong: rgba(255, 255, 255, 0.20);

    --color-ink: #f4f4f5;
    --color-lede: #c9c9ce;
    --color-muted: #9a9aa3;
    --color-faint: #66666f;

    --color-accent: #7c9cff;
    --color-accent-ink: #0b0b0d;
    --color-accent-soft: rgba(124, 156, 255, 0.12);

    --color-shade: #f4f4f5;
    --color-shade-ink: #0b0b0d;
    --color-shade-muted: #5b5b63;
    --color-shade-line: rgba(0, 0, 0, 0.12);'
  ACCENT='#7c9cff'; SCHEME='dark'
else
  TOKENS='    --color-canvas: #f7f6f3;
    --color-panel: #ffffff;
    --color-raised: #eeece7;
    --color-line: rgba(20, 19, 16, 0.10);
    --color-line-strong: rgba(20, 19, 16, 0.20);

    --color-ink: #141310;
    --color-lede: #3b3a36;
    --color-muted: #6b6a64;
    --color-faint: #9a9891;

    --color-accent: #1d4ed8;
    --color-accent-ink: #f7f6f3;
    --color-accent-soft: rgba(29, 78, 216, 0.08);

    --color-shade: #1b1a17;
    --color-shade-ink: #f3f2ee;
    --color-shade-muted: #a3a19a;
    --color-shade-line: rgba(255, 255, 255, 0.12);'
  ACCENT='#1d4ed8'; SCHEME='light'
fi

cat > "$T/template.json" <<JSON
{
    "name": "$NAME",
    "dialect": "blade",
    "description": "One evocative sentence naming the standout surfaces — rewrite before shipping.",
    "icon": "✦",
    "repo": "https://github.com/site-templates/$SLUG",
    "order": 99,
    "category": "landing",
    "theme": "$THEME",
    "accent": "$ACCENT",
    "pages": ["Home"]
}
JSON
printf '.DS_Store\nnode_modules/\n' > "$T/.gitignore"
printf 'User-agent: *\nAllow: /\n' > "$P/robots.txt"

cat > "$P/favicon.svg" <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
    <style>
        path { fill: #141310 }
        @media (prefers-color-scheme: dark) { path { fill: #f4f4f5 } }
    </style>
    <path d="M16 3 27.5 26.5 16 21 4.5 26.5Z"/>
</svg>
SVG

cat > "$R/css/site.css" <<CSS
/*
    $NAME — the design system and the motion system.

    Inlined right after Tailwind by @vite in the layout, so everything here
    applies to every page. One palette and one type pairing, tuned together;
    real values live here and only here. The tokens become utilities:
    bg-canvas, text-ink, text-muted, border-line, bg-panel, bg-shade…
    (contract: .claude/skills/designer-craft/references/tokens.md)
*/
@theme {
    --font-display: 'Instrument Serif', ui-serif, Georgia, serif;
    --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;

$TOKENS

    /* One easing family for everything interactive: fast in, long settle. */
    --ease-out-quart: cubic-bezier(0.25, 1, 0.5, 1);
    --ease-spring: cubic-bezier(0.16, 1, 0.3, 1);
}

html { background-color: var(--color-canvas); color-scheme: $SCHEME; }
body { overflow-x: clip; }
::selection { background-color: var(--color-ink); color: var(--color-canvas); }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; border-radius: 3px; }
h1, h2, h3, h4 { font-family: var(--font-display); }

/* Cross-page fades — the whole site feels like one surface. */
@view-transition { navigation: auto; }
::view-transition-old(root), ::view-transition-new(root) { animation-duration: 220ms; animation-timing-function: ease; }

/*
    The reveal system. main.js adds .js to <html> before first paint, then an
    IntersectionObserver flips .is-visible as each [data-reveal] scrolls in.
    Without JavaScript nothing is ever hidden. Stagger with .reveal-N.
*/
.js [data-reveal] {
    opacity: 0;
    translate: 0 18px;
    filter: blur(6px);
    transition: opacity .9s var(--ease-spring), translate .9s var(--ease-spring), filter .9s var(--ease-spring);
    transition-delay: var(--reveal-delay, 0ms);
}
.js [data-reveal].is-visible { opacity: 1; translate: 0 0; filter: blur(0); }
.reveal-1 { --reveal-delay: 70ms; }
.reveal-2 { --reveal-delay: 140ms; }
.reveal-3 { --reveal-delay: 210ms; }
.reveal-4 { --reveal-delay: 280ms; }
.reveal-5 { --reveal-delay: 350ms; }
.reveal-6 { --reveal-delay: 420ms; }

/* The header: transparent at rest; once scrolled, main.js sets data-scrolled and it becomes glass. */
#header { border-bottom: 1px solid transparent; transition: background-color .4s ease, border-color .4s ease, backdrop-filter .4s ease; }
#header[data-scrolled] {
    background-color: color-mix(in oklab, var(--color-canvas) 72%, transparent);
    border-bottom-color: var(--color-line);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

/* Nav dropdowns: in the DOM at full size, moved and faded from the trigger. Exit faster than entrance. */
[data-dropdown-panel] {
    opacity: 0; translate: 0 6px; scale: .98; visibility: hidden;
    transform-origin: top center;
    transition: opacity .18s ease-in, translate .18s ease-in, scale .18s ease-in, visibility 0s linear .18s;
}
[data-dropdown].is-open [data-dropdown-panel] {
    opacity: 1; translate: 0 0; scale: 1; visibility: visible;
    transition: opacity .24s var(--ease-out-quart), translate .24s var(--ease-out-quart), scale .24s var(--ease-out-quart), visibility 0s;
}
[data-dropdown] .dropdown-caret { transition: rotate .25s var(--ease-out-quart); }
[data-dropdown].is-open .dropdown-caret { rotate: 180deg; }

/* The mobile sheet slides down under the header. */
[data-mobile-panel] {
    opacity: 0; translate: 0 -8px; visibility: hidden;
    transition: opacity .28s var(--ease-out-quart), translate .28s var(--ease-out-quart), visibility 0s linear .28s;
}
.menu-open [data-mobile-panel] { opacity: 1; translate: 0 0; visibility: visible; transition: opacity .32s var(--ease-out-quart), translate .32s var(--ease-out-quart), visibility 0s; }
.menu-open { overflow: hidden; }

/* Smooth <details> opening (Chrome; instant elsewhere). */
details.faq-item { interpolate-size: allow-keywords; }
details.faq-item::details-content { block-size: 0; overflow: hidden; transition: block-size .35s var(--ease-out-quart), content-visibility .35s; transition-behavior: allow-discrete; }
details.faq-item[open]::details-content { block-size: auto; }
details.faq-item summary::-webkit-details-marker { display: none; }

/* Reduced motion wins outright: everything visible, nothing moving. */
@media (prefers-reduced-motion: reduce) {
    .js [data-reveal] { opacity: 1; translate: 0 0; filter: none; transition: none; }
    [data-dropdown-panel], [data-mobile-panel], details.faq-item::details-content { transition-duration: 0s; }
    ::view-transition-old(root), ::view-transition-new(root) { animation: none; }
}
CSS

cat > "$R/data/site.json" <<JSON
{
    "name": "$NAME",
    "nav_links": [
        {
            "text": "Product",
            "url": "#",
            "children": [
                { "text": "Overview", "url": "/features", "description": "What ships in the box" },
                { "text": "How it works", "url": "/#how-it-works", "description": "Three steps, no setup" },
                { "text": "Changelog", "url": "/changelog", "description": "What landed this week" }
            ]
        },
        { "text": "Pricing", "url": "/pricing" },
        { "text": "Changelog", "url": "/changelog" }
    ],
    "footer_links": [
        { "text": "Product", "url": "#", "children": [ { "text": "Overview", "url": "/features" }, { "text": "Pricing", "url": "/pricing" }, { "text": "Changelog", "url": "/changelog" } ] },
        { "text": "Company", "url": "#", "children": [ { "text": "Sign in", "url": "#" }, { "text": "Contact", "url": "mailto:hello@$SLUG.dev" }, { "text": "Status", "url": "#" } ] },
        { "text": "Legal", "url": "#", "children": [ { "text": "Privacy", "url": "#" }, { "text": "Terms", "url": "#" } ] }
    ],
    "social_links": [
        { "text": "X", "url": "#", "icon": "<svg viewBox='0 0 20 20' class='size-4 fill-current' aria-hidden='true'><path d='M15.3 2h2.8l-6.1 7 7.2 9.5h-5.6l-4.4-5.8L4.1 18.5H1.3l6.5-7.5L.9 2h5.8l4 5.3L15.3 2Zm-1 15h1.6L6.1 3.6H4.4L14.3 17Z'/></svg>" },
        { "text": "GitHub", "url": "#", "icon": "<svg viewBox='0 0 20 20' class='size-4 fill-current' aria-hidden='true'><path fill-rule='evenodd' d='M10 1.5a8.5 8.5 0 0 0-2.69 16.57c.43.08.58-.19.58-.41v-1.6c-2.37.51-2.87-1.01-2.87-1.01-.39-.99-.95-1.25-.95-1.25-.77-.53.06-.52.06-.52.86.06 1.31.88 1.31.88.76 1.31 2 .93 2.49.71.08-.55.3-.93.54-1.15-1.89-.21-3.88-.95-3.88-4.21 0-.93.33-1.69.88-2.29-.09-.21-.38-1.08.08-2.25 0 0 .71-.23 2.34.87a8.1 8.1 0 0 1 4.26 0c1.62-1.1 2.33-.87 2.33-.87.46 1.17.17 2.04.09 2.25.55.6.87 1.36.87 2.29 0 3.27-1.99 4-3.89 4.21.31.26.58.78.58 1.57v2.33c0 .23.15.5.59.41A8.5 8.5 0 0 0 10 1.5Z' clip-rule='evenodd'/></svg>" }
    ]
}
JSON

cat > "$R/views/components/layouts/main.blade.php" <<BLADE
@props(['title' => 'Home', 'description' => ''])
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ \$title }} · $NAME</title>
    <meta name="description" content="{{ \$description }}">

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <!-- Two families, only the weights in use. Swap the pairing in site.css and here together. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Loads Tailwind and inlines resources/css/site.css — the palette and the motion system -->
    @vite(['resources/css/site.css'])

    <!-- Flag JS support before first paint so scroll reveals never flash (see main.js) -->
    <script>document.documentElement.classList.add('js')</script>
    <script src="/js/main.js" defer></script>
</head>
<body class="min-h-dvh bg-canvas font-sans text-ink antialiased">

    <!-- The site-wide nav. Its links live in resources/data/site.json (nav_links); the markup is components/nav.blade.php. -->
    <x-nav :links="\$site->nav_links"/>

    <!-- The fixed header floats over this; each page's opening section carries its own top padding. -->
    <main class="relative">
        {{ \$slot }}
    </main>

    <x-footer :columns="\$site->footer_links" :social="\$site->social_links"/>

</body>
</html>
BLADE

cat > "$R/views/components/nav.blade.php" <<'BLADE'
@props([
    'brand' => '__NAME__',
    'links' => [],
    'signInText' => 'Sign in',
    'signInLink' => '#',
    'ctaText' => 'Get started',
    'ctaLink' => '/pricing',
])
<!--
    The site-wide top bar. It rests transparent over each page's opening
    section; once you scroll, main.js sets data-scrolled and site.css fades in
    the glass background and hairline.

    Links come from nav_links in resources/data/site.json. Nest links under an
    item and it becomes a dropdown (tested with count(): on the canvas an empty
    children list is a DataBag object, which is truthy): opens on hover with a short intent delay,
    toggles on click for touch, closes on Escape or an outside click.
-->
<header id="header" class="fixed inset-x-0 top-0 z-50">
    <div class="mx-auto flex h-16 w-full max-w-6xl items-center gap-8 px-6">

        <!-- href="/" always points to the site's root, in preview and when published -->
        <a href="/" aria-label="Homepage" class="flex shrink-0 items-center gap-2.5 text-ink">
            <svg viewBox="0 0 24 24" class="size-5 shrink-0 fill-current" aria-hidden="true">
                <path d="M12 1.9 21.4 21.4 12 16.9 2.6 21.4Z"/>
            </svg>
            <span class="text-[17px] font-semibold tracking-[-0.02em]">{{ $brand }}</span>
        </a>

        <nav class="max-lg:hidden" aria-label="Main">
            <ul role="list" class="flex items-center gap-1 text-[15px] text-lede">
                @foreach ($links as $link)
                @if (count($link->children ?? []))
                <li class="relative" data-dropdown>
                    <button type="button" data-dropdown-trigger aria-expanded="false" class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-2 transition-colors duration-200 hover:text-ink">
                        {{ $link->text }}
                        <svg viewBox="0 0 16 16" class="dropdown-caret size-3.5 fill-current opacity-50" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 6.22a.75.75 0 0 1 1.06 0L8 8.94l2.72-2.72a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0L4.22 7.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    {{-- Centred with left-1/2 + a negative margin of half the width: the open motion animates translate. --}}
                    <div data-dropdown-panel class="absolute top-full left-1/2 -ml-[10rem] w-[20rem] pt-3">
                        <div class="flex flex-col gap-0.5 rounded-2xl border border-line bg-panel p-2 shadow-2xl shadow-black/10">
                            @foreach ($link->children as $child)
                            <a href="{{ $child->url }}" class="group flex flex-col rounded-xl px-3.5 py-2.5 transition-colors duration-150 hover:bg-raised">
                                <span class="text-[15px] font-medium text-ink">{{ $child->text }}</span>
                                @if ($child->description ?? false)
                                <span class="mt-0.5 text-[13px] leading-snug text-muted">{{ $child->description }}</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                </li>
                @else
                <li>
                    <a href="{{ $link->url }}" class="rounded-lg px-3 py-2 transition-colors duration-200 hover:text-ink aria-[current]:text-ink">{{ $link->text }}</a>
                </li>
                @endif
                @endforeach
            </ul>
        </nav>

        <div class="ml-auto flex items-center gap-2 max-lg:hidden">
            <a href="{{ $signInLink }}" class="rounded-full border border-line-strong px-4 py-2 text-[15px] text-ink transition-colors duration-200 hover:bg-raised">{{ $signInText }}</a>
            <a href="{{ $ctaLink }}" class="rounded-full bg-ink px-4 py-2 text-[15px] font-medium text-canvas transition-opacity duration-200 hover:opacity-85">{{ $ctaText }}</a>
        </div>

        <!-- Mobile: hamburger. The 44px box keeps the tap target comfortable. -->
        <button type="button" data-mobile-toggle aria-expanded="false" aria-label="Toggle menu" class="ml-auto flex size-11 cursor-pointer items-center justify-center rounded-lg text-ink transition-colors duration-200 hover:bg-raised lg:hidden">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" class="size-5 stroke-current [.menu-open_&]:hidden" aria-hidden="true"><path stroke-linecap="round" d="M3.75 7h16.5M3.75 12h16.5M3.75 17h16.5"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" class="hidden size-5 stroke-current [.menu-open_&]:block" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- The mobile sheet. Dropdown parents flatten into their children here. -->
    <div data-mobile-panel class="border-b border-line bg-canvas/95 backdrop-blur-xl lg:hidden">
        <nav class="mx-auto w-full max-w-6xl px-6 py-4" aria-label="Mobile">
            <ul role="list" class="flex flex-col text-base">
                @foreach ($links as $link)
                @if (count($link->children ?? []))
                <li class="mt-3 px-3 font-mono text-[11px] tracking-widest text-faint uppercase">{{ $link->text }}</li>
                @foreach ($link->children as $child)
                <li><a href="{{ $child->url }}" class="flex rounded-lg px-3 py-3 text-ink transition-colors duration-200 hover:bg-raised">{{ $child->text }}</a></li>
                @endforeach
                @else
                <li><a href="{{ $link->url }}" class="flex rounded-lg px-3 py-3 text-ink transition-colors duration-200 hover:bg-raised">{{ $link->text }}</a></li>
                @endif
                @endforeach
            </ul>
            <div class="mt-4 flex items-center gap-3 border-t border-line pt-4">
                <a href="{{ $signInLink }}" class="flex-1 rounded-full border border-line-strong px-4 py-3 text-center text-sm text-ink transition-colors duration-200 hover:bg-raised">{{ $signInText }}</a>
                <a href="{{ $ctaLink }}" class="flex-1 rounded-full bg-ink px-4 py-3 text-center text-sm font-medium text-canvas transition-opacity duration-200 hover:opacity-85">{{ $ctaText }}</a>
            </div>
        </nav>
    </div>
</header>
BLADE
sed -i '' "s/__NAME__/$NAME/g" "$R/views/components/nav.blade.php"

cat > "$R/views/components/nav.yml" <<YML
# The visual editor's contract for components/nav.blade.php — surfaces under
# the Layout tab. Field names are camelCase, matching the props they fill.
title: Navigation
description: The top bar — brand, links with dropdown menus, Sign in, and the primary call to action
fixed: true
fields:
    brand:
        type: text
        label: Brand name
        default: "$NAME"
    links:
        type: repeater
        label: Navigation links
        description: "Nest links under an item to turn it into a dropdown. Stored in site.json."
        source: site.nav_links
        nestable: true
        add_button_label: "Add link"
        item_label: text
        sub_fields:
            text:
                type: text
                label: Text
                default: "New link"
            url:
                type: url
                label: Link
                default: "/"
            description:
                type: text
                label: Description
                default: ""
    signInText:
        type: text
        label: Sign in text
        default: "Sign in"
    signInLink:
        type: url
        label: Sign in link
        default: "#"
    ctaText:
        type: text
        label: Primary button text
        default: "Get started"
    ctaLink:
        type: url
        label: Primary button link
        default: "/pricing"
YML

cat > "$R/views/components/footer.blade.php" <<'BLADE'
@props([
    'brand' => '__NAME__',
    'tagline' => 'Rewrite this line in the product\'s voice.',
    'columns' => [],
    'social' => [],
    'legal' => '© 2026 __NAME__. All rights reserved.',
])
<!--
    The site-wide footer: the mark and a one-line tagline, link columns from
    footer_links in site.json, social icons from social_links, and a legal row.
-->
<footer class="border-t border-line">
    <div class="mx-auto w-full max-w-6xl px-6 py-16 sm:py-20">
        <div class="grid gap-12 lg:grid-cols-[1.4fr_repeat(3,1fr)]">
            <div>
                <a href="/" class="flex items-center gap-2.5 text-ink" aria-label="Homepage">
                    <svg viewBox="0 0 24 24" class="size-5 fill-current" aria-hidden="true"><path d="M12 1.9 21.4 21.4 12 16.9 2.6 21.4Z"/></svg>
                    <span class="text-[17px] font-semibold tracking-[-0.02em]">{{ $brand }}</span>
                </a>
                <p class="mt-4 max-w-[30ch] text-[15px]/7 text-muted">{{ $tagline }}</p>
                <ul role="list" class="mt-6 flex items-center gap-2">
                    @foreach ($social as $item)
                    <li>
                        <a href="{{ $item->url }}" aria-label="{{ $item->text }}" class="flex size-9 items-center justify-center rounded-full border border-line text-muted transition-colors duration-200 hover:border-line-strong hover:text-ink">{!! $item->icon !!}</a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @foreach ($columns as $column)
            <div>
                <p class="font-mono text-[11px] tracking-widest text-faint uppercase">{{ $column->text }}</p>
                <ul role="list" class="mt-4 flex flex-col gap-2.5 text-[15px]">
                    @foreach ($column->children ?? [] as $child)
                    <li><a href="{{ $child->url }}" class="text-lede transition-colors duration-200 hover:text-ink">{{ $child->text }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
        <p class="mt-14 border-t border-line pt-6 text-[13px] text-faint">{{ $legal }}</p>
    </div>
</footer>
BLADE
sed -i '' "s/__NAME__/$NAME/g" "$R/views/components/footer.blade.php"

cat > "$R/views/components/footer.yml" <<YML
title: Footer
description: The mark, a tagline, link columns from site.json, social icons, and the legal line
fields:
    brand:
        type: text
        label: Brand name
        default: "$NAME"
    tagline:
        type: textarea
        label: Tagline
        rows: 2
        default: "Rewrite this line in the product's voice."
    columns:
        type: repeater
        label: Link columns
        description: "Each top-level item is a column; its nested links are the rows. Stored in site.json."
        source: site.footer_links
        nestable: true
        add_button_label: "Add column"
        item_label: text
        sub_fields:
            text:
                type: text
                label: Text
                default: "New link"
            url:
                type: url
                label: Link
                default: "/"
    social:
        type: repeater
        label: Social links
        source: site.social_links
        add_button_label: "Add social link"
        item_label: text
        sub_fields:
            text:
                type: text
                label: Name
                default: "X"
            url:
                type: url
                label: Link
                default: "#"
            icon:
                type: textarea
                label: Icon (inline SVG)
                rows: 3
                default: ""
    legal:
        type: text
        label: Legal line
        default: "© 2026 $NAME. All rights reserved."
YML

cat > "$R/views/components/sections/cta.blade.php" <<'BLADE'
@props([
    'heading' => 'Start on the free plan today',
    'body' => 'No card, no call. Import your first project and see the difference this afternoon.',
    'ctaText' => 'Start free',
    'ctaLink' => '/pricing',
    'secondaryText' => 'Talk to us',
    'secondaryLink' => 'mailto:hello@example.com',
])
<!--
    The closing band. It changes the rhythm on purpose: the one dark surface
    on a light page (or the one light surface on a dark one), a statement, and
    a single primary action. Rewrite the defaults for the product.
-->
<section id="cta" class="px-6 py-24 sm:py-32">
    <div class="mx-auto w-full max-w-6xl overflow-hidden rounded-3xl bg-shade px-8 py-16 text-center sm:px-16 sm:py-24" data-reveal>
        <h2 class="mx-auto max-w-[18ch] text-4xl leading-[1.05] tracking-[-0.03em] text-balance text-shade-ink sm:text-5xl">{{ $heading }}</h2>
        <p class="mx-auto mt-6 max-w-[46ch] text-lg/8 text-pretty text-shade-muted">{{ $body }}</p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $ctaLink }}" class="rounded-full bg-accent px-6 py-3.5 text-[15px] font-medium text-accent-ink transition-opacity duration-200 hover:opacity-85">{{ $ctaText }}</a>
            <a href="{{ $secondaryLink }}" class="rounded-full border border-shade-line px-6 py-3.5 text-[15px] font-medium text-shade-ink transition-colors duration-200 hover:bg-shade-line">{{ $secondaryText }}</a>
        </div>
    </div>
</section>
BLADE

cat > "$R/views/components/sections/cta.yml" <<'YML'
title: Closing call to action
description: A dark band with a statement and one primary action
category: cta
fields:
    heading:
        type: text
        label: Heading
        default: "Start on the free plan today"
    body:
        type: textarea
        label: Supporting copy
        rows: 2
        default: "No card, no call. Import your first project and see the difference this afternoon."
    ctaText:
        type: text
        label: Primary button text
        default: "Start free"
    ctaLink:
        type: url
        label: Primary button link
        default: "/pricing"
    secondaryText:
        type: text
        label: Secondary button text
        default: "Talk to us"
    secondaryLink:
        type: url
        label: Secondary button link
        default: "mailto:hello@example.com"
YML

cat > "$R/views/pages/index.blade.php" <<BLADE
<!--
    The homepage. Served at "/". A list of sections: each tag pulls a component
    from resources/views/components/sections/ and fills its props with these
    attributes. In Visual mode you can select, reorder, and edit them on the canvas.
-->
<x-layouts.main title="Home" description="Rewrite this description for the product.">

    <x-sections.cta />

</x-layouts.main>
BLADE

cat > "$R/views/pages/404.blade.php" <<BLADE
<x-layouts.main title="Page not found">
    <section class="flex min-h-[70vh] items-center px-6 pt-32 pb-24">
        <div class="mx-auto w-full max-w-6xl">
            <p class="font-mono text-[11px] tracking-widest text-faint uppercase">404</p>
            <h1 class="mt-4 text-5xl leading-[1.02] tracking-[-0.03em] text-balance sm:text-6xl">That page has moved on.</h1>
            <p class="mt-6 max-w-[40ch] text-lg/8 text-muted">The link is stale or the page never existed. The homepage is a click away.</p>
            <a href="/" class="mt-10 inline-flex rounded-full bg-ink px-6 py-3.5 text-[15px] font-medium text-canvas transition-opacity duration-200 hover:opacity-85">Back to the homepage</a>
        </div>
    </section>
</x-layouts.main>
BLADE

cat > "$P/js/main.js" <<'JS'
/*
    __NAME__ — the interaction layer.

    Everything here is a progressive enhancement. With JavaScript off nothing
    is hidden and every link works; the matching transitions live in
    resources/css/site.css. Reduced motion short-circuits the reveals.
*/
document.documentElement.classList.add('js');

var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

headerState();
dropdowns();
mobileMenu();
markCurrentMenuItem();
reveals(document);

/* Transparent at the top; glass once content scrolls underneath (site.css #header rules). */
function headerState() {
    var header = document.getElementById('header');
    if (!header) return;
    function evaluate() { header.toggleAttribute('data-scrolled', window.scrollY > 8); }
    evaluate();
    window.addEventListener('scroll', evaluate, { passive: true });
}

/*
    Nav dropdowns. Hover opens after a short intent delay and closes after a
    longer one, so diagonal travel into the panel never slams it shut. Click
    toggles for touch; Escape and outside clicks dismiss; focus holds it open.
*/
function dropdowns() {
    var roots = document.querySelectorAll('[data-dropdown]');

    function closeOne(root) {
        root.classList.remove('is-open');
        var t = root.querySelector('[data-dropdown-trigger]');
        if (t) t.setAttribute('aria-expanded', 'false');
    }

    roots.forEach(function (root) {
        var trigger = root.querySelector('[data-dropdown-trigger]');
        var openTimer = null;
        var closeTimer = null;

        function open() {
            clearTimeout(closeTimer);
            roots.forEach(function (other) { if (other !== root) closeOne(other); });
            root.classList.add('is-open');
            if (trigger) trigger.setAttribute('aria-expanded', 'true');
        }

        root.addEventListener('pointerenter', function (e) {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(closeTimer);
            openTimer = setTimeout(open, 50);
        });
        root.addEventListener('pointerleave', function (e) {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(openTimer);
            closeTimer = setTimeout(function () { closeOne(root); }, 180);
        });
        if (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                root.classList.contains('is-open') ? closeOne(root) : open();
            });
        }
        root.addEventListener('focusin', open);
        root.addEventListener('focusout', function (e) {
            if (!root.contains(e.relatedTarget)) closeOne(root);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') roots.forEach(closeOne);
    });
    document.addEventListener('pointerdown', function (e) {
        roots.forEach(function (root) { if (!root.contains(e.target)) closeOne(root); });
    });
}

/* The mobile sheet: .menu-open on <html> shows it and locks scroll. */
function mobileMenu() {
    var toggle = document.querySelector('[data-mobile-toggle]');
    var panel = document.querySelector('[data-mobile-panel]');
    if (!toggle || !panel) return;
    var html = document.documentElement;

    function set(open) {
        html.classList.toggle('menu-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle.addEventListener('click', function () { set(!html.classList.contains('menu-open')); });
    panel.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    window.matchMedia('(min-width: 64rem)').addEventListener('change', function (e) { if (e.matches) set(false); });
}

/* aria-current on the nav link matching the page. */
function markCurrentMenuItem() {
    var path = window.location.pathname.replace(/\/$/, '') || '/';
    document.querySelectorAll('#header nav a[href]').forEach(function (a) {
        var href = a.getAttribute('href').replace(/\/$/, '') || '/';
        if (href === path) a.setAttribute('aria-current', 'page');
    });
}

/* Scroll reveals: flip .is-visible once; anything above the fold shows at once. */
function reveals(root) {
    var targets = root.querySelectorAll('[data-reveal]');
    if (reduceMotion || !('IntersectionObserver' in window)) {
        targets.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    targets.forEach(function (el) {
        if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
            el.classList.add('is-visible');
        } else {
            observer.observe(el);
        }
    });
}
JS
sed -i '' "s/__NAME__/$NAME/g" "$P/js/main.js"

cd "$T" && git init -q -b main && git add -A && git commit -q -m "Scaffold $NAME" && echo "scaffolded $T"
