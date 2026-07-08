# Changelog

All notable changes to Designer Studio are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com), and the project follows
[semantic versioning](https://semver.org) from `1.0.0` onward.

## [Unreleased]

Everything below ships in the first public release.

### Editor

- Visual editor at `/studio`: browser-style topbar, full-width URL bar that doubles as the page switcher, live canvas with per-section chrome, collapsible left panel (Sections / Layout / Page tabs), device preview widths, keyboard shortcuts.
- Section library with live previews, categories, and search — 50+ built-in designs including a full set of minimal "Basic" blocks.
- Confirm-free section deletion with **Undo** from the toast; irreversible actions (page, layout, block-everywhere) keep confirmations.
- Concurrent-edit detection: writes are blocked with a reload prompt when the page or layout changed in another tab.
- Onboarding template picker (Blank, Starter, Launch, Studio, Horizon) with live template previews.

### Content model

- Pages stored as JSON (`storage/studio/pages`) — no database.
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
