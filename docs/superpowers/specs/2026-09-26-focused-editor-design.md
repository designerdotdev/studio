# The focused editor — one mode, the page is the list, edit in a slide-over

Date: 2026-09-26. Brief from Tony (Unicorn Platform as the reference, "designed by
someone at Linear" as the bar). Built overnight from the brief; the decisions below
that the brief did not spell out are marked **(decision)** so they can be reversed.

## What changes

1. **One mode.** The Preview / Edit segmented control is gone. The canvas is always
   editable. Preview is the open-in-new-tab link (draft preview). Code mode survives
   as a single `</>` toggle in the top bar, developer mode only.
2. **No Sections panel.** The page is the list: every section shows its own controls
   on hover. Reorder (↑ ↓), duplicate, hide, make global, delete and add-between all
   live on the canvas.
3. **The left sidebar is the Assistant.** Tabs: Assistant · Pages · Content · Media
   **(decision — Pages/Content/Media still need a home; a slim tab strip keeps them
   one click away without a second column)**. Assistant is the default tab where the
   server allows it (developer mode); otherwise Pages.
4. **The section toolbar** (top-right of a hovered/selected section), left to right:
   `···` (menu: Make global, Duplicate, Hide, Edit code [dev], Delete) · `↑` `↓`
   (each shown only when there is somewhere to move) · `✦ Ask AI` · `Edit`.
   One dark glass bar, Edit filled in the section's scope colour (blue page, violet
   layout, teal global block).
5. **Add section** appears when a section is hovered: a pill on its bottom edge, and
   on the top edge of the first section. Layout/page boundaries offer both targets
   (Add to header + Add section; Add section + Add to footer).
6. **No inline editing.** Hovering a section shows only the blue outline and its
   toolbar. Field halos, chips, the type cursor, the link/select/colour control, the
   collection card and repeater item controls are switched off (`StudioPreview.inline
   = false`) — the code stays, gated, so it can come back behind a preference.
7. **Edit opens a slide-over on the right, and the section is isolated.** The
   inspector is a column on the right of the stage (`aside.s-inspector`, resizable
   300–560, default 360). Opening it collapses the left sidebar (restored on close).
   On the canvas the page steps aside: every other section leaves the layout and the
   edited one becomes a single card on a dotted canvas, with a header strip carrying
   its name and toolbar and a caption underneath saying how to get back. Two earlier
   tries — a fixed scrim over the page, then per-section dimming — lost to the site's
   stacking contexts in Chrome and, once a tall section filled the viewport, showed
   nothing but a blue border. Done, Esc, the panel's × or a click on the canvas around
   the card brings the page back at the scroll position it had.
8. **Ask AI** selects the section, opens the sidebar on the Assistant tab and puts the
   caret in the composer with the section as the context chip (the same
   `studio:select-section` → `AssistantPanel::noteSelection` path the composer
   already understands). Keyboard: `A` on a selected section.
9. **Page settings** open in the same right column (no scrim — nothing on the canvas
   is being edited). Reached from the page switcher's gear, the menu, ⌘,.

## State (Alpine `$store.studio`)

- `sidebar` (bool, persisted), `rail` (`assistant|pages|content|media`, persisted),
  per-group widths (`studio.panel-width`, `-wide`, `studio.assistant-width`).
- `inspector`: `null | 'section' | 'page'`. `openInspector(id)`, `openPageSettings()`,
  `closeInspector({ fromServer })`. Opening remembers `sidebarBefore` and collapses the
  sidebar; closing restores it (a manual ⌘B while open forgets the memory).
- `mode`: `edit | code` (a saved `preview` falls back to `edit`).
- `assistantOpen` is now a getter: `sidebar && rail === 'assistant'`;
  `setAssistant(on)` / `toggleAssistant()` / `focusChat()` are kept for the composer.

## Messages (editor ↔ iframe), added

- editor → iframe `studio:focus {sectionId, on}` — `html.studio-focus` +
  `.studio-section.is-editing`; the section becomes a card, the rest of the page leaves.
- iframe → editor `studio:focus-exit` — Done, or a click around the card.
- iframe → editor `studio:ask-ai {sectionId}`.
- `studio:select` gains `focus: bool` so a canvas reload restores the scrim.

## Not changed

Publishing, the page switcher, the ⌘K palette (entries updated), the Add-Section
modal, Code mode, the dev-mode code modal, `studio:inline:verify` (sentinels still
render; only the affordances are off), SiteReader/SiteWriter.
