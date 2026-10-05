# Section code in the inspector, with a live canvas and an Elements tree

2026-10-04

## Goal

A developer edits a section's source where they edit its fields — in the right
column, beside the section it draws — and watches the canvas follow every
keystroke. The dev-mode code modal goes away.

Decided with Tony:

- The Elements tree shows **the Blade source**, not the rendered DOM.
- Saving is **explicit** (⌘S). The canvas previews the unsaved buffer.
- In the tree, **any attribute value and any text** can be edited in place.
  Adding/removing attributes, deleting and reordering nodes are out of scope.

## What the developer sees

The inspector header's `</>` button is a toggle (`aria-pressed`, accent-tinted
while on; tooltip "Edit code" / "Back to fields"). It exists only while
`$store.studio.developer`.

**Fields** (off) — the inspector as it is today.

**Code** (on) — the same column, the same header, and under it a 36px toolbar:

```
[ Elements | Blade | YAML ]                 ⌖ pick   ● Unsaved   Save ⌘S
```

- **Blade / YAML** — Monaco, one instance, a model per file (undo survives the
  switch). The whole file: `@props`, comments, everything.
- **Elements** — the source as a tree, described below.
- **⌖ pick** (Elements only) — click an element on the canvas to reveal its row.
- **Unsaved** — shown while the buffer differs from disk. **Save** writes both
  files (the existing `PUT api/dev/components/{name}`), then
  `studio:files-changed` + `studio:code-saved`, as the modal did.
- A status line at the foot: the file path, or the render/YAML error in red
  with its line. The canvas keeps its last good render while an error stands.

The surface follows the Code view's look (`--color-code`, Monaco's themes), so
the column drops `.s-light` while code is showing — the same exception the
Code view already makes.

**Width.** Code has its own remembered width: `studio.code-panel-width`,
420–960px (never more than 60% of the window), default 560. `rightWidth` /
`setRightWidth` pick it while code is showing; the column's existing width
transition animates the change.

**Entry points.** The header toggle; the canvas toolbar's `···` → Edit code;
the context menu's Edit code; `studio:open-code` from the canvas. All call
`$store.studio.openSectionCode(sectionId)`: open the inspector on that section
(isolating it, as Edit does) with code showing. The choice of Fields/Code and
of the tab is remembered (`studio.inspector-code`, `studio.code-tab`), so a
developer who works in code stays in code from section to section.

**Leaving with unsaved edits.** Switching back to Fields keeps the buffer (and
the canvas keeps previewing it; the toggle carries the unsaved dot). Anything
that would drop the buffer — Done, Esc, ×, selecting another section, leaving
the Design view, a page switch — asks once, in the panel: *Save · Discard ·
Cancel*. Discard clears the override and re-renders from disk. `beforeunload`
guards a reload, as the Code view does.

**Shortcuts.** ⌘S saves while the panel has focus. Global section shortcuts
stand down while focus is inside the panel (the role `Studio.codeModalOpen`
played, renamed `Studio.codeFocus`). Esc inside Monaco or an in-place edit
belongs to that control first.

## Live preview

The canvas renders sections on the server (`POST api/render`). A buffer is
previewed by **overriding a section's source for the canvas**:

- Editor → iframe: `studio:source-override { ref, html, yaml }`, debounced
  120ms after an edit; `{ ref, clear: true }` on save/discard.
- `StudioPreview.sourceOverrides[ref]` holds it; every section on the page with
  that ref is re-queued through the existing `render()` pipeline (coalescing,
  in-flight re-queue, `paint`).
- `flushRenders()` adds `source: { html, yaml }` to a section whose ref is
  overridden. `RenderController` accepts `sections.*.source` **only when
  `DevMode::enabled()`** (otherwise ignored), parses the YAML for the fields
  (through the same translation `DesignSyncService` applies), resolves the
  posted variables over those fields' defaults, and renders with
  `SectionRenderer::renderHtml(..., instrument: true)`.
- Errors: a YAML parse failure or a Blade compile/render exception comes back
  as `errors[id] = { message, line? }` instead of markup. The iframe leaves the
  section as it is and posts `studio:source-error` / `studio:source-ok` to the
  editor, which shows it in the status line (and as a Monaco marker when the
  line is known). The dashed red "failed to render" box is not painted for an
  overridden section.

**Tailwind.** The canvas already runs Tailwind's browser build over the site's
CSS; it compiles a class the moment it appears in the DOM. `max-w-[300px]` →
`max-w-[100px]` needs nothing more than the swap.

**Instant class edits.** While a `class` value is being edited in the tree and
the source value is static (no `{{`, `@`, `<?`), each keystroke is applied to
the matching canvas elements directly (`studio:node-attr`), preserving classes
the page's own scripts added (those present on the element but absent from the
previous source value). The debounced server render then confirms it. Dynamic
values wait for the render.

## The Elements tree

**One parser, in the browser.** `resources/js/section-source.js` (new, pure, no
DOM) turns Blade text into a tree:

- nodes: `element` (name, attributes with exact value offsets, open/close
  ranges, void/self-closing), `component` (`<x-…>`), `text`, `echo`
  (`{{ }}`/`{!! !!}`), `directive` (`@if (…)`, `@foreach`, `@endif`… as flat
  rows that indent their body), `comment`;
- lossless offsets, so an edit is a splice of the buffer, never a re-print;
- skips the insides of comments, `@verbatim`, `@php`, `<script>`, `<style>`
  (shown as one collapsed row);
- tolerant: unclosed or stray tags produce a tree anyway (a half-typed tag in
  the Blade tab must not blank the Elements tab).

