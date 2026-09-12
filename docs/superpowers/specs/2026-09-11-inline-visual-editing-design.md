# Inline visual editing: the canvas as an editing surface

Date: 2026-09-11. Approved scope: make the canvas directly editable — hover a
heading and see what field it is, click it and type into the page itself —
without the author annotating a single section. The field contract in the
`.yml` stays the model; inline editing is a second projection of it onto the
canvas, beside the inspector rather than instead of it.

The block architecture is unchanged and is the reason this is tractable.
Studio never owns the markup; the developer does. A section stays an opaque
Blade file with a typed field contract, and the editable surface is the
closed set that contract declares. Every write still lands in
`PageRepository`/`LayoutRepository`/`BlockRepository` through the same
`EditorPanel` methods the inspector uses.

## 1. Evidence

Two throwaway prototypes ran before this spec was written, over the installed
Monarch site plus the `monarch` and `pilot` clones in
`storage/studio/templates` — 62 sections, 369 declared fields.

**Coverage.** 365 of 369 declared fields (98.9%) have a discoverable position
in the rendered output. The 4 misses are three distinct cases, all of them
expected:

- `split.align` and `split.visual` (Pilot) — `select` fields that appear only
  inside comparisons (`@if ($align == 'left')`) and never echo a value. They
  have no rendered representation at all, so the inspector is their correct
  and only home.
- `main.showFooter` (counted twice — the same layout file in the installed
  site and in the `monarch` clone) — a toggle whose `@if` wraps an
  `<x-footer>` tag. Annotating a component tag would turn the marker into a
  component prop, so the scanner refuses by design (§3).

**Inertness.** For all 57 renderable sections,
`strip(render(instrument(src))) === render(src)` byte for byte, with 213
sentinels emitted. The 5 unrenderable files are layouts (`main`, `post`,
`guide`) that need `$site`/`$slot` globals the bare harness did not supply —
not scanner failures.

This is the load-bearing claim of the whole design and it is now measured, not
assumed. Section 9 turns the harness into a repeatable command.

## 2. Architecture

Four layers, each independently understandable:

1. **Scan** (PHP, `Services/Inline/`) — read a section's Blade source, locate
   every echo of a declared field, and weave in sentinels. Pure text in, text
   out; no rendering, no evaluation.
2. **Render** — `SectionRenderer` compiles the instrumented source. Canvas
   renders only; every other render path is untouched.
3. **Map** (JS, iframe) — walk the rendered DOM, turn sentinels into a
   `FieldMap` of live `Range`s and elements. Sentinels are read once per paint
   and then the DOM is the index.
4. **Edit** (JS, iframe + `EditorPanel`) — selection tiers, chrome, inline
   `contenteditable`, and the commit back through Livewire.

## 3. Scan: `Services/Inline/`

**`EchoScanner`** — a single left-to-right pass over the Blade source with a
three-state HTML machine (`TEXT` / `TAG` / `ATTR`), returning a list of
`EchoRef` DTOs. It never evaluates anything.

Skipped regions, where an echo is not editable markup: `{{-- --}}`, `<!-- -->`,
`@verbatim`, `@php … @endphp`, and `<script>`/`<style>` bodies. `@{{` is
recognised as an escaped literal.

Recognised expressions (conservative by design — an expression it does not
recognise produces no sentinel, never a wrong one):

| Source | Resolves to |
|---|---|
| `{{ $heading }}` | field `heading` |
| `{{ $heading ?? 'x' }}` | field `heading` |
| `{{ $item['title'] }}` inside `@foreach ($people as $item)` | `people.{index}.title` |
| `{{ $item->title }}` (DataBag object access) | `people.{index}.title` |
| `@if ($showRating)` where `showRating` is a `toggle` | governs the next opened element |

`EchoRef` carries `key`, `path` (the repeater-aware dotted path), `context`
(`text` \| `attr` \| `when`), `attribute`, byte `offset`, and `line`.

