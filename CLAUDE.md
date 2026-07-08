# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Designer Studio is a Laravel package (not a standalone app) that provides a visual page builder for Laravel applications. It uses JSON file storage (no database), Livewire 3 for the editor panel, and an iframe-based live preview with client-side Blade rendering. The host app for local development is the repo two levels up (`/Users/tonylea/Sites/designer`), which path-symlinks this package via composer.

## Build Commands

```bash
npm run build          # Build frontend assets (Vite → dist/)
npm run dev            # Watch mode: rebuilds dist/ on every change (and re-publishes to the host's public/vendor/studio if published assets exist)
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
- **Page routing**: two STATIC routes registered in a `booted()` callback (after app routes, skipped when routes are cached): `/` (only if the app doesn't define its own root) and a single-segment `GET /{slug}` catch-all constrained to `[a-z0-9-]+`. Which pages exist is resolved at request time from live storage — `route:cache`-safe, and newly published pages are routable instantly. `/{home_slug}` 301s to `/`
- **Dependencies**: livewire/livewire + symfony/yaml only (no Filament, no Katana)

### Backend (PHP)

**Services** (`src/Services/`):
- `Storage/StudioStorage` — JSON file I/O under `storage/studio/`. Draft mode (`studio.draft_mode`, default on) adds workspaces: pages/layouts/blocks are read/written under `draft/` when `useDraft()` is active (set by StudioController's constructor + EditorPanel::boot), while public rendering stays on the live tree. The component library is NOT workspaced. `inLive(fn)` runs a callback against live (used by Blade exports)
- `PublishService` — draft mode engine on raw paths (workspace-immune): `status()` diffs draft vs live (ignores timestamps), `publishAll()` mirrors draft→live, `discardAll()` live→draft, `ensureDraftSeeded()` seeds `draft/` from live on first boot, `syncAfterSeed()` makes freshly seeded sites start published. Publishing is always whole-site (shared layouts/blocks make partial publishes unsafe). Draft site is browsable at `/studio/preview[/{slug}]` — preview pages rewrite internal links to stay under the preview prefix and show a floating "Draft preview / Edit page" badge (`page.blade.php`, gated on `$preview`); page deletion is staged (live file removed at publish)
- `Storage/PageRepository` — page CRUD + section ops (add/remove/reorder/duplicate/hide), slug management
- `Storage/BlockRepository` — global blocks (`storage/studio/blocks/`): a synced section instance (component_ref + variables) that pages/layouts reference via lightweight placements (`{id, block_ref, order, hidden}`); `hydrate()` expands placements in all render paths, `usage()`/`deleteEverywhere()` manage cross-page placements. Editing any placement writes to the block → updates everywhere. Placements keep position/hidden local. Teal chrome in the canvas, "Global blocks" category in the Add-Section modal, Make global / Detach / Delete everywhere in the panel
- `Storage/LayoutRepository` — reusable layouts (`storage/studio/layouts/`): page-like docs whose `components` array contains one `@content` slot entry (id `__content__`); sections before it are the shared header, after it the shared footer. Pages opt in via `layout_ref`. The slot can't be removed/hidden/duplicated; all render paths (iframe, PageController, BladeGenerator) merge `regions()` around the page's own sections
- `Storage/ComponentRepository` — library CRUD, `grouped()` returns categories in canonical order (`CATEGORY_ORDER`)
- `DesignSyncService` — discovers `.yml`+`.html` pairs in `resources/views/designer/` (app copy preferred), syncs into the library; skips writes when content unchanged
- `BladeGenerator` — exports pages to static Blade files; statically evaluates the supported `@if` subset, keeps `@foreach` + a `@php` JSON preamble for repeaters, skips hidden sections
- `TemplateRegistry` — the 5 onboarding templates (blank/starter/launch/studio/horizon) incl. multi-page + per-section variable overrides; non-blank templates define a `layout` key (`name`/`before`/`after`) that SampleDataSeeder turns into a shared layout applied to every seeded page (blank seeds plain pages — no layout)
- `SampleDataSeeder` — creates pages from a template

**Livewire** (`src/Livewire/`):
- `EditorPanel` (`studio::editor-panel`) — the entire side panel: sections list, inspector (field editing), Layout tab (assign/create/rename/delete layouts), page settings. Holds both the page's sections and its layout's sections; every section op routes to PageRepository or LayoutRepository by instance-id membership (`isLayoutSection`), so editing a layout section from any page updates all pages using it. Listens for `studio:select-section`, `studio:add-section` (accepts `scope: page|layout`), `studio:section-action`, `studio:undo-delete`; emits `studio:refresh-preview`, `studio:to-iframe`, `studio:toast` (supports `action: {label, dispatch}` for toast buttons like Undo), `studio:page-meta-updated` browser events. Section deletion is confirm-free and undoable: the raw instance + position is captured in `$lastDeleted` and restored by the toast's Undo (single-level; page/layout/block-everywhere deletions keep their confirms)

**Controllers** (`src/Http/Controllers/`):
- `StudioController` — editor page, iframe doc, component/template previews, page CRUD API, image upload (`public/studio-uploads/`), Blade generation
- `PageController` — public auto-routed pages (skips hidden sections, resolves variables with type-aware defaults)
- `AssetController` — serves `dist/studio.js` + `dist/studio-css.css`

**DTOs**: `PageData`, `ComponentData` — `ComponentData::resolveVariables($overrides, usePreviewDefaults:)` is the single source of truth for merging stored values over field defaults (repeaters always resolve to arrays).

### Frontend

- `resources/js/studio.js` — single bundle for BOTH the editor window and the preview iframe. Contains: toasts (`Studio.toast`), the editor↔iframe postMessage bridge, keyboard shortcuts, save-status tracking (Livewire commit hooks), SortableJS drag-reorder helper, upload helper, and the iframe-side `StudioPreview` runtime (selection overlay, live re-render via blade.js). Boots per-document based on `window.__studioPreview` or `#studio-canvas-frame`.
- `resources/js/blade.js` — client-side Blade renderer: `{{ }}`, `{!! !!}`, `??` defaults, `@if/@else/@endif` (incl. `?? false`), `@foreach` with `$item['key']` access + nested `children` loops. This defines the section authoring subset — keep in sync with `BladeGenerator::evaluateConditionals` and `docs/authoring-sections.md`.
- `resources/css/studio.css` — Tailwind 4 with the editor design tokens (`@theme`: shell/panel/raised/ink/soft/accent…) and all `s-*` component classes (buttons, inputs, popovers, modals, preview cards, toasts).
- Editor chrome typeface: Geist (Google Fonts, loaded in the app layout).

