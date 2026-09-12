# The Studio Dock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the editor's topbar, activity bar and permanent sidebar with one floating, draggable dock; open panels as anchored popovers or centred sheets; open the inspector only from the section toolbar.

**Architecture:** The layout (`components/layouts/app.blade.php`) renders the canvas full-window, then a scrim, a floating `<aside class="s-float">` that holds the unchanged Livewire panels, and the dock partial. All shell state lives in the `studio` Alpine store in `home.blade.php`; `rail` (which panel) and `sidebar` (a panel is open) keep their meaning, `dock {edge, along}` replaces `activityBar`. The canvas iframe gains one new postMessage, `studio:open-inspector`, and stops opening the panel on selection.

**Tech Stack:** Laravel Blade views, Livewire 3, Alpine.js 3, Tailwind 4 (`resources/css/studio.css`), the `studio.js` bundle (Vite), Playwright in the scratchpad for verification against the lab app on `http://localhost:8765`.

**Spec:** `docs/superpowers/specs/2026-09-12-studio-dock-design.md`

## Global Constraints

- No PHP changes to any Livewire component or service; the change is views, CSS, and `studio.js`.
- `Livewire.dispatch()` names and `studio:*` postMessage names are shared contracts (CLAUDE.md). The only new one is `studio:open-inspector`.
- After every CSS/JS edit run `npm run build` (the lab serves the worktree's `dist/`).
- Dock: 46px tall, 34px round buttons, 17px icons, 12px from the window edge, tokens `overlay`/`line`/`ink`.
- Popover: 320px (Assistant 380px), `max-height: min(70vh, available)`, 12px clamp. Sheet: `min(1100px, 88vw)` × `80vh` + scrim.
- `studio.sidebar` unset → closed. `studio.dock` JSON `{edge, along}`, default `{edge:'bottom', along:0.5}`.
- `studio:inline:verify` baseline must hold: Coverage 365/369, Inertness 57 identical / 0 diverged.

## Verification harness

Lab app: `<scratchpad>/lab` (Laravel 13, this worktree path-symlinked, Monarch installed, `php artisan serve --port=8765`). Playwright: `<scratchpad>/pw` (`node <script>.mjs`). Every "verify" step means: `npm run build` in the worktree, then run the named script; `localStorage['studio.mode']` is forced to `edit` in an init script because the first-run default is `preview`.

---

### Task 1: The store — dock placement, panel open/closed, frames

**Files:**
- Modify: `resources/views/home.blade.php:33-125` (the `Alpine.store('studio', {...})` block)

**Interfaces:**
- Produces: `dock {edge, along}`, `setDock(edge, along)`, `dockHidden`, `toggleDock()`, `filesOpen`, `toggleFiles()`, `openInspector()`, getter `frame` (`'popover' | 'sheet'`), getter `floatWidth` (px number). `sidebar` default false. `setRail(name, force)` toggles when `name` is already open and `force` is false. `setMode()` no longer touches the rail. `activityBar` / `setActivityBar` removed.

- [ ] **Step 1: Replace the sidebar/rail/activityBar members**

In the store, replace the block from `sidebar: localStorage.getItem('studio.sidebar') !== '0',` through the end of `setActivityBar(position) {...},` with:

```js
                    // A panel is open (the floating surface is showing). Off
                    // on first run: the site is the screen until you ask.
                    sidebar: localStorage.getItem('studio.sidebar') === '1',
                    toggleSidebar() {
                        this.sidebar = !this.sidebar;
                        localStorage.setItem('studio.sidebar', this.sidebar ? '1' : '0');
                    },
                    closePanel() {
                        if (!this.sidebar) return;
                        this.sidebar = false;
                        localStorage.setItem('studio.sidebar', '0');
                    },
                    // The rail: which panel the floating surface shows.
                    rail: (s => ['sections', 'pages', 'content', 'media', 'assistant'].includes(s) ? s : 'sections')(localStorage.getItem('studio.rail')),
                    setRail(name, force = false) {
                        // Every dock button toggles its own panel; a forced
                        // call (picker, palette, inspector) always opens it.
                        if (!force && this.rail === name && this.sidebar) {
                            this.closePanel();
                            return;
                        }
                        this.rail = name;
                        localStorage.setItem('studio.rail', name);
                        if (!this.sidebar) this.toggleSidebar();
                        window.dispatchEvent(new CustomEvent('studio:rail', { detail: { name } }));
                    },
                    // The inspector is the Sections panel in its selected
                    // state — the toolbar's Edit-fields button lands here.
                    openInspector() {
                        this.setRail('sections', true);
                    },
                    // Which frame the open panel takes: the two data screens
                    // are sheets, everything else a popover on the dock.
                    get frame() { return ['content', 'media'].includes(this.rail) ? 'sheet' : 'popover' },
                    get floatWidth() { return this.rail === 'assistant' ? 380 : 320 },

                    /* --- the dock ----------------------------------------
                       Where the floating bar sits: which window edge, and how
                       far along it (0..1, the dock's centre). Snaps on drag. */
                    dock: (() => {
                        try {
                            const saved = JSON.parse(localStorage.getItem('studio.dock') || 'null');
                            if (saved && ['bottom', 'left', 'right', 'top'].includes(saved.edge)) {
                                return { edge: saved.edge, along: Math.min(1, Math.max(0, Number(saved.along) || 0.5)) };
                            }
                        } catch (e) { /* fall through */ }
                        return { edge: 'bottom', along: 0.5 };
                    })(),
                    setDock(edge, along = null) {
                        if (!['bottom', 'left', 'right', 'top'].includes(edge)) return;
                        this.dock = { edge, along: along === null ? 0.5 : Math.min(1, Math.max(0, along)) };
                        localStorage.setItem('studio.dock', JSON.stringify(this.dock));
                        window.dispatchEvent(new CustomEvent('studio:dock', { detail: this.dock }));
                    },
                    dockHidden: localStorage.getItem('studio.dock-hidden') === '1',
                    toggleDock() {
                        this.dockHidden = !this.dockHidden;
                        localStorage.setItem('studio.dock-hidden', this.dockHidden ? '1' : '0');
                    },
                    // Code mode's file tree column (inside the code pane)
                    filesOpen: localStorage.getItem('studio.files') !== '0',
                    toggleFiles() {
                        this.filesOpen = !this.filesOpen;
                        localStorage.setItem('studio.files', this.filesOpen ? '1' : '0');
                    },
```

- [ ] **Step 2: Stop `setMode()` from touching the rail**

Delete these two lines inside `setMode(name)`:

```js
                        if (name !== 'code' && this.rail === 'files') this.setRail('sections', true);
```
(and its comment). Leave `toggleDevMode`, `theme`, `device`, `codeSplit` as they are.

- [ ] **Step 3: Remove the Code-mode file-tree hand-off**

`home.blade.php:1361` (inside `Alpine.store('code')`'s boot): delete `Alpine.store('studio').setRail('files', true);`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/home.blade.php
git commit -m "Store: dock placement, closed-by-default panel, toggling rail"
```

---

### Task 2: CSS — dock, floating surface, scrim, code column

**Files:**
- Modify: `resources/css/studio.css` — replace the `workspace` + `activity bar` sections (lines 334-505) and the `topbar` / `s-box-btn` / `s-logo-btn` / `s-panel-icon-bar` / `s-nav-btn` / `s-urlbar*` rules (lines 593-735). Keep `s-seg`, `s-canvas`, `s-frame`.

**Interfaces:**
- Produces classes: `s-stage`, `s-scrim`, `s-float`, `s-float.is-sheet`, `s-dock` (+ `is-vertical`, `is-hidden`, `is-dragging`), `s-dock-grip`, `s-dock-btn` (+ `is-active`, `is-menu`), `s-dock-sep`, `s-dock-seg`, `s-dock-seg-btn` (+ `is-active`, `is-edit`, `is-code`), `s-dock-publish`, `s-dock-status` (+ `is-saving`, `is-error`), `s-code-files`, `s-logo-btn` (kept for the menu cross-fade).

- [ ] **Step 1: Replace the workspace + activity bar section**

```css
    /* --- the stage: the site is the screen ---------------------------- */

    .s-stage {
        @apply relative min-h-0 min-w-0 flex-1 bg-canvas;
    }

    /* Behind a sheet: dims the site, click closes */
    .s-scrim {
        @apply fixed inset-0 z-20 bg-black/45;
        backdrop-filter: blur(2px);
    }

    /* --- the floating surface (holds the Livewire panels) ------------- */

    .s-float {
        @apply fixed z-30 flex flex-col overflow-hidden bg-panel;
        border-radius: 14px;
        box-shadow:
            0 0 0 1px var(--color-line),
            0 30px 70px -20px rgba(0, 0, 0, 0.7),
            0 8px 24px -10px rgba(0, 0, 0, 0.5);
        animation: s-float-in 160ms cubic-bezier(0.2, 0.8, 0.2, 1);
    }

    .s-float.is-sheet {
        top: 50%;
        left: 50%;
        width: min(1100px, 88vw);
        height: 80vh;
        transform: translate(-50%, -50%);
        border-radius: 16px;
    }

    @keyframes s-float-in {
        from { opacity: 0; transform: translateY(6px) scale(0.985); }
        to { opacity: 1; transform: none; }
    }

    .s-float.is-sheet { animation-name: s-sheet-in; }

    @keyframes s-sheet-in {
        from { opacity: 0; transform: translate(-50%, -50%) scale(0.985); }
        to { opacity: 1; transform: translate(-50%, -50%); }
    }

    /* --- the dock ----------------------------------------------------- */

    .s-dock {
        @apply fixed z-40 flex items-center gap-0.5 text-soft;
        height: 46px;
        padding: 5px 6px 5px 4px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--color-overlay) 94%, transparent);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        box-shadow:
            0 0 0 1px var(--color-line),
            inset 0 1px 0 var(--color-wash),
            0 20px 50px -20px rgba(0, 0, 0, 0.7),
            0 4px 14px -6px rgba(0, 0, 0, 0.5);
        transition: left 350ms cubic-bezier(0.2, 0.8, 0.2, 1), top 350ms cubic-bezier(0.2, 0.8, 0.2, 1), transform 350ms cubic-bezier(0.2, 0.8, 0.2, 1), opacity 200ms ease;
        user-select: none;
    }

    .s-dock.is-dragging { transition: none; }

    .s-dock.is-vertical {
        @apply flex-col;
        height: auto;
        width: 46px;
        padding: 4px 5px 6px;
    }

    /* Hidden: parked just off its edge, brought back by the hot strip */
    .s-dock.is-hidden { opacity: 0; pointer-events: none; }
    .s-dock-peek { @apply fixed z-40; }

    .s-dock-grip {
        @apply flex shrink-0 cursor-grab items-center justify-center rounded-md text-faint;
        width: 18px;
        height: 34px;
        touch-action: none;
    }
    .s-dock-grip:hover { @apply bg-wash text-soft; }
    .s-dock-grip:active { cursor: grabbing; }
    .is-vertical .s-dock-grip { width: 34px; height: 18px; }
    .is-vertical .s-dock-grip svg { transform: rotate(90deg); }

    .s-dock-btn {
        @apply relative flex shrink-0 cursor-pointer items-center justify-center rounded-full text-soft;
        width: 34px;
        height: 34px;
        transition: background-color 150ms ease, color 150ms ease, transform 150ms ease;
    }
    .s-dock-btn:hover { @apply bg-wash text-ink; }
    .s-dock-btn.is-active { @apply bg-wash-strong text-ink; }
    .s-dock-btn:active { transform: scale(0.94); }
    .s-dock-btn:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 1px; }
    .s-dock-btn.is-menu { @apply text-ink; }

    .s-dock-sep { @apply shrink-0 bg-line; width: 1px; height: 20px; margin: 0 4px; }
    .is-vertical .s-dock-sep { width: 18px; height: 1px; margin: 3px 0; }

    /* Preview / Edit / Code */
    .s-dock-seg { @apply flex shrink-0 rounded-full bg-wash p-0.5; gap: 1px; }
    .is-vertical .s-dock-seg { @apply flex-col; }
    .s-dock-seg-btn {
        @apply flex cursor-pointer items-center justify-center rounded-full text-faint;
        width: 32px;
        height: 30px;
        transition: background-color 150ms ease, color 150ms ease;
    }
    .s-dock-seg-btn:hover { @apply text-ink; }
    .s-dock-seg-btn.is-active { background: var(--color-ink); color: var(--color-shell); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35); }
    .s-dock-seg-btn.is-active.is-edit { background: color-mix(in srgb, var(--color-ok) 78%, white); color: #0f2a17; }
    .s-dock-seg-btn.is-active.is-code { background: color-mix(in srgb, var(--color-accent) 80%, white); color: #0e1140; }

    /* Publish with the save-status dot */
    .s-dock-publish {
        @apply relative inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full font-semibold;
        height: 32px;
        padding: 0 12px 0 10px;
        margin-left: 2px;
        font-size: 12.5px;
        background: var(--color-ink);
        color: var(--color-shell);
        transition: filter 150ms ease;
    }
    .s-dock-publish:hover { filter: brightness(1.08); }
    .is-vertical .s-dock-publish { width: 34px; padding: 0; justify-content: center; }
    .is-vertical .s-dock-publish .s-dock-publish-label { display: none; }
    .s-dock-status {
        @apply block shrink-0 rounded-full bg-ok;
        width: 6px;
        height: 6px;
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-ok) 25%, transparent);
        transition: background-color 300ms ease, box-shadow 300ms ease;
    }
    .s-dock-status.is-saving { @apply animate-pulse bg-warn; box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-warn) 25%, transparent); }
    .s-dock-status.is-error { @apply bg-danger; box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-danger) 25%, transparent); }
    .s-dock-publish .s-dock-dirty { @apply absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-accent; box-shadow: 0 0 0 2px var(--color-overlay); }

    /* Tooltips away from the dock's edge */
    .s-dock-btn[data-tip]::after {
        content: attr(data-tip);
        position: absolute;
        z-index: 60;
        padding: 4px 8px;
        border-radius: 7px;
        background: var(--color-ink);
        color: var(--color-shell);
        font-size: 11.5px;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 120ms ease;
    }
    .s-dock-btn[data-tip]:hover::after { opacity: 1; transition-delay: 450ms; }
    .s-dock.at-bottom .s-dock-btn[data-tip]::after { bottom: calc(100% + 10px); left: 50%; translate: -50% 0; }
    .s-dock.at-top .s-dock-btn[data-tip]::after { top: calc(100% + 10px); left: 50%; translate: -50% 0; }
    .s-dock.at-left .s-dock-btn[data-tip]::after { left: calc(100% + 10px); top: 50%; translate: 0 -50%; }
    .s-dock.at-right .s-dock-btn[data-tip]::after { right: calc(100% + 10px); top: 50%; translate: 0 -50%; }

    /* Code mode's file tree column, inside the code pane */
    .s-code-files { @apply flex shrink-0 flex-col border-r border-line bg-panel; width: 260px; }

    @media (prefers-reduced-motion: reduce) {
        .s-dock { transition: none; }
        .s-float { animation: none; }
    }
