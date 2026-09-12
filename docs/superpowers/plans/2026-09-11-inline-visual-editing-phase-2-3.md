# Inline Visual Editing — Phases 2 & 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the inline editing surface — in-place affordances for every remaining field type (Phase 2), and repeater item operations on the canvas (Phase 3).

**Architecture:** v1 built the field map (`StudioFields`), three-tier selection, and inline text editing. This plan adds the per-type affordances that v1 deliberately deferred. Every one of them routes through the **same gate** v1 ended on — `StudioPreview.editabilityOf()` — and the **same single overlay writer** — `paintHalo` (read) → `queuePaint` → `flushPaint` (write).

**Tech Stack:** PHP 8.2+ / Laravel 11+, Livewire 3, Alpine, vanilla JS in `resources/js/studio.js`, Tailwind 4 in `resources/css/studio.css`, Vite.

**Spec:** `docs/superpowers/specs/2026-09-11-inline-visual-editing-design.md` — §10 defines these phases. Read it before Task 1.

**Predecessor plan:** `docs/superpowers/plans/2026-09-11-inline-visual-editing.md` (v1, complete, `134b17a..5a35057`).

## Global Constraints

These bind every task. They are the accumulated result of twelve fix rounds in v1 — violating one re-opens a bug that has already been fixed once.

- **`editabilityOf(entry, sectionId)` is THE gate.** It returns `'edit' | 'select' | 'code'` and both the hover path (`resolveHover`) and the click path (`select`) consult it. Every new affordance must respect it: a `php:`/`blade:`-bound field is never interactive, a `collections.*`-bound field selects but never takes a canvas-side value. Extend the gate; never bypass it.
- **One overlay writer.** `paintHalo()` is a pure read that builds a job; `queuePaint(job)` holds one pending job with a one-frame rAF guard; `flushPaint()` is the only function that assigns style/class/text to `#studio-fhalo`/`#studio-fchip`/`#studio-cursor`. New chrome either goes through that queue or is its own element with its own lifecycle — never a second writer on the existing overlay.
- **The chip is `pointer-events: none`** and must stay that way (it would otherwise block the hover it describes). Any interactive control is a **separate element**, not something mounted inside the chip. The spec's phrase "switch on the chip" means visually adjacent, not a child of it.
- **`paint()` must never run while the caret is in that section.** The suppression guard plus `pendingMarkup` stash is load-bearing; leave it intact.
- **Non-text writes use the existing `EditorPanel::setVariable($sectionId, $key, $value)`.** Unlike text, these types *need* the re-render — `setVariable` persists AND dispatches `studio:to-iframe`, which is correct here. Do not add a no-echo variant for them; that was only needed to protect the caret.
- **Repeater sub-field writes use `updateRepeaterSubField($sectionId, $fieldKey, $index, $subField, $value, bool $push = true)`.** Pass `push: true` (the default) for non-text affordances — they want the repaint.
- **Backend gate must not regress:** `cd <host-app> && php artisan studio:inline:verify` exits 0 with `Coverage: 365/369 fields mapped across 62 sections` and `Inertness: 57 identical, 0 diverged, 5 unrenderable, 577 sentinels`.
- **After editing `resources/js` or `resources/css`, run `npm run build` and commit `dist/`.** `dist/` is tracked, and the build also publishes to the host's `public/vendor/studio`, which takes precedence — keep them in sync.
- **Preview mode stays completely inert.** No new chrome, no new interactivity.
- **Never put a Monaco editor or model inside an Alpine store or `x-data`** (documented repo gotcha).
- End every commit message with:
```
Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX
```

## Interfaces v1 produced (verified to exist)

**`StudioFields`** (iframe-side, `resources/js/studio.js`): `maps`, `paths`, `contracts`, `init(paths, contracts)`, `index(wrapper)`, `indexAll()`, `parsePath(raw)`, `entriesFor(id)`, `rects(entry)`, `box(entry)`, `at(id,x,y)`, `itemAt(id,x,y)`, `refFor(id)`, `sourceFor(id)`, `contractFor(id,key)`, `typeFor(id,entry)`, `isTextRange(range)`.
Entry = `{path, key, index, subKey, line, kind, range, el, attribute?, textHost}`; `kind` is `'text' | 'attr' | 'when'`.

