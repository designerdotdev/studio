# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Designer Studio is a Laravel package (not a standalone app) that provides a visual page builder for Laravel applications. It uses JSON file storage (no database), Livewire 3 for the editor panel, and an iframe-based live preview with client-side Blade rendering. The host app for local development is the repo two levels up (`/Users/tonylea/Sites/designer`), which path-symlinks this package via composer.

## Build Commands

```bash
npm run build          # Build frontend assets (Vite → dist/)
npm run dev            # Start Vite dev server
```

There are no tests or linting configured in this package. Verify changes against the host app (`php artisan serve` from the repo root, then hit `/studio`).

## Architecture

### Package Structure

- **Namespace**: `Designer\Studio` (PSR-4 from `src/`)
- **Service Provider**: `StudioServiceProvider` — singletons, routes, views, Livewire components, `@studioStyles`/`@studioScripts` directives, auto-publish of design files (merge-copy: new package sections are added to the app, existing app files never overwritten)
- **Config**: `config/studio.php` — route prefix, middleware, optional `gate`, storage/output paths, page auto-routing, iframe CDN options
- **Dependencies**: livewire/livewire + symfony/yaml only (no Filament, no Katana)

### Backend (PHP)

**Services** (`src/Services/`):
- `Storage/StudioStorage` — JSON file I/O under `storage/studio/`
- `Storage/PageRepository` — page CRUD + section ops (add/remove/reorder/duplicate/hide), slug management
- `Storage/ComponentRepository` — library CRUD, `grouped()` returns categories in canonical order (`CATEGORY_ORDER`)
- `DesignSyncService` — discovers `.yml`+`.html` pairs in `resources/views/designer/` (app copy preferred), syncs into the library; skips writes when content unchanged
- `BladeGenerator` — exports pages to static Blade files; statically evaluates the supported `@if` subset, keeps `@foreach` + a `@php` JSON preamble for repeaters, skips hidden sections
- `TemplateRegistry` — the 5 onboarding templates (blank/starter/launch/studio/horizon) incl. multi-page + per-section variable overrides
- `SampleDataSeeder` — creates pages from a template

**Livewire** (`src/Livewire/`):
- `EditorPanel` (`studio::editor-panel`) — the entire left panel: sections list, inspector (field editing), page settings. Listens for `studio:select-section`, `studio:add-section`, `studio:section-action`; emits `studio:refresh-preview`, `studio:to-iframe`, `studio:toast`, `studio:page-meta-updated` browser events

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

- `components/layouts/app.blade.php` — editor shell (topbar/sidebar/main slots, dark chrome)
- `home.blade.php` — editor page: browser-style topbar (hamburger menu, back/forward/reload, centered URL bar with integrated save-status dot, device toggle, bordered page dropdown, Publish popover), canvas iframe, Add-Section modal (live scaled preview iframes per component), create-page modal
- `livewire/editor-panel.blade.php` + `livewire/fields/*.blade.php` — panel views; field partials: text, url, textarea, select, toggle, colorpicker, image (with upload), repeater (+ `sub-input` shared partial)
- `iframe.blade.php` — canvas preview document: section wrappers with hover/selection overlay, name chips, floating toolbar, insert-between affordances; exposes `window.__studioPreview`
- `preview.blade.php` — non-interactive render used by picker + onboarding thumbnails
- `onboarding.blade.php` — first-run template picker with live template previews
- `page.blade.php` — public page document (SEO meta from page settings)

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
php artisan studio:dev-reset      # Reset to fresh-install state (dev)
php artisan studio:uninstall      # Remove studio data (--keep-generated, --keep-data)
```

## Gotchas

- The app's copy of `resources/views/designer/` wins over the package copy (DesignSyncService). When package sections change during development, refresh the host app's copy or delete it so it re-publishes.
- `Livewire.dispatch()` names and the postMessage protocol (`studio:*`) are shared contracts between `EditorPanel`, `home.blade.php`, `iframe.blade.php`, and `studio.js` — change them everywhere or nowhere.
- After editing `resources/css|js`, run `npm run build`; assets are served from `dist/` via `AssetController` with a manifest-hash cache buster.