**Mapping tree ↔ canvas.** Before a buffer is sent for preview, the same module
writes ` data-sn="<n>"` after the name of every plain HTML element (never an
`<x-…>` component, never inside skipped regions); `n` is the element's index in
document order. No newlines are added, so the inline-editing sentinels' line
numbers stay true. The rendered canvas therefore carries, on every element, the
index of the source tag that produced it — a tag inside `@foreach` maps to all
of its copies. When code is showing with no edits yet, the override is sent
once with the on-disk source so the markers are present. Markers exist only in
overridden canvas renders: never on disk, never in the draft preview.

- Hover a row → `studio:node-hover { ref, n }` → the canvas outlines every
  `[data-sn=n]` in the edited section with the DevTools-style overlay (content
  box tint, tag·classes + size tooltip on the first one). Leaving clears it.
- Click a row → selects it, scrolls the first match into view.
- Pick → the existing element-select machinery with a `source` purpose: the
  click answers `studio:node-picked { n }`; the tree expands to and selects the
  row.
- Double-click an element on the canvas while code is showing does the same.

**Rows.** Monospace 12px, Monaco's token colours (tag, attribute name, value,
directive, comment, text). Collapsed by default below depth 3; inline when an
element holds only short text (`<h1 class="…">{{ $heading }}</h1>`). Disclosure
triangles; ↑ ↓ ← → move and fold; Enter edits the first attribute; the selected
row is tinted across the full width like DevTools.

**Editing in place.** Double-click an attribute value or a text/echo row: it
becomes a single-line-growing input holding the exact source text (so
`{{ $loop->first ? 'a' : 'b' }}` is edited as written). Typing previews live
(above). Enter or blur commits — a splice at that node's offsets, as one edit
on the Blade Monaco model (`pushEditOperations`), so ⌘Z undoes it in either
tab. Esc cancels and restores the preview. Tab commits and moves to the next
attribute value. A value containing the attribute's quote character is refused
with a shake and a status-line note.

Class values render as separate tokens so the eye can find one; the edit is
still the whole value.

## Structure

One buffer per section: the Blade and YAML Monaco models. Everything else
derives from them.

| Unit | Where | Does |
|---|---|---|
| `section-source.js` | `resources/js/` (bundled into `studio.js`, exposed as `Studio.sectionSource`) | `parse(text)`, `mark(text, tree)`, `splice` helpers. Pure. |
| `sectionCode` Alpine component | `resources/views/partials/section-code.blade.php` (new), included by `home.blade.php` inside the `inspector` slot | The panel: load, tabs, Monaco models, dirty state, save, discard prompt, override dispatch, error line. |
| Elements tree | same partial, `x-for` over a flattened visible-rows list | Rows, folding, keyboard, in-place edit, hover/select messages. |
| `$store.studio` | `home.blade.php` | `inspectorCode`, `codePanelWidth`, `openSectionCode()`, `sectionCodeDirty`, close-guard hook in `closeInspector`. |
| `StudioPreview` | `studio.js` (iframe side) | `sourceOverrides`, `source` in render payload, node hover overlay, `node-attr`, pick-for-source. |
| `RenderController` | PHP | `sections.*.source` (dev mode), `errors`. |
| `editor-panel.blade.php` | Livewire view | The header button becomes the toggle; the fields body hides (`x-show`) while code shows, so Livewire state is untouched. |

Monaco editors and models are kept in a plain object beside the component
(as `studioCodeBuffers` is), never inside `x-data` or a store — Alpine's proxy
hangs on them; only serialisable state (tab, dirty, error, the tree's rows) is
reactive.

The panel lives outside the Livewire component (a sibling in the inspector
slot, shown over the fields area) so a Livewire morph never touches Monaco.
It learns the section from `studio:selection-changed` / the header toggle's
detail (`ref`, `title`, `sectionId`).

Removed: the modal markup and its Alpine component in `home.blade.php`,
`Studio.codeModalOpen`, the `studio:open-code-editor` event (replaced by
`openSectionCode`).

After save: `studio:code-saved` makes `EditorPanel` re-sync and reload fields;
the override is cleared so the canvas shows what is on disk; the Code view's
open tabs re-read through `studio:files-changed`. If the file changed on disk
while the buffer was clean (Assistant turn, Code view save), the panel reloads
it; if the buffer was dirty, it keeps it and says so in the status line.

## Verification

No test suite in the package; verify in the host app's `/studio`:

- `section-source.js`: a Node script (`bin/section-source-check.mjs`) parses
  every section of the installed site and of `templates/starter`, asserts
  `mark()` then strip is byte-identical to the input, and that every splice
  round-trips.
- In Chrome: toggle, width, tabs; type `max-w-[300px]` in Blade and in the tree
  and watch the heading reflow; break the Blade and the YAML and see the error
  line with the canvas unchanged; hover/pick both ways including a `@foreach`
  row; unsaved prompt on Done/Esc/select-another; ⌘S then reload shows the
  file; fields still edit and render after returning from code; light and dark
  themes; developer mode off shows no toggle.
- `php artisan studio:inline:verify` still passes (no sentinel or marker
  reaches a non-canvas render).

Docs: update the package `CLAUDE.md` (Dev mode paragraph, Views, Frontend) and
`docs/authoring-sections.md` where it mentions the modal.

## As built (2026-10-04)

Differences from the above:

- The override is debounced 60ms (the canvas's own render queue adds ~90ms).
- Class values are drawn as one value, not as separate tokens.
- A render error carries a line only for YAML; Blade errors report the message.
- A close Livewire has already made (a deleted section) cannot be asked about:
  the buffer is kept and its preview stays until it is saved or discarded.
- The right column's resize seam moved into the gutter (`.s-gutter-seam`).
- Global shortcuts stand down through `Studio.codeFocus` only for Esc and ⌘Z;
  ⌘S saves the section from anywhere while code is showing.