### Views

- `components/layouts/app.blade.php` — editor shell (topbar/sidebar/main slots, dark chrome); sidebar sits on the LEFT and collapses via `$store.studio.sidebar` (persisted in localStorage `studio.sidebar`); collapse button lives in the panel's tab row, and a reveal button slides out from under the topbar menu button (`.s-reveal`) when collapsed
- `home.blade.php` — editor page: browser-style topbar (hamburger menu, back/forward/reload, full-width URL bar that doubles as the page switcher — save-status dot, hover-reveal chevron opens the pages dropdown incl. "New page", hover-reveal external-link icon opens the live page —, device toggle, Publish popover, sidebar-collapse toggle), canvas iframe, Add-Section modal (live scaled preview iframes per component), create-page modal
- `livewire/editor-panel.blade.php` + `livewire/fields/*.blade.php` — panel views; field partials: text, url, textarea, select, toggle, colorpicker, image (with upload), repeater (+ `sub-input` shared partial)
- `iframe.blade.php` — canvas preview document: section wrappers with hover/selection overlay, name chips, floating toolbar, insert-between affordances; exposes `window.__studioPreview`. Layout sections render with violet chrome + a "Layout" chip tag (page sections are blue); empty layout header/footer regions show violet add-placeholders, and insert zones carry `(scope, docIndex)` so additions land in the right document
- `preview.blade.php` — non-interactive render used by picker + onboarding thumbnails
- `onboarding.blade.php` — first-run template picker with live template previews
- `page.blade.php` — public page document. Renders the full head from `page.meta` (see `EditorPanel::META_KEYS`): title/description/canonical/keywords, combined robots tag (noindex/nofollow toggles; draft previews force noindex), full Open Graph set, Twitter card/site/creator, per-page favicon + theme-color, validated JSON-LD, and verbatim `head_html`. `BladeGenerator::headTags()` mirrors the same tags in exports — keep them in sync. Page-tab UI groups these as Search listing (with live result preview) + collapsible Social sharing + Advanced

### Sections (`resources/views/designer/<category>/`)

Each section = `<name>.html` + `<name>.yml` (filename matches the `name` key). ~30 sections across canonical categories: banners, headers, heroes, logos, features, stats, content, gallery, testimonials, pricing, faq, team, blog, contact, newsletter, cta, footers.

**The authoring contract lives in `docs/authoring-sections.md` — read it before creating or editing any section.** Sections must stay inside the supported Blade subset because they render in three engines (PHP Blade, blade.js, BladeGenerator).

### Field Types

`text`, `textarea`, `url`, `select`, `toggle`, `colorpicker`, `image`, `repeater` (sub-field types: text, textarea, url, image, select; `nestable: true` enables one child level).

### Routes (prefix from `studio.path`, default `studio`)

- `GET /studio` — editor (or onboarding when no pages exist)
- `GET /studio/page/{slug}/iframe` — canvas preview document
- `GET /studio/preview/component/{name}` / `GET /studio/preview/template/{name}` — thumbnails
- `POST|PUT|DELETE /studio/api/pages…` — page + section CRUD
- `POST /studio/api/upload` — image uploads
- `POST /studio/api/generate/{slug}` — Blade export

### Artisan Commands

```bash
php artisan studio:sync           # Re-sync design files into the library
php artisan studio:seed           # Seed the starter template
php artisan studio:publish        # Publish compiled assets to public/vendor/studio (--remove reverts; also: vendor:publish --tag=studio-assets)
php artisan studio:dev-reset      # Reset to fresh-install state (dev)
php artisan studio:uninstall      # Remove studio data (--keep-generated, --keep-data)
```

## Gotchas

- The app's copy of `resources/views/designer/` wins over the package copy (DesignSyncService). When package sections change during development, refresh the host app's copy or delete it so it re-publishes.
- `Livewire.dispatch()` names and the postMessage protocol (`studio:*`) are shared contracts between `EditorPanel`, `home.blade.php`, `iframe.blade.php`, and `studio.js` — change them everywhere or nowhere.
- After editing `resources/css|js`, run `npm run build` (or keep `npm run dev` watching). Asset URLs come from `Support\StudioAssets::url()`: a published copy in `public/vendor/studio` wins (static file, filemtime cache buster); otherwise `AssetController` serves the package `dist/` with a manifest-hash buster. If the editor looks stale, check for a forgotten published copy (`php artisan studio:publish --remove`).