```

- [ ] **Step 2: Replace the topbar section**

Delete `.s-topbar`, `.s-box-btn*`, `.s-panel-icon-bar*`, `.s-panel-collapse*`, `.s-panel-expand*`, `.s-nav-btn*`, `.s-urlbar*`. Keep `.s-suggestion`, `.s-logo-btn*` (the menu cross-fade; it now composes with `s-dock-btn`), `.s-seg*`.

- [ ] **Step 3: Build and commit**

```bash
npm run build
git add resources/css/studio.css
git commit -m "CSS: the dock, the floating panel surface, the scrim"
```

---

### Task 3: The dock partial and the layout

**Files:**
- Create: `resources/views/partials/dock.blade.php`
- Modify: `resources/views/components/layouts/app.blade.php:55-96`
- Modify: `resources/views/partials/activity-bar-glyph.blade.php` (add `right`)
- Delete: `resources/views/partials/activity-bar.blade.php`

**Interfaces:**
- Consumes: store from Task 1; classes from Task 2; slots `$menu`, `$actions`, `$sidebar` from `home.blade.php` (Task 4 renames `topbar` → `actions`).
- Produces: dock buttons carry `data-panel="<rail>"` so the float can anchor to them; the dock root has id `studio-dock`.

- [ ] **Step 1: Write `partials/dock.blade.php`**

```blade
{{-- The dock: the editor's only chrome. A floating pill over the site with
     the menu, one button per panel, the Preview/Edit/Code pill and Publish.
     Drag the grip to any window edge; the position lives in
     $store.studio.dock. Panels open from it as popovers (or sheets),
     anchored by the float in the layout to the button's data-panel. --}}
@php
    $dev = \Designer\Studio\Support\DevMode::enabled();
    $panels = array_values(array_filter([
        $dev ? ['assistant', 'Assistant', '<path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>'] : null,
        ['sections', 'Sections', '<path d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>'],
        ['pages', 'Pages', '<path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>'],
        ['content', 'Content', '<path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 3.75v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75"/>'],
        ['media', 'Media', '<path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>'],
    ]));
@endphp

<nav
    id="studio-dock"
    class="s-dock"
    :class="{
        'is-vertical': $store.studio.dock.edge === 'left' || $store.studio.dock.edge === 'right',
        'is-hidden': $store.studio.dockHidden && !peek,
        'is-dragging': dragging,
        ['at-' + $store.studio.dock.edge]: true,
    }"
    :style="style"
    aria-label="Editor"
    x-data="{
        dragging: false,
        drag: null,      // { x, y } while dragging (pointer position)
        peek: false,
        style: '',

        // Position from the store: the dock's centre sits `along` the edge
        place() {
            if (this.dragging) return;
            const { edge, along } = $store.studio.dock;
            const gap = 12;
            const w = this.$el.offsetWidth, h = this.$el.offsetHeight;
            const W = window.innerWidth, H = window.innerHeight;
            let left, top;
            if (edge === 'bottom' || edge === 'top') {
                left = Math.round(Math.min(W - gap - w, Math.max(gap, along * W - w / 2)));
                top = edge === 'bottom' ? H - gap - h : gap;
            } else {
                top = Math.round(Math.min(H - gap - h, Math.max(gap, along * H - h / 2)));
                left = edge === 'left' ? gap : W - gap - w;
            }
            this.style = `left:${left}px; top:${top}px`;
        },

        startDrag(event) {
            event.preventDefault();
            this.dragging = true;
            event.currentTarget.setPointerCapture(event.pointerId);
            const rect = this.$el.getBoundingClientRect();
            this.drag = { dx: event.clientX - rect.left, dy: event.clientY - rect.top };
        },
        moveDrag(event) {
            if (!this.dragging) return;
            this.style = `left:${Math.round(event.clientX - this.drag.dx)}px; top:${Math.round(event.clientY - this.drag.dy)}px`;
        },
        endDrag(event) {
            if (!this.dragging) return;
            this.dragging = false;
            const x = event.clientX, y = event.clientY;
            const W = window.innerWidth, H = window.innerHeight;
            // Snap to the nearest edge; keep the position along it
            const d = { left: x, right: W - x, top: y, bottom: H - y };
            const edge = Object.keys(d).reduce((a, k) => d[k] < d[a] ? k : a, 'bottom');
            const along = (edge === 'bottom' || edge === 'top') ? x / W : y / H;
            $store.studio.setDock(edge, along);
            this.$nextTick(() => this.place());
        },
    }"
    x-init="place(); $nextTick(() => place())"
    x-effect="$store.studio.dock; $store.studio.mode; $store.studio.codeAvailable; $nextTick(() => place())"
    @resize.window.debounce.50ms="place()"
    @transitionend="$dispatch('studio:dock-moved')"
    @mouseleave="peek = false"
