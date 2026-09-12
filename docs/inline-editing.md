# Inline editing on the canvas

Hover a heading in the editor canvas and it tells you which field produced it. Click it and
you type into the page itself. No section is annotated to make this work — Studio derives the
whole editing surface from the Blade source it already compiles.

The `.yml` contract stays the model. Inline editing is a **second projection of it onto the
canvas**, beside the inspector rather than instead of it, and every write lands through the
same `EditorPanel` methods the inspector uses. Studio still never owns your markup.

## The four layers

**1. Scan** — `src/Services/Inline/`. `EchoScanner` walks a section's Blade source once with a
three-state HTML machine (`TEXT` / `TAG` / `ATTR`) and returns an `EchoRef` for every echo of a
declared field. It never evaluates anything, and it is deliberately conservative: an expression
it does not recognise produces no reference rather than a wrong one. `Instrumenter` turns those
refs into woven source.

**2. Render** — `SectionRenderer::render()` / `renderHtml()` take `bool $instrument = false`.
**Default off.** Exactly two callers pass `true`: `StudioController::iframe()` (the canvas
document) and `RenderController` (live re-renders into it). The draft preview, picker
thumbnails, block previews and the site runtime are provably unaffected, and `SiteWriter`
writes Blade source rather than rendered HTML, so a sentinel can never reach a page file.

**3. Map** — `StudioFields` in `resources/js/studio.js`, iframe-side. It walks the rendered
sentinels into live `Range`s and elements, rebuilt per section after every `paint()`. A field's
box comes from `Range.getBoundingClientRect()`, which is what lets a halo hug one of three
fields sharing a single `<h1>`.

**4. Edit** — `StudioPreview`, plus `EditorPanel` on the server. Selection tiers, chrome, inline
`contenteditable`, and the commit.

## What gets woven in

```
text echo    {{ $headingStart }}
          →  <!--sf:headingStart@41-->{{ $headingStart }}<!--/sf-->

repeater     {{ $p['name'] }}      (inside @foreach ($people as $p))
          →  <!--sf:people.{{ $loop->index }}.name@22-->…<!--/sf-->

attribute    <img src="{{ $image }}" alt="{{ $imageAlt }}">
          →  <img data-sf-attr="src:image@57;alt:imageAlt@57" …>

toggle       @if ($showRating) <div class="pill">
          →  @if ($showRating) <div data-sf-when="showRating@31" …>

undeclared   {{ $eyebrow }}       (echoed but absent from the .yml)
          →  <!--sf?:eyebrow@18-->…<!--/sf-->
```

The number after `@` is the **line in the section source**. That is what powers ⌥-click.

Comments were chosen over wrapper elements deliberately: they cost nothing in layout, cascade
or selector matching, so `box-decoration-clone`, flex children, `:first-child` and `+` all
behave exactly as they do live. They also act as barriers to `Text.normalize()`, which is why
editing one field in a shared `<h1>` cannot invalidate its siblings' ranges.

**Two hard safety rules.** `data-sf-attr` / `data-sf-when` are never injected into an `<x-…>`
tag — that would become a component prop and change behaviour. And a scanner throw falls back
to the uninstrumented source: a section that confuses the scanner loses inline editing, never
its render.

## `editabilityOf()` — the gate

`StudioPreview.editabilityOf(entry, sectionId)` returns `'edit' | 'select' | 'code'`, and
**both the hover path and the click path consult it**. Every affordance goes through it.

| Situation | Verdict | What the canvas does |
|---|---|---|
| no binding, declared `text`/`textarea`, plain text host | `edit` | edits in place |
| bound `site.*` | `edit` | edits in place — `saveSiteValues()` genuinely persists these |
| bound `collections.*` | `select` | selects only; rows belong to the Content panel |
| bound `php:` / `blade:` | `code` | grey "Set in code" halo, not interactive |
| declared `image`/`url`/`select`/`colorpicker` | `select` | its own affordance opens |
| an echo rendering an element (`{!! $icon !!}` → `<svg>`) | `select` | never editable — `innerText` would be empty and wipe it |
| an undeclared echo (`sf?:`) | `code` | dashed halo + "Add as a field" |

