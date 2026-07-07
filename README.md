# Designer Studio

**The visual editor for your Laravel site.** Developers define sections as plain Blade + YAML files — then anyone on the team (marketing, HR, founders) edits pages visually with a live preview. No database, no lock-in: everything is files, and every page can be exported as a plain Blade view.

- 🎨 **Visual editor** — click any section on the live preview and edit its content in place
- 🧱 **30+ pre-built sections** — heroes, features, pricing, testimonials, FAQs, footers, and more, organized by category with live thumbnails
- 📄 **Multi-page** — create, duplicate, and manage as many pages as you need, with SEO settings per page
- ⚡ **Instant publishing** — pages become live routes the moment they're created (`/`, `/about`, `/pricing`, …)
- 📦 **File-based** — pages are JSON in `storage/studio/`, sections are Blade + YAML in `resources/views/designer/`. Everything versions cleanly in git
- 🛠 **Exportable** — one click writes any page to a plain `.blade.php` file you fully own
- 🪶 **Light footprint** — Livewire 3 + Alpine.js; no database tables, no migrations

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require designer/studio
```

That's it. Visit `/studio` in your app and pick a starting template — Blank, Starter, Launch (SaaS landing), Studio (agency portfolio), or Horizon (three-page company site).

> **Production note:** the Studio editor ships with the `web` middleware only. Before deploying,
> protect it with your own auth — see [Securing the Studio](#securing-the-studio).

## How it works

```
resources/views/designer/          ← section designs (Blade + YAML, published on install)
    heroes/hero-split.html         ← the template
    heroes/hero-split.yml          ← its editable fields
storage/studio/
    pages/home.json                ← page = ordered list of section instances + their content
    components/library/*.json      ← synced library (regenerated from the design files)
```

1. **Developers** create section designs: an `.html` file (Blade template) plus a `.yml` file
   declaring which parts are editable (text, images, toggles, repeaters, …).
2. **Editors** open `/studio`, click sections, and change content with a live preview.
   Everything autosaves.
3. **Pages go live automatically** at their slug (`home` → `/`, `about` → `/about`), or you can
   export them as Blade files and route them yourself.

## The editor

- **Click a section** in the canvas to edit its fields; changes render instantly as you type.
- **Sections panel** — drag to reorder; duplicate, hide, or delete from the row actions.
- **Add section** — browse the library by category with live-rendered previews and search.
- **Page tab** — rename the page, change its URL, set SEO title/description, duplicate or delete.
- **Device toggle** — preview at desktop, tablet (768px), and mobile (390px) widths.
- **Publish** — copy the live URL or export the page as a Blade file.
- Shortcuts: `Esc` deselect · `⌘D` duplicate section · `⌫` delete section.

## Creating your own sections

Add a pair of files under `resources/views/designer/<category>/`:

```yaml
# resources/views/designer/heroes/hero-simple.yml
name: hero-simple
title: Simple hero
description: Big heading with a button
category: heroes

fields:
    heading:
        type: text
        label: Heading
        default: "A better way to work"

    button_text:
        type: text
        label: Button text
        default: "Get started"
```

```blade
{{-- resources/views/designer/heroes/hero-simple.html --}}
<section class="w-full bg-white px-6 py-24 text-center">
    <h1 class="text-5xl font-semibold tracking-tight text-neutral-950">
        {{ $heading ?? 'A better way to work' }}
    </h1>
    <a href="#" class="mt-8 inline-block rounded-xl bg-neutral-900 px-6 py-3 text-sm font-medium text-white">
        {{ $button_text ?? 'Get started' }}
    </a>
</section>
```

Reload `/studio` — your section appears in the library, fully editable.

**Field types:** `text`, `textarea`, `url`, `select`, `toggle`, `colorpicker`, `image`
(with built-in uploads), and `repeater` (repeating groups, optionally nestable for menus).

Sections support a well-defined Blade subset (`{{ $var ?? '…' }}`, `@if`/`@else`, `@foreach`
over repeaters) so they render identically in the live editor, on published pages, and in Blade
exports. The full authoring guide — including the design-language conventions the built-in
library follows — lives in [`docs/authoring-sections.md`](docs/authoring-sections.md).

## Configuration

```bash
php artisan vendor:publish --tag=studio-config
```

Key options in `config/studio.php`:

| Option | Default | Purpose |
| --- | --- | --- |
| `path` | `studio` | URL prefix for the editor |
| `middleware` | `['web']` | Middleware for the editor + its API |
| `gate` | `null` | Optional ability name required for every Studio route |
| `storage_path` | `storage_path('studio')` | Where page/component JSON lives |
| `output_path` | `resource_path('views/designer')` | Where exported Blade files are written |
| `page_routing.enabled` | `true` | Auto-register a live route per page |
| `page_routing.home_slug` | `home` | Which page serves `/` |
| `iframe.*` | — | CDN toggles, extra assets, body classes for rendered pages |

## Securing the Studio

Anyone who can reach the Studio can edit your marketing site. Lock it down with either:

```php
// config/studio.php — simplest: require login
'middleware' => ['web', 'auth'],
```

or a gate for finer control:

```php
// AppServiceProvider::boot()
Gate::define('viewStudio', fn ($user) => $user->is_admin);

// config/studio.php
'gate' => 'viewStudio',
```

Image uploads (used by image fields) are stored in `public/studio-uploads/` and validated to
raster image types, 5 MB max.

## Artisan commands

```bash
php artisan studio:sync         # Re-sync section designs into the library
php artisan studio:seed         # Seed the starter template
php artisan studio:dev-reset    # Wipe studio data back to a fresh install (dev only)
php artisan studio:uninstall    # Remove studio data (--keep-generated, --keep-data)
```

## Exporting pages

Every page can be written to a plain Blade file (`Publish → Export Blade file`, or
`POST /studio/api/generate/{slug}`). Exports land in `resources/views/designer/{slug}.blade.php`,
wrapped in the layout component configured by `default_layout`. From there they're ordinary
views — route them, edit them, or delete the package entirely; your pages keep working.

## Uninstalling

```bash
php artisan studio:uninstall    # interactive; removes storage + generated files
composer remove designer/studio
```

Because published pages are just files, `--keep-generated` leaves your exported Blade views in
place so nothing on your site breaks.

## License

MIT © [Designer](https://designer.dev)