>
    <span
        class="s-dock-grip"
        title="Drag to move the dock"
        aria-label="Drag to move the dock"
        @pointerdown="startDrag($event)"
        @pointermove="moveDrag($event)"
        @pointerup="endDrag($event)"
        @pointercancel="dragging = false; place()"
    >
        <svg viewBox="0 0 8 14" width="8" height="14" fill="currentColor" aria-hidden="true"><circle cx="2" cy="2" r="1.3"/><circle cx="6" cy="2" r="1.3"/><circle cx="2" cy="7" r="1.3"/><circle cx="6" cy="7" r="1.3"/><circle cx="2" cy="12" r="1.3"/><circle cx="6" cy="12" r="1.3"/></svg>
    </span>

    {{ $menu ?? '' }}

    <span class="s-dock-sep"></span>

    @foreach($panels as [$name, $label, $icon])
        <button
            type="button"
            class="s-dock-btn"
            data-panel="{{ $name }}"
            data-tip="{{ $label }}"
            :class="$store.studio.rail === '{{ $name }}' && $store.studio.sidebar && 'is-active'"
            :aria-pressed="$store.studio.rail === '{{ $name }}' && $store.studio.sidebar"
            @click="$store.studio.setRail('{{ $name }}')"
            aria-label="{{ $label }}"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
        </button>
    @endforeach

    @if($dev)
        {{-- Files belongs to Code mode: it toggles the tree column in the code pane --}}
        <button
            type="button"
            class="s-dock-btn"
            data-panel="files"
            data-tip="Files"
            x-show="$store.studio.mode === 'code'"
            x-cloak
            :class="$store.studio.filesOpen && 'is-active'"
            :aria-pressed="$store.studio.filesOpen"
            @click="$store.studio.toggleFiles()"
            aria-label="Files"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.25 12.75V12a2.25 2.25 0 0 1 2.25-2.25h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
        </button>
    @endif

    <span class="s-dock-sep"></span>

    {{-- Preview / Edit / Code --}}
    <div class="s-dock-seg" role="radiogroup" aria-label="Mode">
        <button type="button" class="s-dock-seg-btn" :class="$store.studio.mode === 'preview' && 'is-active'" @click="$store.studio.setMode('preview')" title="Preview — browse the site as a visitor" aria-label="Preview mode" role="radio" :aria-checked="$store.studio.mode === 'preview'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9.25"/><path d="M2.9 12h18.2M12 2.75c2.2 2.5 3.3 5.6 3.3 9.25S14.2 18.75 12 21.25C9.8 18.75 8.7 15.65 8.7 12S9.8 5.25 12 2.75Z"/></svg>
        </button>
        <button type="button" class="s-dock-seg-btn is-edit" :class="$store.studio.mode === 'edit' && 'is-active'" @click="$store.studio.setMode('edit')" title="Edit — select and change sections" aria-label="Edit mode" role="radio" :aria-checked="$store.studio.mode === 'edit'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M4 3.5 19 11l-6.5 1.8L9 19 4 3.5Z"/></svg>
        </button>
        <button x-show="$store.studio.codeAvailable" x-cloak type="button" class="s-dock-seg-btn is-code" :class="$store.studio.mode === 'code' && 'is-active'" @click="$store.studio.setMode('code')" title="Code — edit the section and site source files" aria-label="Code mode" role="radio" :aria-checked="$store.studio.mode === 'code'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 7.5 4 12l4.5 4.5M15.5 7.5 20 12l-4.5 4.5"/></svg>
        </button>
    </div>

    {{ $actions ?? '' }}