**`Instrumenter`** — takes the refs and returns the woven source. Edits are
collected as `(offset, text)` and applied in descending offset order so
positions stay valid.

```
text echo    {{ $headingStart }}
          →  <!--sf:headingStart@41-->{{ $headingStart }}<!--/sf-->

repeater     {{ $p['name'] }}
          →  <!--sf:people.{{ $loop->index }}.name@22-->{{ $p['name'] }}<!--/sf-->

attribute    <img src="{{ $image }}" alt="{{ $imageAlt }}">
          →  <img data-sf-attr="src:image@57;alt:imageAlt@57" src="…" alt="…">

toggle       @if ($showRating) <div class="pill">
          →  @if ($showRating) <div data-sf-when="showRating@31" class="pill">
```

Comments are chosen over wrapper elements deliberately: they cost nothing in
layout, cascade, or selector matching, so `box-decoration-clone`, flex
children, `:first-child` and `+` selectors all behave exactly as they do on
the live site.

**Two hard safety rules.** `data-sf-attr` and `data-sf-when` are never
injected into an `<x-…>` tag — that would become a component prop and change
behaviour. And a scanner throw is caught in `SectionRenderer`, which falls back
to the uninstrumented source: a section that confuses the scanner loses inline
editing, never its render.

Files: `src/Services/Inline/EchoScanner.php`, `EchoRef.php`, `Instrumenter.php`.

## 4. Render: where instrumentation is allowed

`SectionRenderer` gains a `bool $instrument = false` parameter, **default off**:

- `render(ComponentData $component, array $variables, array $bindings = [], bool $instrument = false)`
  — the field contract is already on `$component->fields`, so nothing else is
  needed.
- `renderHtml(string $html, array $variables, string $label = 'section', array $fields = [], bool $instrument = false)`
  — raw Blade has no component behind it, so the contract is passed in.

The canvas calls the second form, and today `StudioController::iframe()` builds
each `$sections[]` entry with `'html' => $component->html` but no fields; it
gains `'fields' => $component->fields` so `iframe.blade.php` can pass them
through.

Exactly two callers pass `$instrument: true`:

- `StudioController::iframe()` — the canvas document
- `RenderController::__invoke()` — live re-renders into that document

Everything else keeps the default and so is provably unaffected: the draft
preview (`page.blade.php`), section-picker thumbnails (`preview.blade.php`),
block previews, and the runtime, which never goes through `SectionRenderer` at
all. `SiteWriter` writes Blade source, never rendered HTML, so no sentinel can
reach a page file or a published site.

`__studioPreview` gains `paths: {ref: 'sections/hero'}` from
`ComponentData::path`, so the client can turn `hero@41` into
`resources/designer/views/components/sections/hero.blade.php:41` without
repeating the filename in every sentinel.

## 5. Map: `StudioFields` (iframe side of `studio.js`)

A sibling of `StudioPreview`, rebuilt for a section whenever `paint()` runs.

`index(sectionEl)` walks the section with a `TreeWalker` over comment and
element nodes and produces, per section:

- **field entries** — `{path, key, index, subKey, line, range}` where `range`
  is a live `Range` between the sentinel pair. Bounding boxes come from
  `Range.getBoundingClientRect()`, so a halo fits the text precisely even when
  it is one of three fields inside a single `<h1>`.
- **attribute entries** — `{key, attribute, line, el}` from `data-sf-attr`.
- **toggle entries** — `{key, line, el}` from `data-sf-when`.
- **item entries** — derived, not instrumented: entries sharing a
  `field.index` prefix are grouped and their nearest common ancestor element
  becomes the repeater item's box. This is what the orange `Person` chip and
  (later) the flanking `+` buttons attach to.

Anything not covered by an entry is, by definition, **set in code**. That is
the honest-uneditable state, and it needs no extra work to compute.

## 6. Selection: three tiers

`StudioPreview.selection` becomes `{tier, sectionId, path}` with `tier` one of
`section` \| `item` \| `field`. Edit mode behaviour:

- pointer over section chrome / padding → section halo, blue chip (today's
  behaviour, unchanged)
- pointer over a repeater item → orange halo + item chip
- pointer over a mapped field → tight blue halo + field chip carrying the
  field's label and, in dev mode, `hero:41` and a `</>` button
- click selects the tier under the pointer; **Esc walks up one tier** and only
  deselects from `section`. While a field is actively being typed into, Esc
  belongs to the editor (§7: revert and exit to `field`); the tier walk-up
  resumes on the next press.
- Preview mode is untouched: `StudioFields` does not index, no chrome renders,
  links navigate as they do today

The existing canvas→panel path still fires `studio:section-selected` for the
owning section, so the inspector opens exactly as it does now; a field
selection additionally posts `studio:field-selected`, which scrolls the
inspector to that input and highlights it.

**The oversized cursor** is one `<div>` in the iframe, positioned with
`translate3d` inside a rAF loop, offset +14/+14 from the pointer, carrying a
glyph per tier and type: `T` text, `▣` image, `↗` link, `●—` toggle, `▤`
repeater item, and a grey `</>` over anything with no field behind it. The
native cursor is kept (an I-beam over text is correct while editing), so the
badge reads as a type indicator rather than a cursor replacement.

Files: `resources/js/studio.js` (`StudioFields`, `StudioPreview.selection`),
`resources/views/iframe.blade.php` (chrome CSS, cursor element).

## 7. Edit: typing on the canvas

Activating a `text`/`textarea` field:

1. If the sentinel range is the entire content of its parent element (the
   common case, `<p>{{ $body }}</p>`), set `contenteditable="plaintext-only"`
   on the parent.
2. Otherwise wrap the range in a transient
   `<span data-sf-edit contenteditable="plaintext-only">`. Wrapping is safe
   here because it exists only while the caret is in it and is unwrapped on
   blur.
3. `text` fields commit on Enter and blur; `textarea` fields take Enter as a
   newline. Escape reverts to the value held before editing began and exits to
   the `field` tier without committing.

`plaintext-only` keeps pasted rich text out of a plain field. Where it is
unsupported the attribute falls back to `contenteditable="true"` plus a
`paste` handler that inserts `event.clipboardData.getData('text/plain')`;
either way the value read back is `innerText`, normalised for the `<br>`/`<div>`
line breaks contenteditable produces.

**The caret problem.** `paint()` does `el.innerHTML = markup`, which would
destroy the caret mid-keystroke. The fix follows the inspector's own
convention exactly, mirrored:

| | inspector today | canvas |
|---|---|---|
| per keystroke | `x-on:input` → pushes to iframe → re-render | nothing — the canvas already shows truth |
| on blur | `wire:model.blur` → persist | postMessage → `studio:set-field` → persist |

So `StudioPreview.suppressPaint` holds the section id being edited and
`paint()` skips it; per keystroke the client only updates
`StudioPreview.variables[id][key]` so a later re-render is correct. There is
**one Livewire round trip per field edit**, not per keystroke, and no render
request at all while typing.

`EditorPanel` gains `#[On('studio:set-field')] setFieldFromCanvas(string $sectionId, string $key, $value, ?int $index = null, ?string $subKey = null)`.
It writes into `$variables` and calls the existing `saveVariables()` (or
`updateRepeaterSubField()` for an item), so global blocks, layout sections,
`guardConflict()`, and site-bound fields all behave identically to a panel
edit for free. Unlike `setVariable()` it does **not** dispatch
`studio:to-iframe` — the canvas is already correct, and echoing would repaint
over the caret.

## 8. Developer hooks

**Bidirectional provenance.** Every entry carries a line. In dev mode the
field chip shows `hero:41`; ⌥-click, or the chip's `</>` button, posts
`studio:open-code-at {ref, line}`. The editor resolves the ref through
`paths` to an app-relative path, switches to Code mode, calls
`$store.code.openFile(path)`, then `studioCodeBuffers.editor.revealLineInCenter(line)`
and selects the line. The reverse uses Monaco's `onDidChangeCursorPosition`:
when the caret is inside an instrumented echo's line in a section file, the
split preview scrolls to and haloes the rendered text. This is the
demonstration that sells the feature — click a word on your page, land on the
line that produced it.

