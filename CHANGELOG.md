# Changelog

All notable changes to Designer Studio are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com), and the project follows
[semantic versioning](https://semver.org) from `1.0.0` onward.

## [Unreleased]

Everything below ships in the first public release.

### Editor

- **A quieter top bar.** The sidebar, device and Code toggles fade in when the pointer reaches the bar; at rest it is the menu, the page, the open link and Publish. A toggle that is switched on stays in view.
- While a section is being edited its name and toolbar sit in a bar across the top of the canvas, with the section as a card below it — the same margin on every side.
- A short section (a nav bar) keeps its name chip and toolbar on itself instead of hanging them over the section below.
- **Dark is the default appearance**; light is one click away in the menu.
- While a section is being edited, a header's dropdown or mega panel opens past the card instead of being cut off at its edge.
- Section thumbnails in the Add Section picker show entrance animations finished — a logo mark that rises on load is there in the picture.
- **The sidebar's tabs are one well of icons.** The panel that is showing wears its name on a lifted white segment; the others name themselves on hover.
- **Code mode's files are a tab of the sidebar** (Code, after Media). Choosing the tab is Code mode, Code mode opens on the tab, and leaving it puts the sidebar back the way it was; the other tabs keep working beside the editor.
- The inspector's header is the name of what is being edited — the "Editing" and "Page" eyebrows are gone.
- **A new install opens on the site.** The sidebar starts shut and the **Developer mode** switch starts off, so the first thing `/studio` shows is the page, edge to edge, in the editor a marketing team uses. Both are remembered once changed.
- **Dark changes the frame, not the panels.** The sidebar and the inspector are white in both appearances; dark turns the frame and the top bar near-black.
- **A selection lasts while the pointer is on its section.** Clicking a section still selects it for the shortcuts, and moving away lets go — the state that stays is Edit.
- The template picker's pictures ship with the package, so every template shows one on a new install without downloading anything (the catalog's repositories are not all public).
- **One layout.** A 44px top bar (menu, sidebar toggle, page switcher with a dark dropdown, Preview / Edit / Code in the centre, a device button that cycles Desktop → Tablet → Phone, Assistant, Publish), a resizable sidebar on the left with a tab strip — Sections · Pages · Content · Media — over one panel, the site in the middle, and (developer mode) the Assistant as a resizable column on the right. Every dropdown is dark, with DevDojo's enter curve. The floating/pinnable dock, its View switches, the five Workspace presets, the joined composer, the floating chat, and the popover/sheet panel frames are gone; nothing about where the chrome sits is a setting any more.
- First run opens in **Edit** mode on the Sections panel with a dismissible Getting-started card; the sidebar's Sections panel is the list and the inspector only. **Page settings** (title, URL, layout, search listing, social sharing, advanced, duplicate, delete) are their own view, reached from the page switcher, the panel's gear, the Pages panel, ⌘, or the menu.
- The menu is six rows: New page… / Duplicate / Page settings… / New layout… (developer), the **Developer mode** switch with a one-line description, Appearance (Dark / Light), Keyboard shortcuts (`?`), View live site, Documentation. The ⌘K palette lists pages ("Go to …"), sections, modes, panels, Publish, widths, appearance and developer mode.
- Linear-scale chrome: the top bar on a soft grey frame (`#efeff0` in light) with the sidebar, the site and the Assistant column as rounded white containers; controls one size smaller (28px buttons and inputs, 24px segmented controls with the active segment lifted white, 28px rows, 6px radius).
- Visual editor at `/studio`: live canvas with per-section chrome, device preview widths, keyboard shortcuts.
- Section library with live previews and search — every section in the installed site (any component with a `.yml` of fields).
- Confirm-free section deletion with **Undo** from the toast; irreversible actions (page, layout, block-everywhere) keep confirmations.
- Concurrent-edit detection: writes are blocked with a reload prompt when the page or layout changed in another tab.
- Onboarding template picker over eleven starter sites — Pilot, Amber, Draft, Signal, Reply, Lumen (landing pages) and Monarch, Stone, Strata, Crema, Norden (business) — filterable by category. Installing one copies its files into `resources/designer` + `public/designer` and registers an app-owned runtime provider, so the site keeps working without Studio.
- Laravel 13 support (and Symfony 8 components).
- A section that fails to render shows its error in place on the canvas; before, it took the whole canvas down with "Undefined array key 0" (Laravel flushes the outer view's component stack on any nested render failure — `Support\NestedBlade` now restores it).
- The canvas loads the scripts a layout includes at the end of `<body>` (shaders, smooth scroll), and resolves head links that use the layout's `@props` defaults.

### Content model

- The site is ordinary files in the app (`resources/designer`); Studio's JSON in `storage/studio` is a mirror plus a draft, and publishing edits the files in place, attribute by attribute — no database.
- `studio:templates:link` (local only): link the installed site to a template folder and every write Studio makes is exported back into its `files/` — the inverse of installing, with `template.json`'s page list kept in step. Refuses to overwrite hand edits made in the folder; `studio:templates:export --force` does.
- **Layouts**: shared header/footer sections wrapping pages, edited in place from any page (violet canvas chrome).
- **Global blocks**: synced section instances placeable on any page — edit once, updates everywhere; detachable per placement (teal canvas chrome).
- Field types: text, textarea, url, select, toggle, colorpicker, image (with uploads), repeater (nestable).

### Publishing

- **Draft mode** (default): all edits stage into a site-wide draft, browsable at `/studio/preview`; whole-site Publish with a change summary, Discard, staged page deletion.
- Auto-routing via a cached-route-safe catch-all; published pages live at their slug instantly. Old slugs 301 to renamed pages. `/sitemap.xml` for indexable pages.
- Per-page SEO/head control: search listing (with live result preview), Open Graph, X cards, robots toggles, canonical, favicon, theme color, JSON-LD, custom head HTML — mirrored in Blade exports.
- One-click Blade export of any page (exports reflect the published site).

### Developer experience

- Sections authored as `.html` + `.yml` pairs rendering identically in Blade, the live-preview renderer, and static exports (documented contract).
- `studio:sync`, `studio:seed`, `studio:publish` (assets), `studio:dev-reset`, `studio:uninstall`.
- Compiled assets served from the package or publishable to `public/vendor/studio`; `npm run dev` watch mode republishes automatically.
- Security posture: parameter constraints on all routes, throttled upload/publish/export endpoints, in-editor warning when the Studio is unprotected in production.
