# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

Designer Studio is a Laravel package (not a standalone app) that provides a visual page builder for Laravel applications. It uses JSON file storage (no database), Livewire 3 for reactive components, and an iframe-based live preview with client-side Blade rendering.

## Build Commands

```bash
npm run build          # Build frontend assets (Vite → dist/)
npm run dev            # Start Vite dev server
```

There are no tests or linting configured in this project.

## Architecture

### Package Structure

- **Namespace**: `Designer\Studio` (PSR-4 autoloaded from `src/`)
- **Service Provider**: `StudioServiceProvider` — registers all singletons, routes, views, Livewire components, and Blade directives
- **Config**: `config/studio.php` — route prefix, middleware, storage path, output path, layout

### Backend (PHP)

**Services** (`src/Services/`):
- `StudioStorage` — low-level JSON file I/O abstraction
- `PageRepository` / `ComponentRepository` — CRUD over JSON files in `storage/studio/`
- `BladeGenerator` — converts page JSON into publishable Blade files
- `SampleDataSeeder` — seeds initial component/page data
- `YamlFormBuilder` — builds Filament forms from YAML field definitions

**Livewire Components** (`src/Livewire/`):
- `ComponentEditor` — sidebar form for editing component variables; listens for `component-selected` events and auto-saves on variable changes

**Controllers** (`src/Http/Controllers/`):
- `StudioController` — dashboard, page editor, iframe preview, and all API endpoints (CRUD pages, update components, generate Blade files)
- `AssetController` — serves compiled CSS/JS from `dist/`

**DTOs** (`src/DataTransferObjects/`):
- `PageData` and `ComponentData` — immutable readonly objects with `fromArray()`/`toArray()` methods

### Frontend

- **Vite 6** builds `resources/js/studio.js` and `resources/css/studio.css` into `dist/`
- **Tailwind CSS 4** via `@tailwindcss/vite` plugin
- **Alpine.js** handles client-side interactivity (loaded via CDN in layouts)
- **blade.js** (`resources/js/blade.js`) — client-side Blade template renderer supporting `{{ }}`, `{!! !!}`, `@if/@endif`, and null coalescing

### Iframe Communication (postMessage)

The editor uses an iframe for live preview. Parent and iframe communicate via `postMessage`:

- **Parent → Iframe**: `update-variables` (sends new variable values for re-rendering)
- **Iframe → Parent**: `component-selected` / `component-deselected` (user clicks a component)

### Data Storage

All data is JSON files under `storage/studio/`:
- `pages/*.json` — page definitions with ordered component instances and their variables
- `components/library/*.json` — component templates with HTML, field definitions, and preview variables

### Component Field Types

`text`, `textarea`, `select`, `toggle`, `colorpicker`

### Routes

All routes are prefixed with the configured `studio.path` (default: `studio`). Key routes:
- `GET /studio` — dashboard
- `GET /studio/page/{slug}` — page editor
- `GET /studio/page/{slug}/iframe` — preview iframe
- `PUT /studio/api/pages/{slug}/components` — update component order/variables
- `POST /studio/api/generate/{slug}` — generate Blade file for a page

### Artisan Commands

```bash
php artisan studio:seed           # Seed sample components and a home page
php artisan studio:uninstall      # Remove all studio data (supports --keep-generated, --keep-data)
```