**`StudioPreview`**: `selection {tier, sectionId, path, key, index}`, `bindings`, `variables`, `editabilityOf(entry, sectionId)`, `tierAt(id,x,y)`, `tierAtPoint(x,y,skip)`, `resolveHover(target,x,y)`, `paintHalo(hit,kind,event)`, `queuePaint(job)`, `flushPaint()`, `clearHover()`, `cursor` (with `show/hide/track/glyphs`), `cursorKind(hit,kind)`, `selectField(entry,id)`, `selectItem(item,id)`, `walkUp()`, `beginEdit(entry,id,multiline,x,y)`, `commitEdit()`, `cancelEdit()`, `applyFieldValue(id,entry,value)`, `siblingIds(id)`, `render(id)`, `paint(id,markup)`, `highlightLine(source,line)`, `post(type,payload)`.

**`EditorPanel`** (PHP): `setVariable`, `setFieldFromCanvas`, `addRepeaterItem`, `removeRepeaterItem`, `moveRepeaterItem`, `updateRepeaterSubField(…, bool $push = true)`, `indentRepeaterItem`, `outdentRepeaterItem`, `removeRepeaterChild`.

**Editor-window helpers:** `window.Studio.mediaPick()` → Promise resolving to a URL or null (lives in the **editor** window; the canvas must reach it via a postMessage round trip). `Studio.upload(file, {url, csrf})`.

## Facts established by v1 that shape this work

