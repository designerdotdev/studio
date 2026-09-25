# Authoring sections

A **section** is an anonymous Blade component of the installed site with a `.yml` contract
beside it:

```
resources/designer/views/components/sections/hero.blade.php   ← the component
resources/designer/views/components/sections/hero.yml         ← what the editor may change
```

Studio treats any component under `resources/designer/views/components` that ships a `.yml`
as a section (except `layouts/`, which are document shells, and `blocks/`, which holds global
blocks) and syncs it into the section library. Its identity comes from its path: pages use it
as `<x-sections.hero />`, and the library names it `sections-hero`. Components without a
`.yml` (`<x-icon>`, a card a section composes) are supporting parts — usable anywhere, but not
offered in the Add Section picker.

The same files are what the site's runtime renders, so a section never has two versions: what
you write here is exactly what the live page serves, with or without Studio installed.

## The Blade file

It is an ordinary anonymous component. Declare every editable value as a prop, with the same
default the `.yml` gives it:

```blade
@props([
    'heading' => 'Plan work without the busywork',
    'showBadge' => '1',
    'items' => [],
])
<section class="px-6 py-24">
    @if ($showBadge)
        <p class="eyebrow">New</p>
    @endif

    <h2 class="text-4xl font-semibold tracking-tight">{{ $heading }}</h2>

    @foreach ($items as $item)
        <p>{{ $item->title }}</p>
    @endforeach
</section>
```

Anything Blade accepts works — `$loop`, nested conditionals, `@php`, nested `<x-…>`
components — because every render path (the editor canvas, the draft preview, the live site)
compiles it with Laravel's own engine.

**Shared data.** `$site` (from `resources/designer/data/site.json`) and every collection (from
`resources/designer/data/collections/<name>.json`, as `$<name>`) are available in every page
and component without being passed. Rows are objects: read them as `$item->title`.

## The YAML file

```yaml
title: Split hero                 # shown in the editor
description: Headline left, product image right
category: heroes                  # optional — otherwise guessed from the file name
fields:
    heading:
        type: text
        label: Heading
        default: "Plan work without the busywork"

    showBadge:
        type: toggle
        label: Show badge
        default: "1"

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
        default: "/designer/images/hero.jpg"

    items:
        type: repeater
        label: Points
        source: collections.features
        sub_fields:
            title:
                type: text
                label: Title
```

Field keys are the prop names — keep their case (`showBadge`, `ctaText`).

**Field types:** `text`, `textarea`, `url`, `select`, `toggle`, `colorpicker`, `image`,
`repeater` (the site-templates spellings `number`, `richtext`, `color`, and `boolean` are
accepted too). **Repeater sub-field types:** `text`, `textarea`, `url`, `image`, `select`.
A repeater may set `nestable: true` for one level of child items.

**`source:`** says where a field's data lives when the section is added to a page:

- `source: collections.features` — the repeater starts **bound** to that collection: the page
  writes `:items="$features"`, its rows are edited in the Content panel, and the inspector
  shows a locked "Bound to …" panel with Unbind.
- `source: site.menu_primary` — the field is **site-wide**: the page writes
  `:primary="$site->menu_primary"`, and editing it in the inspector edits
  `resources/designer/data/site.json`, so every section that reads the same key follows.

`fixed: true` (optional) marks a section whose root element is `position: fixed` (a sticky
header): the canvas shows it in place with a "Fixed" tag so the page stays easy to work on.

## How a page records a section

Studio writes what the editor does back into the page file, as the template itself would:

```blade
<x-layouts.main title="Pricing" description="Plans for every team.">

    <x-sections.hero heading="Know what a run costs" :items="$plans" />

    {{-- <x-sections.stats :items="$stats" /> --}}

    <x-blocks.closing-cta />

</x-layouts.main>
```

- A value equal to the field's default is not written; one you set is (`heading="…"`).
- A hidden section is commented out.
- A global block is its own component, `views/components/blocks/<slug>.blade.php`, and pages
  place it with `<x-blocks.<slug> />`.
- A repeater with its own rows is written as a PHP literal (`:items="[(object) ['title' => …]]"`).
- An attribute that is code (`:count="count($posts)"`) is shown in the inspector as "set in
  code" and kept exactly as written.

Only what changed is rewritten: every other tag, attribute, comment, and blank line in the
file stays byte for byte, and comments above a section travel with it when it moves.

## How your Blade decides what is editable on the canvas

Studio derives inline editing from the source you write — nothing is annotated — so a few
authoring choices decide what a marketer can touch directly on the page. See
[inline-editing.md](inline-editing.md) for the mechanics.

**Echo a field and it becomes editable.** A plain `{{ $heading }}` in element content is
editable in place. `src="{{ $image }}"` opens the media Library. `href="{{ $link }}"` gets a
link popover alongside the label's own editor.

**A field with no echo has no canvas presence.** A `select` used only in a comparison
(`@if ($align == 'left')`) or as a class name renders no value, so there is nothing to hover —
it stays panel-only. That is correct behaviour, not a gap, but it is worth knowing when you
choose between a `select` that switches a layout and one that prints a label.

**Keep a field's echo in text, not markup.** `{!! $icon !!}` that renders an `<svg>` is
detected and made non-editable, because committing it would read an empty `innerText` and wipe
the value. If a field is meant to be edited as text, echo it as text.

**A toggle's `@if` should wrap a plain element, not a component tag.** Studio marks the element
the `@if` opens so the toggle can be switched off from the canvas, but it never annotates an
`<x-…>` tag — that would become a component prop. `@if ($showFooter) <x-footer />` therefore
gets no canvas affordance; wrap it in a `<div>` if you want one.

**Bound fields are not canvas-editable, by design.** A field bound to `collections.*` selects
but refuses free text (its rows live in the Content panel), and one bound to `php:`/`blade:`
shows a grey "Set in code" halo. `site.*` bindings *are* editable, since they persist to the
site document.

**Prefer one `@foreach` per repeater.** Rendering the same repeater through two loops (a
marquee that duplicates its row for seamless scrolling, say) merges both copies into one group,
and those rows get no item controls.

## Still worth avoiding

- Blade echoes **inside Alpine attributes** (`x-data="{ open: {{ … }} }"`) — quoting breaks.
- Anything that reaches for application state a section cannot assume exists: the database,
  the authenticated user, named routes.
- Always keep whitespace (or a tag boundary) before a directive — Blade does not recognise
  one glued to a word character (`ON@else`).

Use HTML comments in sections, not `{{-- --}}`, when the comment should survive into the
rendered page.

## Checklist before shipping a section

1. Every piece of copy a marketer would want to change is a field, with the same default in
   `@props` and in the `.yml`.
2. Field defaults read like a real product's page, not a template.
3. Renders correctly on its own, with no application state behind it.
4. Looks right at 390px, 768px, and 1280px.
