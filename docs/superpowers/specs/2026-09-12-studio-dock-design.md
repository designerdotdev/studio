# The Studio Dock: one floating bar replaces the topbar, activity bar and sidebar

Date: 2026-09-12. Approved from the visual plan ("The Studio Dock" artifact). Scope: the
editor shell only. No runtime, storage, publishing, template, or inline-editing engine changes.

## 1. The idea

The website fills the editor window, exactly like the draft preview does. One dark, pill-shaped,
draggable **dock** floats over it, bottom-centre by default. Everything the topbar, the activity
bar and the permanent sidebar do today happens from the dock. Panels open as popovers anchored to
their dock button, or as centred sheets for the two data screens. The inspector is no longer
opened by selecting a section; it opens from a new "Edit fields" button on the section toolbar.

The Livewire panels are not rewritten. The `<aside>` that holds them becomes a floating surface,
and `$store.studio.rail` / `$store.studio.sidebar` keep meaning "which panel" / "a panel is open".

## 2. The dock

`partials/dock.blade.php`, rendered once by `components/layouts/app.blade.php` (the layout has no
topbar any more). Fixed-position, `z-40`, `s-dock`. Left to right:

1. **Grip** (six-dot handle). Pointer-drag moves the dock; on release it snaps to the nearest
   window edge and stays where it was released along that edge.
2. **Menu** — the existing `menu` slot from `home.blade.php` (brand mark / hamburger + dropdown).
   The dropdown gains three rows: *Canvas width* (desktop / tablet / mobile radio), *Back · Forward
   · Reload*, and *Dock* (bottom / left / right / top, using `partials/activity-bar-glyph` extended
   with `right`). The *Activity bar* row goes.
3. **Panel buttons** — Assistant (dev mode only) · Sections · Pages · Content · Media, plus Files
   while in Code mode. Same icons as today's activity bar. The active one (its panel is open) gets
   `is-active`. Clicking the active button closes the panel; clicking another switches.
   Tooltips from `data-tip`, placed away from the dock's edge.
4. **Mode pill** — Preview / Edit / Code as a `s-dock-seg`. Code only when
   `$store.studio.codeAvailable`. The active segment is filled: neutral for Preview, `ok`-tinted for
   Edit, `accent`-tinted for Code.
5. **Actions slot** — the `actions` slot from `home.blade.php`: the Publish button and its popover
   (the popover opens away from the dock's edge). The save-status dot (`studio:status` window
   event: saving = warn pulse, saved/idle = ok, error = danger) moves onto the Publish button; the
   old Saving / Saved / Offline text becomes the button's `title`.

Dimensions: 46px tall, 34px round buttons, 17px icons, 12px from the window edge. Vertical edges
render it as a column (`is-vertical`): same buttons stacked, the mode pill vertical, Publish
collapses to its icon. Colours come from the chrome tokens (`overlay` / `line` / `ink`), so the
light theme works; dark is the default.

### Placement state

```js
dock: { edge: 'bottom' | 'left' | 'right' | 'top', along: 0..1 }   // localStorage studio.dock (JSON)
dockHidden: boolean                                                  // localStorage studio.dock-hidden
setDock(edge, along)   // clamps `along`, persists, dispatches window 'studio:dock'
toggleDock()           // hide / show, persists
```

`along` is the fraction of the edge at which the dock's centre sits, clamped so the dock stays
fully inside the window. Hidden: the dock translates off its edge; a 6px hot strip on that edge
brings it back on hover (peek) and `⌘.` toggles it. `activityBar` / `setActivityBar` and
localStorage `studio.activity-bar` are removed.

## 3. The floating surface (the old sidebar)

The `<aside class="s-float">` in the layout holds every panel exactly as today (`x-show` per
rail). It has two frames, chosen by the active rail:

| Rail | Frame | Notes |
|---|---|---|
| sections | popover | List state (Sections / Layout / Page tabs) or inspector state, as `EditorPanel` decides |
| pages | popover | Gains a header row: `host / slug` of the open page, open-draft/live link, save text |
| assistant | popover, 380px | |
| content | sheet | Scrim behind, `Esc` / scrim click close |
| media | sheet | Picker mode (`Studio.mediaPick()`) opens the same sheet and resolves the same promise |
| files | — | Not a rail any more; see §5 |

**Popover**: 320px wide (380 for the Assistant), `max-height: min(70vh, available)`. Anchored to
the active dock button: above the dock for a bottom edge, below for top, beside it for left /
right, centred on the button and clamped 12px inside the window. Position is recomputed from
`getBoundingClientRect()` on rail change, dock move, window resize, and after the dock's
placement transition ends. `s-float` is `fixed`, `z-30`, `bg-panel`, 14px radius, hairline + shadow.

**Sheet** (`is-sheet`): centred, `width: min(1100px, 88vw)`, `height: 80vh`, with a scrim
(`s-scrim`, `z-20`) that closes on click. The dock stays above the scrim.

`sidebar` (panel open) now defaults to **closed** on first run (`studio.sidebar` unset → false);
a stored value is honoured. `setRail(name, force)`: if `name` is the open rail and not forced →
close; else set the rail and open. `toggleSidebar()` keeps its meaning (⌘B reopens the last panel).
`Esc` closes an open panel before it deselects the section.