</nav>
```

- [ ] **Step 2: Rewrite the layout body**

Replace everything from `<div class="flex h-dvh flex-col">` to its closing `</div>` (before `@livewireScripts`) in `app.blade.php` with:

```blade
        <div class="flex h-dvh flex-col">
            @isset($sidebar)
                {{-- The site is the screen: the stage fills the window. --}}
                <main class="s-stage">
                    {{ $slot }}
                </main>

                {{-- Behind a sheet only --}}
                <div
                    x-data
                    x-show="$store.studio.sidebar && $store.studio.frame === 'sheet'"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="s-scrim"
                    @click="$store.studio.closePanel()"
                ></div>

                {{-- The floating surface: every panel lives here, one visible
                     at a time. Anchored to its dock button (popover) or
                     centred (sheet). --}}
                <aside
                    class="s-float"
                    :class="$store.studio.frame === 'sheet' && 'is-sheet'"
                    :style="$store.studio.frame === 'popover' ? style : ''"
                    x-data="{
                        style: '',
                        place() {
                            if ($store.studio.frame !== 'popover') return;
                            const button = document.querySelector(`#studio-dock [data-panel='${$store.studio.rail}']`);
                            const dock = document.getElementById('studio-dock');
                            if (!button || !dock) return;
                            const gap = 12, edgePad = 12;
                            const W = window.innerWidth, H = window.innerHeight;
                            const b = button.getBoundingClientRect();
                            const d = dock.getBoundingClientRect();
                            const edge = $store.studio.dock.edge;
                            const w = $store.studio.floatWidth;
                            let left, top, maxH;
                            if (edge === 'bottom' || edge === 'top') {
                                left = b.left + b.width / 2 - w / 2;
                                maxH = Math.min(H * 0.7, H - d.height - gap - edgePad * 2);
                                top = edge === 'bottom' ? d.top - gap - maxH : d.bottom + gap;
                            } else {
                                maxH = Math.min(H * 0.7, H - edgePad * 2);
                                top = b.top + b.height / 2 - maxH / 2;
                                left = edge === 'left' ? d.right + gap : d.left - gap - w;
                            }
                            left = Math.round(Math.max(edgePad, Math.min(left, W - w - edgePad)));
                            top = Math.round(Math.max(edgePad, Math.min(top, H - maxH - edgePad)));
                            this.style = `left:${left}px; top:${top}px; width:${w}px; height:${Math.round(maxH)}px`;
                        },
                    }"
                    x-effect="$store.studio.rail; $store.studio.sidebar; $store.studio.dock; $store.studio.mode; $nextTick(() => place())"
                    @resize.window.debounce.50ms="place()"
                    @studio:dock-moved.window="place()"
                    x-show="$store.studio.sidebar"
                    x-cloak
                    :aria-hidden="!$store.studio.sidebar"
                    :inert="!$store.studio.sidebar"
                >
                    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
                        {{ $sidebar }}
                    </div>
                </aside>

                @include('studio::partials.dock')
            @else
                <main class="relative min-h-0 min-w-0 flex-1 overflow-hidden">
                    {{ $slot }}
                </main>
            @endisset
        </div>
