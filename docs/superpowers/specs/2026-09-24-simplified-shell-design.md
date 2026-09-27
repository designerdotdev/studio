# The simplified shell: one layout, two editors

Date: 2026-09-24. Scope: the editor chrome only — how the panels, toolbar, modes and
menus are arranged and what a new user sees first. No change to the runtime, storage,
publishing, templates, sections, the canvas engine, Code mode's workspace, or the
Assistant's engines. Every capability the editor had stays reachable; what goes is the
*choice* of where the chrome sits.

## 1. Why

The dock design (2026-09-12) gave the toolbar four edges, a pin, a drag grip, a hidden
state with a peek strip, seven View switches, five Workspace presets, three panel frames
(docked column, popover, sheet), a chat that could float, dock, or join the toolbar as one
object, and a command palette that was mostly about moving all of that around. Each piece
was well made. Together they are an editor about its own layout: a first-time user meets a
chat composer, a pill they can drag, and a menu with two flyouts before they have edited a
word of their page.

Linear's answer to this is one opinionated arrangement, no chrome settings, keyboard
shortcuts for the things people do every minute, and progressive disclosure for the rest.
That is what this spec does.

## 2. The layout

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ ◧ ▣  Home ▾  /          [ Edit | Preview | Code ]        ▭ ▯ ▮  ✦  ● Publish │  48px
├───────────────┬──────────────────────────────────────────────┬───────────────┤
│ Sections·Pages│                                              │ Assistant     │
│ Content·Media │                the site (the stage)          │ (developer    │
│               │                                              │  mode, ⌘J)    │
│  panel 300px  │                                              │  380px        │
└───────────────┴──────────────────────────────────────────────┴───────────────┘
```

- **Top bar** (`partials/topbar.blade.php`, 48px, part of the frame). Left: the sidebar
  toggle (⌘B), the brand mark that opens the menu, the **page switcher** (title, path, the
  save-status dot; its popover lists pages, New page…, Page settings…, Manage pages…).
  Centre: the **mode** control — *Edit* and *Preview* as labelled segments, *Code* added in
  developer mode. Right: canvas widths (desktop / tablet / mobile, ⌥1–3), the Assistant
  button (developer mode, ⌘J), and **Publish** with its popover exactly as before.
- **Sidebar** (`aside.s-sidebar`, 300px, resizable 240–520 by its inner seam, collapsible
  with ⌘B or the toggle). A tab strip across its top — Sections · Pages · Content · Media —
  and one Livewire panel showing beneath it. There are no popovers and no sheets any more:
  every panel is a column beside the site. Content and Media are the same components; the
  sidebar simply defaults wider (420px) for them so their grids have room, and the width
  the user drags is remembered per group (`studio.panel-width`, `studio.panel-width-wide`).
- **Stage**: the site, a rounded card on the frame, as before. Code mode replaces it (or
  splits it) exactly as before.
- **Assistant** (`aside.s-assistant`, 380px, resizable): a right column the Assistant
  button / ⌘J toggles. It exists only in developer mode. It never floats.

Removed outright: the dock (edges, pin, grip, hide/peek, `studio.dock*`), the View
submenu and every `studio.view` switch, the five workspaces, the joined composer, the
floating chat and its fold state, the popover and sheet frames, the per-panel close ×.

## 3. The two editors

Developer mode stays one switch with two editors behind it, and gets a clearer home: the
first row of the menu, with a one-line description of what it changes. Off (the editor a
marketing team uses): Edit and Preview, Sections / Pages / Content / Media, the page's
settings, Publish. On: Code mode, the Assistant column, edit-code buttons, source lines,
bindings, collection schemas, layouts, raw head HTML — unchanged from before.

## 4. First run

- The mode defaults to **Edit** (it was Preview), so the first click on the page edits it.
- The sidebar opens on **Sections**, never on the Assistant.
- The Sections panel shows a dismissible **Getting started** card: click text on the page to
  edit it · drag sections to reorder · Publish when ready. `studio.tip.start`.

## 5. The Sections panel

The inner tab strip (Sections / Layout / Page) goes. The panel is the sections list, and
the inspector when a section is selected — that is all a content editor sees. **Page
settings** (title, URL, layout, search listing, social sharing, advanced, duplicate,
delete) become a view of the same panel reached from the page switcher's *Page settings…*,
the Pages panel's gear on the open page, or ⌘, — with a back button, like the inspector.
The Layout tab's controls (assign, rename, create, delete) move into Page settings as one
group; creating and deleting layouts stay developer-mode only. `EditorPanel::$tab` keeps
its values (`sections | page | layout`) so nothing behind it changes; `layout` simply
renders inside the `page` view.

## 6. Menu

Brand mark → New page… · Duplicate page · New layout… (developer) ─ Developer mode
(switch + description, where the server allows it) · Appearance: Dark / Light ─ Keyboard
shortcuts (?) · View live site · Documentation. Nothing about the toolbar.

## 7. Command palette (⌘K)

Kept, cut to what people do: Add section… · New page… · Switch to <each page> · Page
settings · Sections / Pages / Content / Media · Edit / Preview / Code mode · Publish… ·
Refresh the preview · Open in a new tab · Toggle sidebar · Assistant · Desktop / Tablet /
Mobile · Dark / Light · Developer mode on/off · quick-open files in Code mode.

## 8. Shortcuts

⌘K palette · ⌘B sidebar · ⌘J assistant · ⌘, page settings · ⌥1/2/3 widths · E inspector ·
⌘D duplicate · ⌘↑/⌘↓ reorder · ⌫ delete · Esc closes an open modal, then the inspector,
then deselects (it no longer closes the sidebar) · ? shortcuts. ⌘. is gone with the dock.

## 9. Theme

Dark stays the default. The top bar sits on the frame (light `#efeff0`, dark near-black);
the sidebar, the site and the Assistant column are rounded containers a 6px gutter apart —
white in light, straight on the black in dark. Every popover is dark in both themes, with
DevDojo's enter curve; the sidebar toggle glyph and the cycling device button are DevDojo's too. One
accent (`#4c7dfa`, the canvas's selection blue). Controls run one size smaller than before
(28px bar buttons, inputs and buttons, 24px segments in a grey well with the active one
lifted white, 28px rows, 6px radius). The main menu follows the theme too.

## 10. State

```
studio.sidebar          '1' | '0'      sidebar open (default open)
studio.rail             sections | pages | content | media
studio.panel-width      px (Sections/Pages)      studio.panel-width-wide  px (Content/Media)
studio.assistant        '1' | '0'      right column open (default closed)
studio.assistant-width  px
studio.mode             edit | preview | code    (default edit)
studio.devmode, studio.theme, studio.files, studio.code-split, studio.code-size,
studio.code-view, studio.code-folders, studio.chat-mode, studio.tip.start  — as before
```
Old keys (`studio.dock`, `studio.dock-hidden`, `studio.view`, `studio.chat-float`,
`studio.chat-open`, `studio.chat-joined`) are ignored.

## 11. Contracts that do not change

`studio:*` postMessage types, `Livewire.dispatch` names, `EditorPanel`'s listeners,
`StudioPreview`'s gates, the code store (`$store.code`), `Studio.mediaPick()`,
`studio:open-library`, `studio:open-create-page`, `studio:open-publish`,
`studio:open-palette`, `studio:toast`, `studio:status`, `studio:pick`, `studio:reflow`.
`studio:canvas-pointerdown` is still posted by the canvas; nothing listens now.