## 4. Inline first, inspector on request

- `studio.js` `studio:section-selected` handler no longer calls `setRail('sections', true)`. It
  only dispatches `studio:select-section` to Livewire, so `EditorPanel` still tracks the selection
  and shows the inspector state *when the panel is open*. That is how the inspector "follows the
  selection" while open and stays closed otherwise.
- `iframe.blade.php`'s section toolbar gets a leading **Edit fields** button (sliders icon, before
  the move buttons); the context menu (`sectionMenuItems`) gets an "Edit fields" item first, with
  `E` as its shortcut. Both post `studio:open-inspector { sectionId }` to the parent.
- Parent: `studio:open-inspector` → `Livewire.dispatch('studio:select-section', {id})` +
  `$store.studio.openInspector()` (= `setRail('sections', true)`). The `E` key in the parent (not
  typing, a section selected) does the same.
- The inspector header's back arrow (`closeInspector`) returns to the list, as today.
- Canvas editability, `editabilityOf`, the overlay writer, blur commits: untouched.

`studio:open-inspector` joins the shared postMessage contract list in CLAUDE.md.

## 5. Code mode

The code pane fills the window like the canvas does. The file tree leaves the aside and becomes a
column inside `partials/code-pane.blade.php` (`s-code-files`, 260px, left of the tab strip +
editor), shown when `$store.studio.filesOpen` (localStorage `studio.files`, default true). The
dock's Files button toggles it and is only rendered in Code mode. `rail` never holds `'files'`;
`setMode()` no longer touches the rail. The split toggle moves into the code pane's tab strip
(right end, before Save). The split seam and preview half are unchanged.

## 6. Where every topbar control goes

| Control | New home |
|---|---|
| Menu button | Dock |
| Sidebar toggle | Removed; `⌘B` toggles the last panel |
| Back / Forward / Reload | Menu rows + `⌘[` `⌘]` (reload = refresh preview) |
| URL bar + pages dropdown | Pages popover header + the existing PagesPanel list; the create-page modal keeps `studio:open-create-page` |
| Save status dot + text | Dot on Publish, text as its `title` |
| Preview / Edit / Code | Dock mode pill |
| Split toggle | Code pane tab strip |
| Device segment | Menu → Canvas width, `⌘1` `⌘2` `⌘3` |
| Publish popover | Dock actions slot, same popover |
| Editor notices | Unchanged (they already float over the canvas) |

Shortcuts added in `studio.js` `handleShortcut`: `⌘.` (dock), `⌘1/2/3` (device), `E` (inspector),
`⌘[` / `⌘]` (history). `⌘R` stays the browser's reload; the menu's Reload row dispatches
`studio:refresh-preview`. `⌘K` palette entries: "Dock: bottom/left/right/top", "Hide/Show the dock",
"Edit fields" (when a section is selected); the four "Activity bar: …" entries go.

## 7. Layout and CSS

`app.blade.php`: `body > .s-shell` is `h-dvh`; `main.s-stage` is the whole window (no
`s-workspace`, no `s-card`, no padding); then `s-scrim`, the `aside.s-float`, and the dock. The
small-screen overlay stays; the dock is not rendered below `lg` (the overlay covers it anyway).
`s-canvas` / `s-frame` keep working: a narrow device width centres the frame on the dotted surface.

New classes in `studio.css`: `s-dock`, `s-dock-grip`, `s-dock-btn`, `s-dock-sep`, `s-dock-seg`,
`s-dock-publish`, `s-float`, `s-float.is-sheet`, `s-scrim`, `s-code-files`. Removed:
`s-topbar`, `s-workspace`, `s-card`, `s-sidebar`, `s-stage` padding rules, `s-activity*`,
`s-urlbar*`, `s-box-btn` (the menu button becomes an `s-dock-btn`), `s-nav-btn` (menu rows use
`s-menu-item`). `s-seg` stays for the file-tree view switch and the menu's radios.
Reduced motion: the dock's placement transition and the popover's pop animation are disabled
under `prefers-reduced-motion`.

## 8. Phases

1. **Dock replaces topbar + activity bar.** Layout, dock partial, store (`dock`, `sidebar`
   default, `setRail` toggle), floating popover surface for every rail, topbar controls rehomed,
   menu rows, palette entries, drag + snap.
2. **Sheets and the Code column.** Content/Media as sheets with scrim; picker mode; Assistant at
   380px; file tree into the code pane; split toggle into the tab strip.
3. **Inline first.** Selection stops opening the panel; toolbar + context menu "Edit fields";
   `studio:open-inspector`; `E`; docs.
4. **Polish.** Hide/peek (`⌘.`), light-theme pass, reduced motion, CLAUDE.md "Views" rewrite.

Each phase is verified in a throwaway lab app (Laravel + this package path-symlinked + Monarch
installed from the local template clone) with a Playwright script; `studio:inline:verify` must
keep its baseline (Coverage 365/369, Inertness 57 identical / 0 diverged).

## 9. Not changing

Every Livewire component's PHP and events; the canvas iframe's inline editing; `RenderController`;
storage, publishing, the runtime, templates; the create-page and section-library modals; toasts.