```

Note the popover's height is fixed to `maxH` so the panel's own scroll regions (`min-h-0 flex-1 overflow-y-auto`) keep working; the panels all fill their container.

- [ ] **Step 3: Add `right` to the glyph partial and delete the activity bar**

In `activity-bar-glyph.blade.php` add a case:
```blade
        @case('right')
            <rect x="10.5" y="4.1" width="1.9" height="7.8" rx=".7" fill="currentColor"/>
            @break
```
Then `git rm resources/views/partials/activity-bar.blade.php`.

- [ ] **Step 4: Commit**

```bash
git add -A resources/views/partials resources/views/components/layouts/app.blade.php
git commit -m "The dock and the floating panel surface replace the activity bar and sidebar"
```

---

### Task 4: home.blade.php — rehome the topbar controls

**Files:**
- Modify: `resources/views/home.blade.php` — `x-slot:topbar` (lines 17-585) becomes `x-slot:actions` holding only Publish; menu slot rows; palette; sidebar slot loses `files`.
- Modify: `resources/views/livewire/pages-panel.blade.php:1-16` (header row).

**Interfaces:**
- Consumes: store (Task 1), dock slots (Task 3).
- Produces: `x-slot:actions`, menu rows (Canvas width, Back/Forward/Reload, Dock), palette commands.

- [ ] **Step 1: Turn the topbar slot into the actions slot**

Rename `<x-slot:topbar>` … `</x-slot:topbar>` to `<x-slot:actions>` … `</x-slot:actions>`. Inside it, delete: the sidebar toggle `<div x-data class="shrink-0">…</div>` (lines 147-166), the browser navigation block (168-182), the URL bar block (184-308), the mode switch `s-seg` (310-343), the split toggle (345-357), the device switcher (359-397). Keep the `<script>` block (the store) and the Publish block. Change the Publish trigger button to:

```blade
            <button
                @click="open = !open; if (open) refreshStatus()"
                class="s-dock-publish"
                x-data="{ state: 'idle' }"
                @studio:status.window="state = $event.detail.state"
                :title="state === 'saving' ? 'Saving…' : state === 'error' ? 'Offline — changes are not being saved' : 'All changes saved'"
                aria-label="Publish"
            >
                <span class="s-dock-status" :class="{ 'is-saving': state === 'saving', 'is-error': state === 'error' }"></span>
                <span class="s-dock-publish-label">Publish</span>
                <span x-show="draftMode && status?.dirty" x-cloak x-transition.opacity class="s-dock-dirty"></span>
            </button>
```

Change the Publish popover's classes from `absolute right-0 top-full mt-1.5 w-80 origin-top-right p-3` to a placement that follows the dock edge:

```blade
                class="s-pop fixed z-50 w-80 p-3"
                :style="popStyle"
                x-effect="open; $nextTick(() => { if (open) popStyle = window.StudioDock.anchor($el, $el.previousElementSibling, 320) })"
