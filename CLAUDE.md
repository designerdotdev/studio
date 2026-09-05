# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Designer Studio is a Laravel package (not a standalone app) that provides a visual page builder for Laravel applications. It uses JSON file storage (no database), Livewire 3 for the editor panel, and an iframe-based live preview with client-side Blade rendering. The host app for local development is the repo two levels up (`<host-app>`), which path-symlinks this package via composer.

## Build Commands

```bash
npm run build          # Build frontend assets (Vite → dist/)
npm run dev            # Watch mode: builds Monaco once, then rebuilds dist/ on every change (re-publishing to the host's public/vendor/studio if published assets exist)
```

There are no tests or linting configured in this package. Verify changes against the host app (`php artisan serve` from the repo root, then hit `/studio`).

## Architecture

### Package Structure

- **Namespace**: `Designer\Studio` (PSR-4 from `src/`)
- **Service Provider**: `StudioServiceProvider` — singletons, routes, views, Livewire components, `@studioStyles`/`@studioScripts` directives, auto-publish of design files (merge-copy: new package sections are added to the app, existing app files never overwritten)
- **Config**: `config/studio.php` — route prefix, middleware, optional `gate`, `draft_mode`, storage/output paths, page auto-routing, iframe CDN options
- **Hardening**: every route has parameter constraints (`[a-z0-9-]+` slugs/names); upload (30/min), publish/discard/export (12/min) are throttled; `StudioController::editorNotices()` surfaces floating canvas banners for unprotected-in-production and unwritable-storage states; the editor shows a small-screen overlay below `lg`
- **Concurrent edits**: `EditorPanel::$docVersions` tracks page/layout `updated_at` at load; `guardConflict()` blocks every mutation with a Reload toast if the doc changed elsewhere (block-content edits skip the guard — blocks are shared by design)
- **SEO extras**: `/sitemap.xml` (indexable published pages, `page_routing.sitemap` config) and 301s for renamed slugs (`previous_slugs` on the page doc, written by PageRepository::update, resolved in PageController)
- **Page routing**: two STATIC routes registered in a `booted()` callback (after app routes, skipped when routes are cached): `/` (only if the app doesn't define its own root) and a single-segment `GET /{slug}` catch-all constrained to `[a-z0-9-]+`. Which pages exist is resolved at request time from live storage — `route:cache`-safe, and newly published pages are routable instantly. `/{home_slug}` 301s to `/` only when Studio owns the root route (`SiteUrls::ownsRoot()`); when the app defines its own `/`, the home page serves at `/{home_slug}` instead and the editor shows an `app-owns-root` notice
- **Claiming `/`**: `Support/WelcomeRoutePruner` auto-removes the stock Laravel welcome route from the host's `routes/web.php` (strict regex — customized routes are never touched) so the published homepage serves at `/`. Runs idempotently at seed completion, on publish (`home_claimed` in the JSON response → toast), and on editor load (self-heal); requires a live home page; clears the route cache when needed. `Support/SiteUrls::pageUrl()` is the single source for public page URLs (sitemap, previous-slug 301s, editor live-URL)
- **Dev mode**: `Support/DevMode::enabled()` — `config('studio.dev_mode')` (env `STUDIO_DEV_MODE`), default null = local environment only. When on (server gate + `$store.studio.devMode` toggle in the hamburger menu), the inspector header shows an Edit-code button that opens a Monaco modal (HTML/YAML tabs) for the selected section's source. `DevModeController` (`GET|PUT /studio/api/dev/components/{name}`) validates YAML (name key immutable), copy-on-writes to the app's `resources/views/designer/`, re-syncs the library; the frontend then refreshes the preview and dispatches `studio:code-saved` (EditorPanel reloads fields)
- **Dependencies**: livewire/livewire, symfony/yaml, symfony/process (git, for template sync) — no Filament, no Katana
- **Site templates**: whole starter sites in their own git repos, in the DevDojo `site-templates` format. See `docs/site-templates.md`

### Backend (PHP)

**Services** (`src/Services/`):
- `Storage/StudioStorage` — JSON file I/O under `storage/studio/`. Draft mode (`studio.draft_mode`, default on) adds workspaces: pages/layouts/blocks/collections/site are read/written under `draft/` when `useDraft()` is active (set by StudioController's constructor + EditorPanel::boot), while public rendering stays on the live tree. The component library is NOT workspaced. `inLive(fn)` runs a callback against live (used by Blade exports)
- `PublishService` — draft mode engine on raw paths (workspace-immune): `status()` diffs draft vs live (ignores timestamps), `publishAll()` mirrors draft→live, `discardAll()` live→draft, `ensureDraftSeeded()` seeds `draft/` from live on first boot, `syncAfterSeed()` makes freshly seeded sites start published. Publishing is always whole-site (shared layouts/blocks make partial publishes unsafe). Draft site is browsable at `/studio/preview[/{slug}]` — preview pages rewrite internal links to stay under the preview prefix and show a floating "Draft preview / Edit page" badge (`page.blade.php`, gated on `$preview`); page deletion is staged (live file removed at publish)
- `Storage/PageRepository` — page CRUD + section ops (add/remove/reorder/duplicate/hide), slug management
- `Storage/BlockRepository` — global blocks (`storage/studio/blocks/`): a synced section instance (component_ref + variables) that pages/layouts reference via lightweight placements (`{id, block_ref, order, hidden}`); `hydrate()` expands placements in all render paths, `usage()`/`deleteEverywhere()` manage cross-page placements. Editing any placement writes to the block → updates everywhere. Placements keep position/hidden local. Teal chrome in the canvas, "Global blocks" category in the Add-Section modal, Make global / Detach / Delete everywhere in the panel
- `Storage/LayoutRepository` — reusable layouts (`storage/studio/layouts/`): page-like docs whose `components` array contains one `@content` slot entry (id `__content__`); sections before it are the shared header, after it the shared footer. Pages opt in via `layout_ref`. The slot can't be removed/hidden/duplicated; all render paths (iframe, PageController, BladeGenerator) merge `regions()` around the page's own sections
- `Storage/ComponentRepository` — library CRUD, `grouped()` returns categories in canonical order (`CATEGORY_ORDER`)
- `DesignSyncService` — discovers `.yml`+`.html` pairs in `resources/views/designer/` (app copy preferred), syncs into the library; skips writes when content unchanged. Also publishes image files sitting beside designs into `public/studio-uploads/designer/` so field defaults can reference `/studio-uploads/designer/<file>` (e.g. the Atlas hero backdrop)
- `Storage/CollectionRepository` — collections (`storage/studio/collections/<name>.json`, workspaced + published like pages): `{name, title, fields: {key: {type,label,options?}}, rows: [{id,…}]}`; field types text/textarea/richtext/url/image/select/toggle/number; keys keep their case (`dateFormatted`) because sections read them
- `CollectionBinder` — resolves an instance's `bindings` (`{field: "collections.<name>"}`) into the collection's rows. `SectionRenderer::render($component, $vars, $bindings)` applies it, so every render path (canvas bootstrap in `StudioController::iframe`, `RenderController` live edits — which must `useDraft()` like the other editor controllers —, `PageController`, draft preview, `BladeGenerator`) honours bindings. `PageRepository::defaultBindings()` applies a repeater's yml `source: collections.<name>` when the section is added; `BlockRepository::hydrate` carries a block's bindings. The inspector shows a bound repeater as a locked panel (Unbind copies the rows into the instance) and offers "Bind to a collection…" on unbound ones (`EditorPanel::bindRepeater/unbindRepeater`)
- `Assistant/Engines` (finds `claude` / `codex` binaries, builds the non-interactive argv: `claude -p … --output-format stream-json --include-partial-messages --dangerously-skip-permissions --append-system-prompt … [--resume]`, `codex exec --json --full-auto …` / `codex exec resume`), `Assistant/Threads` (`storage/studio/assistant/threads/*.json`, not workspaced), `Assistant/SystemPrompt` (storage layout + the open page's instances + the selection), `Assistant/TurnRunner` (Symfony Process in `base_path()`, translates each CLI's JSON lines into the SSE events, appends the assistant message, keeps the session id for `--resume`)
- `SectionRenderer` — **the one place a section becomes HTML.** Used by the canvas, the live page, the draft preview, previews, and exports. Wraps array values in `Support\DataBag` (so `$item['x']` and `$item->x` both resolve) and injects `$site` from the site document. A render failure is reported and shown in place rather than blanking the page
- `BladeGenerator` — exports pages to static Blade files by rendering each section through `SectionRenderer`, then fencing the output in `@verbatim` when it contains anything Blade would re-compile; the site's fonts/theme/scripts are fenced into the exported `<head>`; skips hidden sections
- `Templates/TemplateSync` — clones/fast-forwards/prunes the git repos in `studio.templates.catalog` into `resources/studio-templates` (self-ignoring folder). A clone with uncommitted or unpushed work is reported and skipped, never reset (`--force` discards)
- `Templates/TemplateImporter` — turns a synced repo into a Studio site (collections in `resources/data/collections/*.json` become collection documents — schema from the sibling `.yml` `fields:` map, else inferred — and instances get `bindings` instead of inlined rows, both for `:items="$posts"` attributes and for sections that read a collection as a global): sections (with a sibling `.yml`) become library components named `<template>-<section>`, supporting components are copied to `resources/views/components/studio-templates/<template>/` with their tags rewritten, collections and `site.json` become instance values, `layouts/main.blade.php` becomes a Studio layout plus the site chrome, and `files/public/*` is published to `public/studio-templates/<template>/` with URLs rewritten. **The publish mirror direction follows `StudioStorage::workspace()`** — the editor writes to draft, the console to live; mirroring the wrong way erases the import
- `Templates/SectionTagParser` — reads a page's `<x-…>` tags into refs + literal/bound attributes; `Templates/TemplateChrome` lifts fonts, theme CSS, scripts and body/html classes out of the template's layout; `Templates/TemplateAssets` publishes and rewrites asset URLs; `Templates/TemplateCatalog` merges built-in and synced templates for the picker
- `Storage/SiteRepository` — the site document (`site/data.json`, a workspaced tree): `$site` data, theme CSS, head HTML, scripts, body/html classes. `Support\SiteChrome` renders it into every surface, handing the CSS to Tailwind's browser build as `<style type="text/tailwindcss">`
- `TemplateRegistry` — the 7 built-in onboarding templates (blank/atlas/pilot/starter/launch/studio/horizon) incl. multi-page + per-section variable overrides; non-blank templates define a `layout` key (`name`/`before`/`after`) that SampleDataSeeder turns into a shared layout applied to every seeded page (blank seeds plain pages — no layout). **Atlas is the onboarding default** (preselected in onboarding.blade.php) — a warm editorial landing page built from the 11 `atlas-*` sections (own sand palette via arbitrary Tailwind values, DM Serif Display headings, self-contained per section). **Pilot** is the port of the DevDojo Sites "Pilot" template (off-white monochrome AI-agent-framework site, Geist/Geist Mono): 8 pages (Home, Features, Pricing, Guides + 4 guide articles, since Studio has no dynamic collection pages) built from the 18 `pilot-*` sections; guide bodies live in `pilotGuidePages()` as nowdoc HTML rendered raw by `pilot-article`
- `SampleDataSeeder` — creates pages from a template

**Livewire** (`src/Livewire/`):
- `EditorPanel` (`studio::editor-panel`) — the entire side panel: sections list, inspector (field editing), Layout tab (assign/create/rename/delete layouts), page settings. Holds both the page's sections and its layout's sections; every section op routes to PageRepository or LayoutRepository by instance-id membership (`isLayoutSection`), so editing a layout section from any page updates all pages using it. Listens for `studio:select-section`, `studio:add-section` (accepts `scope: page|layout`), `studio:section-action`, `studio:undo-delete`; emits `studio:refresh-preview`, `studio:to-iframe`, `studio:toast` (supports `action: {label, dispatch}` for toast buttons like Undo), `studio:page-meta-updated` browser events. Section deletion is confirm-free and undoable: the raw instance + position is captured in `$lastDeleted` and restored by the toast's Undo (single-level; page/layout/block-everywhere deletions keep their confirms)

- `PagesPanel` (`studio::pages-panel`) — list/rename/duplicate/delete/reorder pages (`order` on the page doc, `PageRepository::reorder`), "Set as home" writes `home_slug` into the site doc (`SiteUrls::homeSlug()` is the single reader — site doc first, config fallback)
- `MediaPanel` (`studio::media-panel`) — a thin frame; the browser is Alpine over `MediaController`'s JSON endpoints (`GET/POST/PATCH/DELETE /studio/api/media…`) backed by `Services/MediaLibrary` (paths relative to `public/studio-uploads`, `..` rejected, `designer/` read-only). Picker mode: `window.Studio.mediaPick()` resolves with a URL; image fields and repeater sub-images have a Library button
- `ContentPanel` (`studio::content-panel`) — collections → rows → entry form (`livewire/content/field.blade.php` incl. a contenteditable richtext editor), schema editor, create/delete. Row saves dispatch `studio:refresh-preview`; "Edit in Content" from the inspector fires the `studio:open-collection` window event
- `AssistantPanel` (`studio::assistant-panel`, dev mode only) — threads + composer; streaming runs in Alpine against `AssistantController` (SSE). Listens to `studio:select-section` for the context chip; the crosshair sends `studio:element-select` to the iframe, which answers `studio:element-selected {sectionId, ref, path, tag, text}` (relayed as a window event). After a turn: `studio:refresh-preview` + `studio:code-saved`

**Controllers** (`src/Http/Controllers/`):
- `StudioController` — editor page, iframe doc, component/template previews, page CRUD API, image upload (`public/studio-uploads/`), Blade generation
- `PageController` — public auto-routed pages (skips hidden sections, resolves variables with type-aware defaults)
- `AssetController` — serves the compiled `dist/` assets (allowlist mirrors `StudioAssets::FILES`)
- `MediaController` — JSON for the Media panel (see `MediaLibrary`)
- `AssistantController` — `GET api/assistant/engines`, `POST api/assistant/turn` (records the user message, returns a turn id), `GET api/assistant/stream/{turn}` (SSE: `text`, `activity`, `files`, `done`, `error`), `DELETE api/assistant/stream/{turn}` (stop flag). 404 unless `DevMode::enabled()`

**DTOs**: `PageData`, `ComponentData` — `ComponentData::resolveVariables($overrides, usePreviewDefaults:)` is the single source of truth for merging stored values over field defaults (repeaters always resolve to arrays).

### Frontend

- `resources/js/studio.js` — single bundle for BOTH the editor window and the preview iframe. Contains: toasts (`Studio.toast`), the editor↔iframe postMessage bridge, keyboard shortcuts, save-status tracking (Livewire commit hooks), SortableJS drag-reorder helper, upload helper, `Studio.codeEditor` (lazy-loads the slim Monaco bundle on first modal open — `dist/studio-monaco.js` + worker files built by `esbuild.monaco.mjs`, HTML+YAML languages only; studio.js carries no editor code) + `Studio.codeModalOpen` (global shortcuts stand down while the dev-mode code modal is open), and the iframe-side `StudioPreview` runtime (selection overlay, live re-render). Boots per-document based on `window.__studioPreview` or `#studio-canvas-frame`.
- **Live re-rendering is server-side.** `StudioPreview.render()` queues a section, coalesces ~90ms of edits, and POSTs `{sections:[{id, ref, variables}]}` to `studio.api.render`; the response markup is swapped into `[data-section-content]`. A section already awaiting a response is re-queued rather than double-requested. There is no client-side Blade renderer any more (`blade.js` was deleted) and no `templates` payload in the iframe — only refs and current values travel with the document.
- `resources/css/studio.css` — Tailwind 4 with the editor design tokens (`@theme`: shell/panel/raised/ink/soft/accent…) and all `s-*` component classes (buttons, inputs, popovers, modals, preview cards, toasts).
- Editor chrome typeface: Geist (Google Fonts, loaded in the app layout).

### Views

- `components/layouts/app.blade.php` — editor shell (topbar/sidebar/main slots, dark chrome). Left of the 320px sidebar is the 48px **rail** (`s-rail`): Assistant (dev mode) / Sections / Pages / Content / Media; `$store.studio.rail` + `setRail(name, force)` (same item again collapses the panel; persisted in localStorage `studio.rail`; fires the `studio:rail` window event). `home.blade.php` mounts the five panels in the sidebar slot, one visible at a time; selecting on the canvas forces the Sections panel. The sidebar collapses via `$store.studio.sidebar` (localStorage `studio.sidebar`); the toggle is an always-visible `s-box-btn` in the topbar right of the menu button
- `home.blade.php` — editor page: topbar grouped left/centre/right (menu + sidebar toggle | a borderless **page pill** (`s-urlbar`, max-w-xs) showing the page title — status dot at rest, hover-reveal reload button on the left and chevron on the right, click opens the pages dropdown incl. "New page" | device toggle, open-draft/live link, Publish popover), canvas iframe, Add-Section modal (live scaled preview iframes per component), create-page modal
- `livewire/editor-panel.blade.php` + `livewire/fields/*.blade.php` — panel views; field partials: text, url, textarea, select, toggle, colorpicker, image (upload + Library picker), repeater (+ `sub-input` shared partial; bound state + bind menu). `livewire/pages-panel`, `media-panel`, `content-panel` (+ `content/field`, `content/schema-editor`), `assistant-panel`
- `iframe.blade.php` — canvas preview document: section wrappers with hover/selection overlay, name chips, floating toolbar, insert-between affordances; exposes `window.__studioPreview`. Layout sections render with violet chrome + a "Layout" chip tag (page sections are blue); empty layout header/footer regions show violet add-placeholders, and insert zones carry `(scope, docIndex)` so additions land in the right document. Components with `fixed: true` render in-flow (position neutralized) with a "Fixed" chip tag; wrappers carry `data-ref/title/doc-index/doc-first/doc-last/hidden/block` for the right-click **context menu** (dark, elastic `studio-menu`, built in studio.js: move/add-above-below/duplicate/make-global/edit-code/hide/delete). Dev-mode-only chrome (`</>` toolbar button, Edit code menu item) toggles via `html.studio-devmode` (localStorage `studio.devmode` + `studio:devmode` postMessage). Shortcuts: ⌘K add-section, ⌘D duplicate, ⌘↑/⌘↓ reorder, ⌫ delete
- `preview.blade.php` — non-interactive render used by picker + onboarding thumbnails
- `onboarding.blade.php` — first-run template picker. Built-in templates preview live in an iframe; synced repository templates show their shipped `thumbnail.png`. Applying one routes to `SampleDataSeeder` or `TemplateImporter` by source
- `page.blade.php` — public page document. Renders the full head from `page.meta` (see `EditorPanel::META_KEYS`): title/description/canonical/keywords, combined robots tag (noindex/nofollow toggles; draft previews force noindex), full Open Graph set, Twitter card/site/creator, per-page favicon + theme-color, validated JSON-LD, and verbatim `head_html`. `BladeGenerator::headTags()` mirrors the same tags in exports — keep them in sync. Page-tab UI groups these as Search listing (with live result preview) + collapsible Social sharing + Advanced

### Sections (`resources/views/designer/<category>/`)

Each section = `<name>.html` + `<name>.yml` (filename matches the `name` key). ~40 sections across canonical categories: banners, headers, heroes, logos, features, stats, content, gallery, testimonials, pricing, faq, team, blog, contact, newsletter, cta, footers. The 11 `atlas-*` sections form the Atlas template family and carry their own inlined sand palette + Google-Fonts links so each renders standalone. The 18 `pilot-*` sections are the Pilot family (palette as hex arbitrary values, `.pilot-mono` for Geist Mono, CSS-only motion; the hero's stage painting ships beside it as `heroes/pilot-hero-stage.jpg`). Pilot's "first item is featured" sections expose explicit lead fields + a repeater for the rest, highlighted rows use a yes/no `select` sub-field, and the comparison table pairs each cell's text with a check-mark select — all worked around the old Blade subset, and all still fine now that the subset is gone. Use HTML comments in sections, not `{{-- --}}`, when the comment should survive into the rendered page.

**The authoring contract lives in `docs/authoring-sections.md` — read it before creating or editing any section.** There is no longer a reduced Blade subset: every render path compiles through `SectionRenderer`, so `@props`, `$loop`, comparisons, nested conditionals, `@php`, and nested `<x-…>` components all work.

### Field Types

`text`, `textarea`, `url`, `select`, `toggle`, `colorpicker`, `image`, `repeater` (sub-field types: text, textarea, url, image, select; `nestable: true` enables one child level).

### Routes (prefix from `studio.path`, default `studio`)

- `GET /studio` — editor (or onboarding when no pages exist)
- `GET /studio/page/{slug}/iframe` — canvas preview document
- `GET /studio/preview/component/{name}` / `GET /studio/preview/template/{name}` — thumbnails
- `POST|PUT|DELETE /studio/api/pages…` — page + section CRUD
- `POST /studio/api/render` — live canvas re-render (batched; throttled 600/min)
- `GET /studio/preview/thumbnail/{name}` — a synced template's `thumbnail.png`
- `POST /studio/api/upload` — image uploads
- `POST /studio/api/generate/{slug}` — Blade export
- `GET|PUT /studio/api/dev/components/{name}` — dev-mode section source read/write (404 unless `DevMode::enabled()`)
- `GET|POST|PATCH|DELETE /studio/api/media…` — media library
- `GET /studio/api/assistant/engines`, `POST /studio/api/assistant/turn`, `GET|DELETE /studio/api/assistant/stream/{turn}` — the Assistant (dev mode only)

### Artisan Commands

```bash
php artisan studio:sync           # Re-sync design files into the library
php artisan studio:seed           # Seed the starter template
php artisan studio:publish        # Publish compiled assets to public/vendor/studio (--remove reverts; also: vendor:publish --tag=studio-assets)
php artisan studio:templates:sync   # Clone/pull the template repos (--list, --template=, --force)
php artisan studio:templates:import # Build a site from a synced template (--keep)
php artisan studio:dev-reset      # Reset to fresh-install state (dev)
php artisan studio:uninstall      # Remove studio data (--keep-generated, --keep-data)
```

## Gotchas

- The app's copy of `resources/views/designer/` wins over the package copy (DesignSyncService). When package sections change during development, refresh the host app's copy or delete it so it re-publishes.
- `Livewire.dispatch()` names and the postMessage protocol (`studio:*`) are shared contracts between `EditorPanel`, `home.blade.php`, `iframe.blade.php`, and `studio.js` — change them everywhere or nowhere.
- After editing `resources/css|js`, run `npm run build` (or keep `npm run dev` watching). Asset URLs come from `Support\StudioAssets::url()`: a published copy in `public/vendor/studio` wins (static file, filemtime cache buster); otherwise `AssetController` serves the package `dist/` with a per-file mtime buster. If the editor looks stale, check for a forgotten published copy (`php artisan studio:publish --remove`).
- The compiled asset list lives in several places that must stay in sync when files are added: `StudioAssets::FILES` (drives `studio:publish` + the provider's `studio-assets` publish tag), `AssetController::$allowedFiles` (adds MIME types), Vite's `ASSET_FILES` (`vite.config.js`), and `MONACO_FILES` in `esbuild.monaco.mjs`.