1. **`StudioFields.at()` deliberately skips `when` entries** (`if (entry.kind === 'when') continue;`) because a toggle governs a large container and returning it would shadow every field nested inside. Consequence: **toggles are currently unreachable by hover**, and v1 removed `cursorKind`'s toggle branch as dead code. Task 3 must add reachability by a different route — not by removing that skip.
2. **A canvas toggle can only switch OFF.** When a toggle is false its governed element is never rendered, so there is nothing to hover. Turning one back on stays an inspector action. Do not invent a canvas placeholder for markup the section deliberately omitted.
3. **`cursorKind()` currently returns `text` for a `select`-verdict field**, so the badge implies typing where clicking won't type. Phase 2 makes the cursor tell the truth (Task 2).
4. **A repeater rendered by two `@foreach` loops over the same field** (Pilot's `logos` marquee) merges both copies into one `key.index` group, so the item box can balloon to the whole section. Task 5 must tolerate this rather than assume one DOM group per row.
5. **Animated sections exist** (that same marquee). Anything that caches a rect across frames will be wrong there.

---

# Phase 2 — the remaining field types

### Task 1: The canvas action bridge, and image fields

**Files:**
- Modify: `resources/js/studio.js` (`StudioPreview`: `fieldAction()`; `StudioEditor.listenToIframe()`: new cases)
- Modify: `resources/views/iframe.blade.php` (image affordance CSS + drop styling)

**Interfaces:**
- Consumes: `editabilityOf`, `StudioFields.at`, `typeFor`, `queuePaint`.
- Produces:
  - postMessage `studio:field-action` `{sectionId, key, index, subKey, type, action}` (canvas → editor)
  - postMessage `studio:field-value` `{sectionId, key, index, subKey, value}` (editor → canvas, after the editor resolves a value)
  - `StudioPreview.fieldAction(entry, sectionId, action)` — the single place a non-text field asks the editor to do something
  - `StudioPreview.applyIncomingValue(payload)` — writes the resolved value and triggers the re-render

This task builds the bridge **and** its first consumer (images) so it ships something testable rather than plumbing alone.

- [ ] **Step 1: Write the failing check**

Manual gate: in the canvas console, `Studio.preview.fieldAction` is `undefined`. That is the failing state.

- [ ] **Step 2: Add the bridge**

In `StudioPreview`, add:

```js
    /**
     * A non-text field asking the editor window to resolve a value.
     *
     * Text fields edit in place; every other type needs something the
     * canvas iframe cannot host — the media library, an upload, a colour
     * input. So the canvas posts the request, the editor resolves it, and
     * the value comes back through `studio:field-value`.
     */
    fieldAction(entry, sectionId, action) {
        if (this.mode === 'preview') return;
        if (this.editabilityOf(entry, sectionId) === 'code') return;

        this.post('studio:field-action', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            type: StudioFields.typeFor(sectionId, entry),
            action,
        });
    },

    /** The editor resolved a value for a non-text field. */
    applyIncomingValue({ sectionId, key, index, subKey, value }) {
        if (value === null || value === undefined) return;

        const entry = { key, index, subKey };

        for (const id of this.siblingIds(sectionId)) {
            this.applyFieldValue(id, entry, value);
            this.render(id);
        }
    },
```

Add the receive case to `StudioPreview.init()`'s message switch:

```js
                case 'studio:field-value':
                    this.applyIncomingValue(data);
                    break;
```

In `StudioEditor.listenToIframe()`, add the editor half:

```js
                case 'studio:field-action':
                    this.resolveFieldAction(data);
                    break;
```

And the method on `StudioEditor`:

```js
    /**
     * Resolve a canvas field action in the editor window, then persist it
     * and hand the value back to the canvas.
     *
     * Unlike a text edit, these types want the repaint — so this goes
     * through EditorPanel::setVariable / updateRepeaterSubField, which
     * persist AND echo to the iframe.
     */
    async resolveFieldAction({ sectionId, key, index, subKey, type, action }) {
        let value = null;

        if (action === 'pick-media') {
            value = await window.Studio.mediaPick();
        }

        if (value === null || value === undefined) return;

        if (index !== null && subKey) {
            window.Livewire?.dispatch('studio:set-repeater-sub-field', { sectionId, fieldKey: key, index, subField: subKey, value });
        } else {
            window.Livewire?.dispatch('studio:set-field-value', { sectionId, key, value });
        }

        this.send('studio:field-value', { sectionId, key, index, subKey, value });
    },
```

- [ ] **Step 3: Add the Livewire entry points**

In `src/Livewire/EditorPanel.php`, beside `setFieldFromCanvas`:

```php
    /**
     * A non-text field resolved on the canvas (an image pick, a colour, a
     * select). Unlike {@see setFieldFromCanvas()} these types want the
     * repaint, so this goes through setVariable(), which echoes to the
     * iframe as well as persisting.
     */
    #[On('studio:set-field-value')]
    public function setFieldValueFromCanvas(string $sectionId, string $key, $value): void
    {
        if (!isset($this->variables[$sectionId])) {
            return;
        }

        $this->setVariable($sectionId, $key, $value);
    }

    #[On('studio:set-repeater-sub-field')]
    public function setRepeaterSubFieldFromCanvas(string $sectionId, string $fieldKey, int $index, string $subField, $value): void
    {
        if (!isset($this->variables[$sectionId])) {
            return;
        }

        $this->updateRepeaterSubField($sectionId, $fieldKey, $index, $subField, (string) $value);
    }
```

- [ ] **Step 4: Wire images to it**

In `StudioPreview.select()`'s field branch, after the `editability === 'edit'` text case, add the image case. An image entry is `kind === 'attr'` with `attribute` of `src`/`srcset`, or a field whose declared type is `image`:

```js
                    } else if (this.isImageField(hit.entry, sectionId)) {
                        this.fieldAction(hit.entry, sectionId, 'pick-media');
                    }
```

And the predicate beside `editabilityOf`:

```js
    isImageField(entry, sectionId) {
        if (entry.kind === 'attr' && (entry.attribute === 'src' || entry.attribute === 'srcset')) return true;

        return StudioFields.typeFor(sectionId, entry) === 'image';
    },
```

- [ ] **Step 5: Drop-to-upload**

Add to `iframe.blade.php`'s head styles:

```css
            .studio-drop-target {
                outline: 2px dashed #4c7dfa !important;
                outline-offset: -2px;
            }
```

In `StudioPreview.init()`, after the other document listeners:

```js
        // Drop an image file straight onto an image field
        document.addEventListener('dragover', (event) => {
            if (this.mode === 'preview') return;
            if (!event.dataTransfer?.types?.includes('Files')) return;

            const hit = this.imageHitAt(event.clientX, event.clientY);
            if (!hit) return;

            event.preventDefault();
            this.markDropTarget(hit);
        });

        document.addEventListener('dragleave', () => this.markDropTarget(null));

        document.addEventListener('drop', (event) => {
            if (this.mode === 'preview') return;

            const hit = this.imageHitAt(event.clientX, event.clientY);
            this.markDropTarget(null);

            if (!hit) return;

            const file = event.dataTransfer?.files?.[0];
            if (!file) return;

            event.preventDefault();
            this.post('studio:field-upload', {
                sectionId: hit.sectionId,
                key: hit.entry.key,
                index: hit.entry.index,
                subKey: hit.entry.subKey,
            });
            this.pendingUpload = { hit, file };
        });
```

The file itself cannot cross `postMessage` usefully here, so the canvas hands the editor the *target* and the editor opens its own file input — **or** simpler and preferred: read the file in the iframe as a `File` and post it directly; `postMessage` does structured-clone `File` objects fine in same-origin frames. Use the direct route:

```js
            this.post('studio:field-upload', {
                sectionId: hit.sectionId,
                key: hit.entry.key,
                index: hit.entry.index,
                subKey: hit.entry.subKey,
                file,
            });
```

and on the editor side add:

```js
                case 'studio:field-upload':
                    this.uploadFieldFile(data);
                    break;
```

```js
    async uploadFieldFile({ sectionId, key, index, subKey, file }) {
        try {
            const url = await window.Studio.upload(file, {
                url: window.__studioUploadUrl,
                csrf: document.querySelector('meta[name=csrf-token]').content,
            });

            if (index !== null && subKey) {
                window.Livewire?.dispatch('studio:set-repeater-sub-field', { sectionId, fieldKey: key, index, subField: subKey, value: url });
            } else {
                window.Livewire?.dispatch('studio:set-field-value', { sectionId, key, value: url });
            }

            this.send('studio:field-value', { sectionId, key, index, subKey, value: url });
        } catch (e) {
            window.Studio.toast(e.message || 'Upload failed', 'error');
        }
    },
```

`window.__studioUploadUrl` must be exposed in `home.blade.php` beside the other `window.__studio*` values — add it there, using the same `route('studio.api.upload')` the image field partial uses.

Add the two helpers to `StudioPreview`:

```js
    imageHitAt(x, y) {
        for (const wrapper of document.querySelectorAll('[data-section]')) {
            const sectionId = wrapper.dataset.section;
            const entry = StudioFields.at(sectionId, x, y);

            if (!entry) continue;
            if (this.editabilityOf(entry, sectionId) === 'code') continue;
            if (!this.isImageField(entry, sectionId)) continue;

            return { sectionId, entry };
        }

        return null;
    },

    markDropTarget(hit) {
        document.querySelectorAll('.studio-drop-target').forEach((el) => el.classList.remove('studio-drop-target'));

        const el = hit?.entry?.el;
        if (el) el.classList.add('studio-drop-target');
    },
```

- [ ] **Step 6: Verify**

1. `node --check resources/js/studio.js`
2. `npm run build` clean
3. `php artisan studio:inline:verify` — `Coverage: 365/369`, `Inertness: 57 identical, 0 diverged, 5 unrenderable, 577 sentinels`
4. Grep showing `fieldAction`, `applyIncomingValue`, `isImageField`, `imageHitAt`, `markDropTarget`, `resolveFieldAction`, `uploadFieldFile`
5. Grep showing the two new `#[On(...)]` methods on `EditorPanel`
6. Grep showing `window.__studioUploadUrl` exposed in `home.blade.php`

- [ ] **Step 7: Commit**

```bash
cd <host-app>/packages/designer/studio
git add resources/js/studio.js resources/views/iframe.blade.php resources/views/home.blade.php src/Livewire/EditorPanel.php dist
git commit -m "Pick and drop images straight on the canvas

Adds the canvas->editor action bridge every non-text field type needs —
the iframe cannot host the media library — and wires images to it:
click an image to open the Library, or drop a file onto it to upload
and swap in place. Bound fields are gated by editabilityOf as usual.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 2: Link, select and colour controls — and an honest cursor

**Files:**
- Modify: `resources/js/studio.js` (`StudioPreview.control` — a small popover controller; `cursorKind`)
- Modify: `resources/views/iframe.blade.php` (popover markup + CSS)

**Interfaces:**
- Consumes: `fieldAction` (Task 1), `editabilityOf`, `typeFor`, `contractFor`.
- Produces: `StudioPreview.control.open(kind, entry, sectionId, box)` / `.close()`; `cursorKind` returning `'url' | 'select' | 'color'` for the matching declared types.

The chip is `pointer-events: none`, so this is a **separate** floating element (`#studio-control`) with its own show/hide — not a second writer on the halo overlay.

- [ ] **Step 1: Add the popover element and styles**

`iframe.blade.php`, head styles:

```css
            .studio-control {
                position: fixed;
                z-index: 2147483007;
                display: none;
                align-items: center;
                gap: 6px;
                padding: 6px;
                border-radius: 8px;
                background: rgba(12, 12, 14, 0.96);
                border: 1px solid rgba(255, 255, 255, 0.12);
                box-shadow: 0 10px 28px -8px rgba(0, 0, 0, 0.5);
                font-family: ui-sans-serif, system-ui, sans-serif;
                font-size: 12px;
                color: #fff;
            }

            .studio-control.is-on { display: flex; }

            .studio-control input[type="text"],
            .studio-control select {
                height: 26px;
                min-width: 180px;
                padding: 0 8px;
                border-radius: 6px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: rgba(255, 255, 255, 0.06);
                color: #fff;
                font: inherit;
            }

            .studio-control input[type="color"] {
                width: 28px;
                height: 26px;
                padding: 0;
                border: none;
                background: none;
            }

            html.studio-preview .studio-control { display: none !important; }
```

And the element beside the halo/chip/cursor:

```blade
    <div class="studio-control" id="studio-control"></div>
```

- [ ] **Step 2: Add the controller**

```js
    /**
     * A small floating control for field types that need a widget rather
     * than typing: a link's href, a select's options, a colour swatch.
     *
     * Deliberately its own element with its own lifecycle — the chip is
     * pointer-events:none by design (it must never block the hover it
     * describes), so an interactive control cannot live inside it.
     */
    control: {
        el: null,
        entry: null,
        sectionId: null,

        mount() { this.el = document.getElementById('studio-control'); },

        open(kind, entry, sectionId, box, options) {
            if (!this.el) return;

            this.entry = entry;
            this.sectionId = sectionId;
            this.el.innerHTML = '';

            const commit = (value) => {
                StudioPreview.fieldValue(entry, sectionId, value);
                this.close();
            };

            if (kind === 'url') {
                const input = document.createElement('input');
                input.type = 'text';
                input.value = StudioPreview.currentValue(sectionId, entry) || '';
                input.placeholder = 'https://… or /path';
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); commit(input.value); }
                    if (e.key === 'Escape') { e.preventDefault(); this.close(); }
                });
                this.el.appendChild(input);
            }

            if (kind === 'select') {
                const select = document.createElement('select');
                for (const [value, label] of Object.entries(options || {})) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = label;
                    select.appendChild(opt);
                }
                select.value = StudioPreview.currentValue(sectionId, entry) || '';
                select.addEventListener('change', () => commit(select.value));
                this.el.appendChild(select);
            }

            if (kind === 'color') {
                const input = document.createElement('input');
                input.type = 'color';
                input.value = StudioPreview.currentValue(sectionId, entry) || '#000000';
                input.addEventListener('change', () => commit(input.value));
                this.el.appendChild(input);
            }

            this.el.classList.add('is-on');
            this.el.style.left = Math.max(8, box.left) + 'px';
            this.el.style.top = Math.max(8, box.top - 38) + 'px';
            this.el.querySelector('input,select')?.focus();
        },

        close() {
            this.entry = null;
            this.sectionId = null;
            this.el?.classList.remove('is-on');
            if (this.el) this.el.innerHTML = '';
        },
    },

    /** The value the canvas currently holds for a field. */
    currentValue(sectionId, entry) {
        const vars = this.variables[sectionId] || {};

        if (entry.index === null) return vars[entry.key];

        return (vars[entry.key] || [])[entry.index]?.[entry.subKey];
    },

    /** Persist a resolved non-text value (shared by the control and Task 1). */
    fieldValue(entry, sectionId, value) {
        for (const id of this.siblingIds(sectionId)) {
            this.applyFieldValue(id, entry, value);
            this.render(id);
        }

        this.post('studio:field-committed', {
            sectionId,
            key: entry.key,
            index: entry.index,
            subKey: entry.subKey,
            value,
            echo: true,
        });
    },
```

`studio:field-committed` already relays to `studio:set-field` in `StudioEditor`. Add an `echo` flag there so a non-text commit routes to `studio:set-field-value` (which repaints) rather than `studio:set-field` (which deliberately does not). Keep the text path exactly as it is.

Call `this.control.mount()` in `init()` beside `this.cursor.mount()`, and `this.control.close()` from `clearSelection()`.

- [ ] **Step 3: Open it from a click**

Extend `select()`'s field branch after the image case:

```js
                    } else {
                        const type = StudioFields.typeFor(sectionId, hit.entry);
                        const box = StudioFields.box(hit.entry);

                        if (box && (type === 'url' || type === 'select' || type === 'colorpicker')) {
                            this.control.open(
                                type === 'colorpicker' ? 'color' : type,
                                hit.entry,
                                sectionId,
                                box,
                                StudioFields.contractFor(sectionId, hit.entry.key)?.options
                            );
                        }
                    }
```

`options` must be present in the contract payload. In `StudioController::iframe()`'s `$componentContracts` builder, include `'options' => $config['options'] ?? null` alongside `label`, `type` and `sub_fields`.

Also: an `attr` entry whose `attribute === 'href'` is a link — treat it as `url` regardless of declared type.

- [ ] **Step 4: Make the cursor honest**

`cursorKind()` currently returns `'text'` for anything not image/url. Extend it so the badge matches what a click will actually do:

```js
    cursorKind(hit, kind) {
        if (kind === 'item') return 'item';
        if (kind === 'code') return 'code';

        const entry = hit.entry;

        if (entry.kind === 'attr') {
            if (entry.attribute === 'src' || entry.attribute === 'srcset') return 'image';
            if (entry.attribute === 'href') return 'url';
        }

        const type = StudioFields.typeFor(this.selection.sectionId, entry);

        if (type === 'image') return 'image';
        if (type === 'url') return 'url';
        if (type === 'select') return 'select';
        if (type === 'colorpicker') return 'color';

        return 'text';
    },
```

Re-add the `select` and `color` glyphs to `cursor.glyphs` (v1 removed them as unreachable; they are reachable now):

```js
            select: '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.2 7.7a1 1 0 0 1 1.4 0L10 11.1l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 9.1a1 1 0 0 1 0-1.4Z"/></svg>',
            color: '<svg viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="6"/></svg>',
```

**Important:** `cursorKind` is called from `paintHalo` (the read phase) where `this.selection.sectionId` may be stale. Pass the section id in explicitly rather than reading it off `selection` — change the signature to `cursorKind(hit, kind, sectionId)` and update `paintHalo`'s call site.

- [ ] **Step 5: Verify**

1. `node --check`
2. `npm run build` clean
3. `php artisan studio:inline:verify` unchanged
4. Grep showing `control:` with `open`/`close`/`mount`, `currentValue`, `fieldValue`
5. Grep showing `options` added to `$componentContracts` in `StudioController`
6. Grep showing `cursorKind(hit, kind, sectionId)` and both new glyphs

- [ ] **Step 6: Commit**

```bash
git add resources/js/studio.js resources/views/iframe.blade.php src/Http/Controllers/StudioController.php dist
git commit -m "Add link, select and colour controls to the canvas

A small floating control — its own element, since the chip is
pointer-events:none by design — opens on click for url, select and
colorpicker fields. The oversized cursor now names what a click will
actually do rather than always claiming text.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 3: Toggles

**Files:**
- Modify: `resources/js/studio.js` (`when`-entry reachability + switch control)
- Modify: `resources/views/iframe.blade.php` (switch styling)

**The problem to solve first.** `StudioFields.at()` skips `when` entries deliberately — a toggle governs a large container, and returning it would shadow every field nested inside. So toggles are unreachable by ordinary hover, and that skip must stay.

**The route in:** a `when` entry is reachable when the pointer is over the governed element **but over no field and no item** — i.e. exactly where `tierAt` currently answers `'section'` and the UI paints the grey "Set in code" halo. In that position, if the innermost `[data-sf-when]` ancestor of the pointed element exists, offer the toggle instead of the grey state.

Remember: **a canvas toggle can only switch OFF.** When false, the governed element is not rendered at all. Do not build an "off" placeholder.

- [ ] **Step 1: Add toggle resolution**

```js
    /**
     * The toggle governing the point, when nothing more specific is there.
     *
     * `StudioFields.at()` deliberately never returns a `when` entry — a
     * toggle governs a whole container and would shadow every field inside
     * it. So a toggle is only offered where the pointer is over its
     * governed element but over no field and no repeater item, which is
     * exactly where the grey "set in code" state would otherwise paint.
     */
    toggleAt(target, sectionId) {
        const el = target?.closest?.('[data-sf-when]');

        if (!el) return null;

        const wrapper = el.closest('[data-section]');

        if (!wrapper || wrapper.dataset.section !== sectionId) return null;

        const raw = el.getAttribute('data-sf-when');
        const at = raw.lastIndexOf('@');

        return { key: raw.slice(0, at), line: Number(raw.slice(at + 1)) || 0, el, index: null, subKey: null, kind: 'when' };
    },
```

- [ ] **Step 2: Offer it in the hover path**

In `resolveHover`, where the `'section'` tier currently resolves to the grey code halo, check for a toggle first and paint it as a field-tier hover with the toggle cursor. Guard it with `editabilityOf` (a `php:`-bound toggle stays grey).

- [ ] **Step 3: Offer it on click**

In `select()`, when nothing more specific was hit, open a switch control at the governed element's box:

```js
    openToggle(entry, sectionId, box) {
        const on = String(this.currentValue(sectionId, entry) ?? '1') !== '0';

        // The canvas can only turn a toggle OFF: when it is false the
        // governed element is not rendered, so there is nothing to hover.
        // Turning one back on stays an inspector action.
        if (!on) return;

        this.fieldValue(entry, sectionId, '0');
    },
```

Use your judgement on whether a confirm/undo affordance is warranted — hiding a whole block on a single click is destructive-feeling. A toast with an Undo that restores `'1'` matches the section-delete pattern already in the codebase (`studio:toast` supports `action: {label, dispatch}`). Prefer that.

- [ ] **Step 4: Re-add the toggle glyph** to `cursor.glyphs` and return `'toggle'` from `cursorKind` for a `when` entry (reachable now).

- [ ] **Step 5: Verify** — the usual three, plus greps for `toggleAt` and `openToggle`, plus a grep proving `StudioFields.at()` still skips `when` entries (that skip must NOT have been removed).

- [ ] **Step 6: Commit**

```bash
git add resources/js/studio.js resources/views/iframe.blade.php dist
git commit -m "Switch a section's toggles off from the canvas

A toggle is offered exactly where the pointer is over its governed
element but over no field and no item — the position that would
otherwise paint the grey 'set in code' state. at() still skips `when`
entries, so a toggle never shadows the fields nested inside it. Turning
one back ON stays an inspector action: when false the element is not
rendered, so there is nothing on the canvas to click.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_015ipmAu6GRkPd69yKxBdGBX"
```

---

### Task 4: Promote an echo to a field (dev mode)

**Files:**
- Create: nothing
- Modify: `src/Services/Inline/EchoScanner.php` (report undeclared echoes)
- Modify: `src/Http/Controllers/DevModeController.php` (new endpoint)
- Modify: `routes/web.php`
- Modify: `resources/js/studio.js`, `resources/views/iframe.blade.php` (dashed halo + action)

**Interfaces:**
- Produces: `EchoScanner::scanUndeclared(string $source, array $fields): array` (echoes of `$names` with no yml entry); `POST /studio/api/dev/components/{name}/field`; sentinel form `<!--sf?:eyebrow@18-->` for an undeclared echo.

The scanner already sees every echo; it currently discards those without a declared field. Emit them under a distinct sentinel so the canvas can offer to declare them — **without** letting them be edited (there is no field to write to yet).

- [ ] **Step 1:** Extend the scanner to emit undeclared echoes as `context: 'undeclared'` refs, and the Instrumenter to write them as `<!--sf?:key@line-->…<!--/sf-->`. Keep them out of `Coverage` counting — the baseline `365/369` must not move.
- [ ] **Step 2:** `StudioFields.index()` parses `sf?:` into entries with `kind: 'undeclared'`; `editabilityOf` returns `'code'` for them (never editable); the hover paints a **dashed** halo with the chip `Add "<key>" as a field`.
- [ ] **Step 3:** Clicking it (dev mode only) POSTs to the new endpoint, which appends the `.yml` field entry and the `@props` default, runs `SiteMirror::sync()`, and returns `synced: true`. The frontend then refreshes the preview and dispatches `studio:code-saved`, exactly as the dev-mode modal already does.
- [ ] **Step 4:** Validate hard on the server: the name must match `[a-zA-Z][a-zA-Z0-9_]*`, must not already exist in the yml, and the component must be a real section (`DesignSyncService::sourceFiles`). Reject anything else.
- [ ] **Step 5:** Verify the three gates, plus: create a throwaway section with an undeclared echo, promote it, and confirm both files changed and `studio:inline:verify` coverage rises by exactly one on both sides.
- [ ] **Step 6:** Commit.

---

# Phase 3 — repeater item operations

### Task 5: Add, reorder and delete repeater items on the canvas

**Files:**
- Modify: `resources/js/studio.js` (item toolbar + drag)
- Modify: `resources/views/iframe.blade.php` (item chrome CSS)
- Modify: `resources/js/studio.js` (`StudioEditor` relays)

**Interfaces:**
- Consumes: `StudioFields.itemAt`, `maps[id].items`, `editabilityOf`.
- Produces: postMessage `studio:item-action` `{sectionId, key, index, action}` where action is `add-before | add-after | remove | move`; relayed to the existing `EditorPanel::addRepeaterItem` / `removeRepeaterItem` / `moveRepeaterItem`.

The backend already exists — this is canvas UI over it.

- [ ] **Step 1:** On item hover, show flanking `+` buttons (before/after) and a small toolbar with delete, positioned from the item's box through the existing rAF write queue. They are interactive, so they are **their own elements**, not chip children.
- [ ] **Step 2:** Wire them to `studio:item-action`; relay in `StudioEditor` to the matching Livewire method. A `collections.*`-bound repeater must **not** offer these — the rows live in the Content panel, not the page doc. Gate on `editabilityOf` of any entry in that item, or on the binding for the repeater key directly.
- [ ] **Step 3:** Drag to reorder using the existing SortableJS helper if it can be applied to the item elements; otherwise a simple pointer-drag that computes the target index from the item boxes. Commit via `moveRepeaterItem`.
- [ ] **Step 4:** Handle the two-`@foreach` case (Pilot's `logos` marquee): items grouped across two DOM copies must not show two sets of controls, and the item box must not balloon to the whole section. Detect `items` whose common ancestor is the section wrapper itself and suppress the controls for those, with a note in the report.
- [ ] **Step 5:** Verify the three gates, plus greps for the new handlers, plus confirm nothing is offered in preview mode.
- [ ] **Step 6:** Commit.

---

## Final verification (both phases)

- [ ] `php artisan studio:inline:verify` — `Coverage: 365/369`, `Inertness: 57 identical, 0 diverged, 5 unrenderable, 577 sentinels`
- [ ] No sentinels escape: `curl -s <host>/studio/preview | grep -c 'sf:\|data-sf-'` → 0; same for the live page; `grep -rn 'data-sf-\|<!--sf' resources/designer/ | wc -l` → 0
- [ ] `public/vendor/studio/studio.js` sha1-matches `dist/studio.js`
- [ ] Every affordance respects `editabilityOf`: a `php:`-bound field offers nothing, a `collections.*`-bound field selects but never takes a canvas value
- [ ] Preview mode renders no new chrome and no new interactivity