```
and add `popStyle: ''` to the Publish `x-data`. `window.StudioDock.anchor(pop, button, width)` is defined in Step 4.

- [ ] **Step 2: Menu rows**

Change the menu trigger from `class="s-box-btn s-logo-btn"` to `class="s-dock-btn is-menu s-logo-btn"` with `data-tip="Menu"`. Give the dropdown `class="s-pop s-pop-inverse fixed z-50 w-60"` with the same `:style="popStyle"` / `x-effect` pattern (add `popStyle: ''` to the menu's `x-data`; width 240). Replace the *Activity bar* row (lines 668-690) with:

```blade
                {{-- Canvas width --}}
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span>Canvas width</span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Canvas width">
                        @foreach(['desktop' => 'Desktop', 'tablet' => 'Tablet — 768px', 'mobile' => 'Mobile — 390px'] as $value => $label)
                            <button type="button" class="flex h-6 min-w-6 cursor-pointer items-center justify-center rounded-md px-1.5 text-[11px] transition-colors duration-150"
                                :class="$store.studio.device === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.device = '{{ $value }}'" role="radio" :aria-checked="$store.studio.device === '{{ $value }}'" title="{{ $label }}">{{ ucfirst($value) }}</button>
                        @endforeach
                    </span>
                </div>
                {{-- Dock placement --}}
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span class="flex items-center gap-2.5">
                        @include('studio::partials.activity-bar-glyph', ['position' => 'bottom'])
                        Dock
                    </span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Dock position">
                        @foreach(['bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right', 'top' => 'Top'] as $value => $label)
                            <button type="button" class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md transition-colors duration-150"
                                :class="$store.studio.dock.edge === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.setDock('{{ $value }}')" role="radio" :aria-checked="$store.studio.dock.edge === '{{ $value }}'" title="{{ $label }}" aria-label="{{ $label }}">
                                @include('studio::partials.activity-bar-glyph', ['position' => $value])
                            </button>
                        @endforeach
                    </span>
                </div>
                <div class="s-divider my-1"></div>
                <div class="flex items-center gap-1 px-1.5 py-1">
                    <button type="button" class="s-menu-item flex-1 !justify-center" @click="open = false; history.back()" title="Back">← Back</button>
                    <button type="button" class="s-menu-item flex-1 !justify-center" @click="open = false; history.forward()" title="Forward">Forward →</button>
                    <button type="button" class="s-menu-item flex-1 !justify-center" @click="open = false; window.dispatchEvent(new CustomEvent('studio:refresh-preview'))" title="Reload the preview">Reload</button>
                </div>
```

- [ ] **Step 3: Sidebar slot and palette**

Delete the `files` wrapper (lines 726-733) from `x-slot:sidebar` (the tree moves in Task 7; until then Code mode has no tree — acceptable for the Phase 1 commit only if Task 7 lands in the same push; otherwise keep it and hide it with `x-show="false"`). In the palette: delete the four `Activity bar:` entries and the `Files panel` entry; change the sidebar entry to `{ label: studio.sidebar ? 'Close the panel' : 'Open the panel', hint: 'Layout', run: () => studio.toggleSidebar() }`; add:

```js
                    { label: 'Dock: bottom', hint: 'Layout', when: studio.dock.edge !== 'bottom', run: () => studio.setDock('bottom') },
                    { label: 'Dock: left', hint: 'Layout', when: studio.dock.edge !== 'left', run: () => studio.setDock('left') },
                    { label: 'Dock: right', hint: 'Layout', when: studio.dock.edge !== 'right', run: () => studio.setDock('right') },
                    { label: 'Dock: top', hint: 'Layout', when: studio.dock.edge !== 'top', run: () => studio.setDock('top') },
                    { label: studio.dockHidden ? 'Show the dock' : 'Hide the dock', hint: 'Layout', run: () => studio.toggleDock() },
                    @if($devModeAvailable)
                    { label: studio.filesOpen ? 'Hide the file tree' : 'Show the file tree', hint: 'Code', when: studio.mode === 'code', run: () => studio.toggleFiles() },
                    @endif
```

- [ ] **Step 4: `window.StudioDock.anchor()`**

In the store `<script>` (after `Alpine.store` registration), add a tiny helper both popovers use:

```js
            // Position a popover (menu, publish) beside its dock button,
            // opening away from the dock's edge and clamped to the window.
            window.StudioDock = {
                anchor(pop, button, width) {
                    const edge = Alpine.store('studio').dock.edge;
                    const b = button.getBoundingClientRect();
                    const gap = 10, pad = 12;
                    const W = window.innerWidth, H = window.innerHeight;
                    const h = pop.offsetHeight || 320;
                    let left, top;
                    if (edge === 'bottom') { left = b.left; top = b.top - gap - h; }
                    else if (edge === 'top') { left = b.left; top = b.bottom + gap; }
                    else if (edge === 'left') { left = b.right + gap; top = b.top; }
                    else { left = b.left - gap - width; top = b.top; }
                    left = Math.max(pad, Math.min(left, W - width - pad));
                    top = Math.max(pad, Math.min(top, H - h - pad));
                    return `left:${Math.round(left)}px; top:${Math.round(top)}px; width:${width}px`;
                },
            };
```

- [ ] **Step 5: Pages panel header row**

At the top of `pages-panel.blade.php`, above the existing header, add the current-page row (the URL bar's job):

```blade
    @php
        $homeSlug = \Designer\Studio\Support\SiteUrls::homeSlug();
        $path = $pageSlug === $homeSlug ? '' : $pageSlug;
        $host = parse_url(url('/'), PHP_URL_HOST);
        $draft = (bool) config('studio.draft_mode', true);
        $openUrl = $draft ? route('studio.preview.home') . ($path ? '/' . $path : '') : \Designer\Studio\Support\SiteUrls::pageUrl($pageSlug);
    @endphp
    <div class="flex h-9 shrink-0 items-center gap-1.5 border-b border-line bg-raised/40 px-3 text-[12px]">
        <span class="min-w-0 flex-1 truncate"><span class="text-soft">{{ $host }}</span><span class="mx-1 text-faint">/</span><span class="font-mono text-[11.5px] text-ink">{{ $path }}</span></span>
        <a href="{{ $openUrl }}" target="_blank" class="s-icon-btn !h-6 !w-6" title="{{ $draft ? 'Open the draft preview in a new tab' : 'Open the live page in a new tab' }}">
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
        </a>
    </div>
