# Changelog

All notable changes to Designer Studio are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com), and the project follows
[semantic versioning](https://semver.org) from `1.0.0` onward.

## [Unreleased]

Everything below ships in the first public release.

### Editor

- Visual editor at `/studio`: browser-style topbar, full-width URL bar that doubles as the page switcher, live canvas with per-section chrome, collapsible left panel (Sections / Layout / Page tabs), device preview widths, keyboard shortcuts.
- Section library with live previews and search — every section in the installed site (any component with a `.yml` of fields).
- Confirm-free section deletion with **Undo** from the toast; irreversible actions (page, layout, block-everywhere) keep confirmations.
- Concurrent-edit detection: writes are blocked with a reload prompt when the page or layout changed in another tab.
- Onboarding template picker over eleven starter sites — Pilot, Amber, Draft, Signal, Reply, Lumen (landing pages) and Monarch, Stone, Strata, Crema, Norden (business) — filterable by category. Installing one copies its files into `resources/designer` + `public/designer` and registers an app-owned runtime provider, so the site keeps working without Studio.
- Laravel 13 support (and Symfony 8 components).
- A section that fails to render shows its error in place on the canvas; before, it took the whole canvas down with "Undefined array key 0" (Laravel flushes the outer view's component stack on any nested render failure — `Support\NestedBlade` now restores it).
- The canvas loads the scripts a layout includes at the end of `<body>` (shaders, smooth scroll), and resolves head links that use the layout's `@props` defaults.

### Content model

- The site is ordinary files in the app (`resources/designer`); Studio's JSON in `storage/studio` is a mirror plus a draft, and publishing edits the files in place, attribute by attribute — no database.
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
