# Authoring sections

A **section** is a pair of files with the same basename inside `resources/views/designer/<category>/`:

```
resources/views/designer/heroes/hero-split.html   ← the Blade-ish template
resources/views/designer/heroes/hero-split.yml    ← metadata + editable fields
```

Studio discovers every `.yml` file recursively, pairs it with its `.html` twin, and syncs it
into the component library (`storage/studio/components/library/<name>.json`). The `name` key in
the YAML — not the filename — is the component's identity, so it must be globally unique.

## The YAML file

```yaml
name: hero-split            # unique, kebab-case — never change once shipped
title: Split hero           # shown in the editor
description: Headline left, product image right
category: heroes            # one of the canonical categories (see below)
tags: [hero, image, landing]

fields:
    heading:
        type: text
        label: Heading
        default: "Plan work without the busywork"

    show_badge:
        type: toggle
        label: Show badge
        default: true

    tone:
        type: select
        label: Background tone
        default: white
        options:
            white: White
            tinted: Tinted

    image:
        type: image
        label: Image
        default: "https://picsum.photos/seed/hero-split/1200/900"

    bullets:
        type: repeater
        label: Bullet points
        add_button_label: Add bullet
        sub_fields:
            text:
                type: text
                label: Text
        default:
            - text: "Unlimited projects"
            - text: "Role-based permissions"
```

**Field types:** `text`, `textarea`, `url`, `select`, `toggle`, `colorpicker`, `image`, `repeater`.

**Repeater sub-field types:** `text`, `textarea`, `url`, `image`, `select`.

A repeater may set `nestable: true` (+ `max_depth: 2`) to allow one level of child items —
used for nav dropdowns and footer link columns. Children live in `$item['children']`.

A repeater may also declare `source: collections.<name>`. When a section is added to a page
and that collection exists in the site, the repeater starts **bound** to it: its rows come
from the collection (edited in the Content panel) instead of the instance, and the inspector
shows a locked "Bound to …" panel with Unbind. Any repeater can be bound or unbound later
from the inspector. Bindings live on the page/layout/block instance as
`"bindings": {"items": "collections.guides"}` and are honoured by every render path.

`preview_variables` (optional, top-level) overrides field defaults for the picker preview and
for the starting content when a user adds the section. Usually the field `default`s are enough —
make them excellent, because they *are* the demo content.

`fixed: true` (optional, top-level) marks a section whose root element is `position: fixed`
(sticky headers). It renders pinned on the live site, but the editor canvas shows it **in place**
with a "Fixed" tag on its name chip so the page stays easy to work on. Only use it when the
section's root really is fixed.

**Canonical categories** (display order): `banners`, `headers`, `heroes`, `logos`, `features`,
`stats`, `content`, `gallery`, `testimonials`, `pricing`, `faq`, `team`, `blog`, `contact`,
`newsletter`, `cta`, `footers`.

## The HTML file — Blade

Every render path — the canvas, the live page, the draft preview, and Blade exports —
compiles sections with Laravel's own Blade engine, so anything Blade accepts works here.
There is no reduced subset to stay inside any more.

### The common shapes

```blade
{{ $heading ?? 'Fallback copy' }}          {{-- escaped echo (use for all text) --}}
{!! $logo_svg !!}                           {{-- raw echo (svg/html fields only) --}}

@if($show_badge) … @else … @endif           {{-- toggle a block --}}
@if($plan === 'pro' && $seats > 5) … @endif {{-- comparisons and nesting are fine --}}

@foreach($items as $item)                   {{-- repeater loop --}}
    {{ $item['title'] }}  {{-- or $item->title; both resolve --}}
    {{ $loop->index }}
    @foreach($item['children'] as $child) {{ $child['text'] }} @endforeach
@endforeach
```

Repeater rows answer to array access *and* property access, so `$item['title']` and
`$item->title` are the same value. Studio's own sections use the first; sections imported
from a site-template repository use the second.

### Also available

- **`@props`** at the top of the file, exactly as an anonymous component would declare it.
  Props with no matching field still get their defaults.
- **`$loop`**, `@php`, `@include`, `@elseif`, ternaries, and method calls.
- **Nested `<x-… />` components**, resolved the way Laravel resolves any anonymous
  component. A template import copies its own supporting components into
  `resources/views/components/studio-templates/<template>/` and rewrites their tags to match.
- **`$site`** — the site-wide document (company name, nav links, socials), injected into
  every section unless the section declares a field of its own by that name.

### Still worth avoiding

- Blade echoes **inside Alpine attributes** (`x-data="{ open: {{ … }} }"`) — quoting breaks.
- Echoing one variable inside another field's default.
- Anything that reaches for application state a section cannot assume exists: the database,
  the authenticated user, named routes.

Alpine.js attributes (`x-data`, `x-show`, `@click`, …) are fine for interactivity
(mobile menus, accordions) — they pass through untouched. Use `x-show` with an inline
`style="display: none"` rather than `x-cloak` so hidden panels never flash.

Always keep whitespace (or a tag boundary) before a directive — Blade does not recognize
one glued to a word character (`ON@else`).

Use HTML comments in sections, not `{{-- --}}`, when the comment should survive into the
rendered page.

## Design language

Sections from different categories get stacked on one page — they must feel like one product.

- **Layout:** `mx-auto w-full max-w-6xl px-6 lg:px-8` container (max-w-7xl for wide grids),
  section rhythm `py-20 sm:py-28`. Root element is `<section>` (`<header>`/`<footer>` where
  semantically right).
- **Palette:** white / `neutral-50` surfaces, `neutral-950` headings with `tracking-tight`,
  `neutral-500`/`600` body copy. Dark sections use `neutral-950` backgrounds. Keep accent color
  usage rare and purposeful (emerald checks, amber stars).
- **Type:** eyebrows `text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400`;
  h2 `text-3xl font-semibold tracking-tight sm:text-4xl`; sentence case everywhere.
- **Buttons:** primary `rounded-xl bg-neutral-900 px-6 py-3 text-sm font-medium text-white
  shadow-sm transition hover:bg-neutral-800`; secondary same but
  `border border-neutral-200 text-neutral-700 hover:bg-neutral-50` (no bg).
- **Cards:** `rounded-2xl border border-neutral-200/70 bg-white` or `bg-neutral-50` tiles.
  Shadows only where elevation means something.
- **Copy:** write real, specific draft copy. No “Elevate”, “Unleash”, “Seamless”, “Next-gen”,
  no Lorem Ipsum, no “John Doe”, no round fake numbers (`99.99%` → `47.2%`). Use varied,
  realistic names and organic figures.
- **Images:** `https://picsum.photos/seed/<unique-slug>/<w>/<h>` for scenery/screens,
  `https://i.pravatar.cc/144?img=<n>` for faces (vary `n` per person). Every image an editor
  should replace belongs in an `image` field.
- **Icons:** inline SVG, `stroke-width="1.5"`, consistent within a section.
- Responsive first: verify grids collapse cleanly at `sm`/`md`. Never use `h-screen`.

## Checklist before shipping a section

1. Every piece of copy a marketer would want to change is a field.
2. Field defaults read like a real product’s page, not a template.
3. Renders correctly on its own, with no application state behind it.
4. Looks right at 390px, 768px, and 1280px.
5. `name` is unique; `category` is canonical; `title`/`description` are human.