```
(`$pageSlug` is `PagesPanel`'s public mount property; confirm its name in `src/Livewire/PagesPanel.php:32` before using it.)

- [ ] **Step 6: Build, verify Phase 1, commit**

`npm run build`, then `node <scratchpad>/pw/dock.mjs` (written in Task 5) must print `PHASE1 OK`. Commit:

```bash
git add resources/views/home.blade.php resources/views/livewire/pages-panel.blade.php
git commit -m "Rehome the topbar: Publish + menu on the dock, URL bar into Pages"
```

---

### Task 5: Playwright check for Phase 1

**Files:**
- Create: `<scratchpad>/pw/dock.mjs`

- [ ] **Step 1: Write the script**

```js
import { chromium } from 'playwright';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', e => errors.push(e.message));
await page.addInitScript(() => { localStorage.setItem('studio.mode', 'edit'); localStorage.removeItem('studio.sidebar'); localStorage.removeItem('studio.dock'); });
await page.goto('http://localhost:8765/studio', { waitUntil: 'networkidle' });
const must = async (name, ok) => { console.log((ok ? 'ok  ' : 'FAIL') + ' ' + name); if (!ok) process.exitCode = 1; };
await must('no topbar', await page.locator('.s-topbar').count() === 0);
await must('dock present', await page.locator('#studio-dock').isVisible());
await must('panel closed on first run', !(await page.locator('.s-float').isVisible()));
const dockBox = await page.locator('#studio-dock').boundingBox();
await must('dock at bottom centre', dockBox.y + dockBox.height > 900 - 20 && Math.abs(dockBox.x + dockBox.width / 2 - 720) < 4);
await page.click('#studio-dock [data-panel=pages]');
await page.waitForTimeout(250);
await must('pages popover open', await page.locator('.s-float').isVisible() && !(await page.locator('.s-float').evaluate(e => e.classList.contains('is-sheet'))));
const fb = await page.locator('.s-float').boundingBox();
await must('popover above the dock', fb.y + fb.height <= dockBox.y);
await page.click('#studio-dock [data-panel=pages]');
await page.waitForTimeout(150);
await must('clicking again closes it', !(await page.locator('.s-float').isVisible()));
await page.evaluate(() => Alpine.store('studio').setDock('left'));
await page.waitForTimeout(450);
await must('vertical on the left', await page.locator('#studio-dock.is-vertical').count() === 1 && (await page.locator('#studio-dock').boundingBox()).x < 20);
await must('persisted', JSON.parse(await page.evaluate(() => localStorage.getItem('studio.dock'))).edge === 'left');
await page.evaluate(() => Alpine.store('studio').setDock('bottom'));
await page.screenshot({ path: 'phase1.png' });
await must('no page errors', errors.length === 0);
if (errors.length) console.log(errors);
console.log(process.exitCode ? 'PHASE1 FAILED' : 'PHASE1 OK');
await browser.close();
```

- [ ] **Step 2: Run it**

Run: `node dock.mjs` from `<scratchpad>/pw`. Expected: every line `ok`, then `PHASE1 OK`. Look at `phase1.png` once.

---

### Task 6: Sheets for Content and Media

**Files:**
- Modify: `resources/views/livewire/content-panel.blade.php`, `media-panel.blade.php` — no logic change; confirm their roots are `flex h-full min-h-0 flex-col` so they fill the sheet.
- Modify: `resources/js/studio.js:313-333` (`handleShortcut`): `Esc` closes an open panel first.

- [ ] **Step 1: Esc order**

Replace the `if (key === 'Escape') {...}` block with:

```js
        if (key === 'Escape') {
            const studio = window.Alpine?.store('studio');
            if (studio?.sidebar) {
                studio.closePanel();
                return;
            }
            if (this.selectedId) {
                this.selectedId = null;
                this.send('studio:deselect');
                window.Livewire?.dispatch('studio:deselect-section');
            }
            return;
        }
```

- [ ] **Step 2: Verify sheets**

Append to `dock.mjs`: click `[data-panel=media]`, assert `.s-float.is-sheet` visible and `.s-scrim` visible, press `Escape`, assert closed. Run, expect `ok`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/studio.js
git commit -m "Content and Media open as sheets; Esc closes the open panel first"
```

---

### Task 7: The file tree moves into the code pane

**Files:**
- Modify: `resources/views/partials/code-pane.blade.php` — wrap the tab strip + editor in a row with the tree column first; split toggle in the tab strip.
- Modify: `resources/views/home.blade.php` — remove the `files` wrapper from the sidebar slot (if kept in Task 4).

- [ ] **Step 1: Restructure the pane**

Change the pane root's inner structure to:

```blade
    <div class="flex min-h-0 flex-1">
        {{-- File tree column --}}
        <div x-show="$store.studio.filesOpen" class="s-code-files">
            @include('studio::partials.file-tree')
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Tab strip (existing) --}}
            ...
            <div class="flex flex-1 items-center justify-end gap-2 px-2.5">
                <button type="button" class="s-icon-btn" :class="$store.studio.codeSplit && '!bg-wash-strong !text-ink'" @click="$store.studio.toggleCodeSplit()" :title="$store.studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code'">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4.75" width="18" height="14.5" rx="2.25"/><path d="M12 4.75v14.5"/></svg>
                </button>
                <span x-show="$store.code.saving" ...>Saving…</span>
                <button ... Save ...>
            </div>
            {{-- Editor (existing) --}}
        </div>
    </div>
```
The pane root keeps `x-show="$store.studio.mode === 'code'"` and its `s-frame` classes; the root's `flex-col` becomes `flex` (row) via the wrapper above. The file tree's own root is `flex h-full min-h-0 flex-col`, which fits the column.

- [ ] **Step 2: Verify Code mode**

Append to `dock.mjs`: `Alpine.store('studio').setMode('code')`, assert `.s-code-files` visible and `#studio-dock [data-panel=files]` visible; click it; assert `.s-code-files` hidden; set mode back to `edit`.

- [ ] **Step 3: Commit**

```bash
git add resources/views/partials/code-pane.blade.php resources/views/home.blade.php
git commit -m "Code mode owns its file tree; the split toggle joins the tab strip"
```

---

### Task 8: Inline first — the inspector opens from the toolbar

**Files:**
- Modify: `resources/js/studio.js:97-101` (`studio:section-selected`), new `case 'studio:open-inspector'`, `handleShortcut` (`E`), `sectionMenuItems` (+ `MENU_ICONS.fields`), new `StudioPreview.openInspector()`.
- Modify: `resources/views/iframe.blade.php:1165` (toolbar: leading button).
- Modify: `CLAUDE.md` (contract list + Views), `docs/inline-editing.md` (one paragraph).