**Honest uneditable.** Falls out of section 5 for free: no entry means the
grey `</>` cursor and a muted "set in code" halo. Studio never pretends to own
markup it does not own.

**Assistant field path.** `studio:element-selected` gains `field`, `itemIndex`,
and `source` (`hero.blade.php:43`) resolved from the nearest enclosing entry.
`Assistant/SystemPrompt` states the exact field the user pointed at, so an
edit turn writes the right prop instead of inferring one from nearby text.

**Promote an echo to a field.** The scanner already sees echoes of names with
no `.yml` entry. In dev mode those get a dashed halo and an inline
"Add `eyebrow` as a text field" action, which posts to a new
`POST /studio/api/dev/components/{name}/field` (dev-mode gated, like the rest
of `DevModeController`). It appends the `.yml` entry and the `@props` default,
runs `SiteMirror::sync()`, and returns `synced: true`, after which the
frontend refreshes the preview and dispatches `studio:code-saved` — the same
contract the dev-mode modal already uses. This closes the blade↔yml loop that
is hand-maintained today. Phase 2 (section 10): it writes source files, which
is a materially larger surface than the rest of v1.

## 9. Verification

This package has no test runner, so verification follows the existing
convention: host-bootstrapping scripts, promoted here to a dev-only command.

`php artisan studio:inline:verify` (registered only when `DevMode::enabled()`)
walks the installed site plus every clone in `storage/studio/templates` and
reports, per section:

- **the inertness invariant** — `strip(render(instrument(src))) === render(src)`,
  byte for byte. Any divergence prints the file, the byte offset, and 90
  characters either side. This is the regression gate for every scanner
  change.
- **coverage** — declared fields, mapped fields, and the names of any that are
  unmapped, so a drop is visible immediately.

Baseline to hold: 62 sections, 57/57 renderable ones identical, 365/369 fields
mapped, 213 sentinels rendered.

Manual checks against the host (`php artisan serve`, `/studio`): type into a
heading that shares its `<h1>` with two other fields and confirm the caret
survives and the inspector updates on blur; edit a global block placed twice on
one page and confirm both copies update; confirm the draft preview
(`/studio/preview`) and a published page contain no `sf:` or `data-sf-`
anywhere; confirm Preview mode renders no chrome and indexes nothing.

## 10. Phasing

**v1 — foundation and text.** Sections 3–7 in full, plus provenance, the
honest-uneditable cursor, and the Assistant field path from section 8. Every
non-text field type is selectable and focuses its inspector input; repeaters
select at the item tier and edit in the panel.

**Phase 2 — the remaining types.** In-place affordances for image (Library
picker + drop-to-upload, reusing `Studio.mediaPick()`), url (link popover),
toggle (switch on the chip), select (dropdown), colorpicker (swatch). Plus
promote-an-echo-to-a-field.

**Phase 3 — repeater item operations.** Flanking `+` above/below, drag to
reorder, delete — the interaction in the second reference screenshot.

## 11. Out of scope

- Editing markup structure. Studio does not own the DOM and this does not
  change that; there is no inline class editor, no style panel, no element
  insertion.
- Values computed in `@php`, built by string manipulation, or passed across an
  `<x-…>` boundary. These stay panel-only and show the honest `</>` state. The
  measured cost is 4 fields in 369 (§1), none of which has a rendered position
  a user could point at in any case.
- Nested repeater children (`nestable: true`). The scanner resolves one loop
  level; a `@foreach ($link->children as $child)` inner loop is not mapped.
  Panel-only, revisit with Phase 3.
- Value tainting (the rejected Approach 2). It remains a clean, purely
  additive fallback if real-world coverage ever proves thinner than the
  measurement here suggests.