**Why this matters:** `EditorPanel::saveVariables()` does `array_diff_key($variables, $bindings)`,
so a write to a bound key is silently discarded — the user types, sees it, blurs, gets "Saved",
and it reverts on the next render. The gate is what prevents that.

**This is the single most error-prone part of the feature.** Five separate times during
development a gate was consulted with the wrong or missing input — never because the gate was
wrong, always because of what was passed in. If you add an affordance, route it through
`editabilityOf` and pass the section id that the *hit* resolved to, not the one from the DOM
ancestor.

## One writer for the overlay

`paintHalo()` is a pure **read** that computes geometry and builds a job. `queuePaint(job)`
holds a single pending job behind a one-frame `requestAnimationFrame` guard. `flushPaint()` is
the **only** function that assigns style, class or text to `#studio-fhalo`, `#studio-fchip` or
`#studio-cursor`.

`cursor.track()` keeps its own rAF, because it writes one `transform` at pointer frequency.
That is the only sanctioned exception.

New interactive chrome — `#studio-control`, the repeater item toolbar, the canvas toast —
owns its own element and lifecycle. **The chip is `pointer-events: none` and must stay so**: it
would otherwise block the hover it describes, which is why no control can live inside it.

## The caret rule

`paint()` does `el.innerHTML = markup`. Running it while someone is typing destroys the caret,
so the whole design is arranged around that:

| | inspector | canvas |
|---|---|---|
| per keystroke | `x-on:input` pushes to the iframe and re-renders | **nothing** — the canvas already shows the truth |
| on blur | `wire:model.blur` persists | `studio:field-committed` → `studio:set-field` → persists |

So: **no network request while typing, exactly one Livewire round trip per field edit.**
`paint()` skips the section holding the caret and stashes the markup in `pendingMarkup`,
applying it in `teardownEdit()`.

`EditorPanel::setFieldFromCanvas()` deliberately does **not** dispatch `studio:to-iframe` —
echoing the value back would repaint over the caret. Every other type *wants* the repaint and
uses `setVariable()` instead.

## Affordances by type

- **text / textarea** — inline `contenteditable`, caret placed at the click via
  `caretRangeFromPoint`, falling back to collapse-to-end. Enter commits (`text`) or inserts a
  newline (`textarea`); Escape reverts and is a true no-op.
- **image** — click opens the media Library; drop a file on it to upload and swap.
- **url** — a **link popover**, not a separate target: `at()` returns the smallest rect
  containing the point, so a link's `href` entry is shadowed by its own text (measured at 39 of
  42 links on Monarch's home page). Selecting the link's text opens the label editor *and* the
  destination popover together.
- **toggle** — click the governed element to switch it **off**, with an Undo toast. It can only
  be switched off: when false the element is not rendered, so there is nothing to click.
  Turning one back on stays an inspector action. Note `at()` deliberately skips `when` entries
  (a toggle governs a container and would shadow every field inside), so a toggle is offered
  only where the pointer is over its element but over no field and no item.
- **select / colorpicker** — a floating control. These types are usually used in comparisons
  (`@if ($align == 'left')`) or as class names rather than echoed, so they often have no
  sentinel and no reachable instance. The control is kept because that is data-dependent: a
  template echoing `{{ $variant }}` or `style="color: {{ $accent }}"` reaches it with no code
  change.
- **repeater items** — hover a row for flanking add-before/after, drag to reorder, delete with
  Undo. Not offered for a `collections.*`-bound repeater.

## Provenance (dev mode)

Every entry carries its source line, so the chip shows `hero:39` and **⌥-click opens Code mode
at that exact line**. The reverse works too: move the Monaco caret onto an echo and the canvas
scrolls to and haloes the text it renders.

This is the thing no other visual editor can do, because Studio owns the compiler.

## Promote an echo to a field (dev mode)

An echo with no `.yml` entry gets a dashed halo and an "Add `<key>` as a field" action, which
writes the `.yml` entry and the `@props` default.