**Interfaces:**
- Produces: postMessage `studio:open-inspector { sectionId }` iframe → editor; `Studio.preview.openInspector(sectionId, event)`.

- [ ] **Step 1: Editor side**

```js
                case 'studio:section-selected':
                    this.selectedId = data.sectionId;
                    // Selecting never opens the panel — the inspector is on
                    // request (toolbar's Edit fields, E). Livewire still
                    // tracks the selection so an open panel follows it.
                    window.Livewire?.dispatch('studio:select-section', { id: data.sectionId });
                    break;

                case 'studio:open-inspector':
                    this.selectedId = data.sectionId;
                    window.Livewire?.dispatch('studio:select-section', { id: data.sectionId });
                    window.Alpine?.store('studio')?.openInspector();
                    break;
```
In `handleShortcut`, after the `if (!this.selectedId) return;` line add:
```js
        if (!meta && (key === 'e' || key === 'E')) {
            preventDefault();
            window.Livewire?.dispatch('studio:select-section', { id: this.selectedId });
            window.Alpine?.store('studio')?.openInspector();
            return;
        }
```

- [ ] **Step 2: Iframe side**

In `StudioPreview` next to `openCode`:
```js
    openInspector(sectionId, event) {
        if (event) event.stopPropagation();
        if (this.mode === 'preview') return;
        this.post('studio:open-inspector', { sectionId });
    },
```
`MENU_ICONS.fields`: `'<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 6h9M15 6h2M3 14h2M8 14h9"/><circle cx="13" cy="6" r="2"/><circle cx="6" cy="14" r="2"/></svg>'`. In `sectionMenuItems`, insert after the header: `{ label: 'Edit fields', icon: 'fields', kbd: 'E', onClick: () => this.openInspector(id) }, 'sep',`. The iframe's own keydown handler for `E` (not typing, a selection) calls `this.openInspector(this.selectedId)` — find where `⌘D`/`⌫` are handled inside the iframe runtime and add it there with the same guards.

In `iframe.blade.php`, as the toolbar's first child:
```blade
                    <button type="button" class="studio-toolbar-primary" onclick="Studio.preview.openInspector('{{ $section['id'] }}', event)" title="Edit fields (E)">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M3 6h9M15 6h2M3 14h2M8 14h9"/><circle cx="13" cy="6" r="2"/><circle cx="6" cy="14" r="2"/></svg>
                    </button>
                    <span class="studio-toolbar-sep"></span>
```
and in the pushed `<style>` block: `.studio-toolbar button.studio-toolbar-primary { background: rgba(76,125,250,.22); color: #b7c8ff; } .studio-toolbar button.studio-toolbar-primary:hover { background: rgba(76,125,250,.38); color: #fff; }`.

- [ ] **Step 3: Verify**

Append to `dock.mjs`: click a section in the canvas iframe (`page.frameLocator('#studio-canvas-frame').locator('[data-section]').nth(1)` at its top-left corner), wait 300ms, assert `.s-float` NOT visible; hover the section and click its `.studio-toolbar-primary`; wait 400ms; assert `.s-float` visible and its text contains the section title from the wrapper's `data-title`; press `Escape`; assert closed. Run `php artisan studio:inline:verify` in the lab: baseline unchanged.

- [ ] **Step 4: Docs and commit**

CLAUDE.md: add `studio:open-inspector` to the "shared contracts" gotcha; in "Views", replace the app.blade.php / home.blade.php / activity bar paragraphs with the dock description (grip, menu, panels, mode pill, Publish; `$store.studio.dock`, `frame`, `s-float`, sheets for Content/Media, Files column in the code pane, inspector on request). `docs/inline-editing.md`: under "Affordances by type" add "**Inspector** — never opened by a click; the toolbar's Edit fields button, the context menu, or `E` posts `studio:open-inspector`."

```bash
git add resources/js/studio.js resources/views/iframe.blade.php CLAUDE.md docs/inline-editing.md
git commit -m "Inline first: the inspector opens from the toolbar, not from selection"
```

---

### Task 9: Polish — hide/peek, shortcuts, light theme

**Files:**
- Modify: `resources/views/partials/dock.blade.php` (peek strip), `resources/js/studio.js` (`⌘.`, `⌘1/2/3`, `⌘[`, `⌘]`), `resources/css/studio.css` (light theme check on `s-dock*`).

- [ ] **Step 1: Peek strip**

After the `<nav>` in the dock partial, add a sibling rendered by the layout (same `x-data` scope is not shared, so put it inside the nav's parent via `x-teleport="body"`):

```blade
    <template x-teleport="body">
        <div
            x-show="$store.studio.dockHidden"
            x-cloak
            class="s-dock-peek"
            :style="({ bottom: 'left:0;right:0;bottom:0;height:6px', top: 'left:0;right:0;top:0;height:6px', left: 'top:0;bottom:0;left:0;width:6px', right: 'top:0;bottom:0;right:0;width:6px' })[$store.studio.dock.edge]"
            @mouseenter="peek = true"
        ></div>
    </template>
```

- [ ] **Step 2: Shortcuts**

In `handleShortcut`, after the `⌘K` block:
```js
        if (meta && key === '.') { preventDefault(); window.Alpine?.store('studio')?.toggleDock(); return; }
        if (meta && ['1', '2', '3'].includes(key)) {
            preventDefault();
            const studio = window.Alpine?.store('studio');
            if (studio) studio.device = { 1: 'desktop', 2: 'tablet', 3: 'mobile' }[key];
            return;
        }
        if (meta && (key === '[' || key === ']')) { preventDefault(); key === '[' ? history.back() : history.forward(); return; }
```
(before the `typing` check for `⌘.` and the device keys; after it is fine for the bracket keys).

- [ ] **Step 3: Light theme**

Load the lab with `localStorage.studio.theme = 'light'`, screenshot, confirm the dock reads (overlay white, ink dark, publish inverted). Adjust only token usage if something is illegible.

- [ ] **Step 4: Final verification and commit**

`node dock.mjs` → all ok; `php artisan studio:inline:verify` baseline; `git commit -m "Dock polish: hide and peek, shortcuts, light theme"`.