`POST /studio/api/dev/components/{name}/field` — 404 unless dev mode, throttled, name and key
both pattern-validated, and the section resolved through `DesignSyncService::sourceFiles()`
rather than string concatenation. **Both files are validated before either is written**: the
YAML is re-parsed, and the rewritten `@props` array is checked with `token_get_all(…, TOKEN_PARSE)`
plus `PhpLiteral` when the original was a pure literal. If the key already exists in `@props`,
only the yml is written and the Blade is left byte-identical.

It writes to your source files, so it is held to a stricter bar than anything else here. Four
fix rounds went into it; the one that ended the churn was abandoning a hand-rolled scanner for
PHP's own tokenizer.

## Verifying it

This package has no test runner. `php artisan studio:inline:verify` (dev mode only) **is** the
test suite. Run it from the host app after any change to the scanner, the instrumenter or the
render path:

```bash
cd <host-app> && php artisan studio:inline:verify
```

It walks the installed site plus every clone in `storage/studio/templates` and checks three
things:

- **Coverage** — every declared field that resolves to a rendered position.
- **Inertness** — `strip(render(instrument(src))) === render(src)`, byte for byte. This is the
  load-bearing invariant: a sentinel must *describe* the page, never change it. A divergence
  prints the file, the byte offset and 90 characters either side.
- **Leak check** — sentinels appear only when instrumentation is requested.

The baseline to hold (62 sections, Monarch + the `monarch` and `pilot` clones):

```
Coverage: 365/369 fields mapped across 62 sections
Inertness: 57 identical, 0 diverged, 5 unrenderable, 577 sentinels rendered
  unrenderable: main, post, main, post, guide
```

The 4 unmapped fields are correct answers, not gaps: `split.align` / `split.visual` are selects
used only in comparisons, and `main.showFooter` (×2) is a toggle whose `@if` wraps `<x-footer>`.
The 5 unrenderable are layout files needing `$site`/`$slot` globals the bare harness cannot
supply.

Also worth a `curl` after render-path changes — the draft preview and the live page must both
contain zero `sf:` or `data-sf-`.

## Things that bit us, so you don't have to find them again

- **Hit-testing returns the smallest rect containing the point.** That is right (nested field
  beats container), but it means an `<a>`'s href is shadowed by its text, and an image's own
  entry can be shadowed by an overlay's text. Probe an individual rect's centre, never a
  wrapped entry's union-box centre — the union centre can land on a neighbour entirely.
- **Section scoping.** Resolving the hit by DOM ancestry alone misses a field whose rect covers
  the point from another section; `tierAtPoint()` walks `elementsFromPoint` as a fallback. It
  must not *replace* the ancestor-first attempt, which is what protects against occluded
  entries (a collapsed nav menu still has laid-out rects over the hero).
- **Animated sections exist** (Pilot's `logos` marquee). Never cache a rect across frames.
- **A repeater rendered by two `@foreach` loops** over the same field merges both DOM copies
  into one group, so the item's common ancestor can climb to the whole section. Those items are
  detected and get no controls.
- **The canvas iframe loads `studio.js` only, never `studio.css`** — anything that renders
  inside the frame needs its styles in `iframe.blade.php`'s pushed `<style>` block.
- **`DataBag::wrap([])` is an object, and objects are truthy.** Unrelated to inline editing but
  found while building it: `@if ($link->children ?? false)` is always true on the canvas and
  false on the live site, so a nestable repeater row with no children renders differently in
  the editor than on the published page. Visible in Pilot's `nav` and `footer`.

## Deliberately not supported

- Editing markup structure. There is no inline class editor, no style panel, no element
  insertion. Studio does not own the DOM and this does not change that.
- Values computed in `@php`, built by string manipulation, or passed across an `<x-…>`
  boundary. They stay panel-only and show the honest `</>` state.
- Nested repeater children (`nestable: true`). The scanner resolves one loop level; a canvas
  item delete removes the parent row *with* its children, and nested rows get no item box.
