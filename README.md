# Designer Studio

**The visual editor for your Laravel site.** Pick a starting template, and its pages, sections,
and assets are added to your app as ordinary Blade files. Then anyone on the team edits those
pages visually with a live preview, and developers keep working on the same files in their own
editor. No database, no lock-in: remove Studio and the site keeps working.

- 🎨 **Visual editor** — click any section on the live canvas and edit its content; changes render as you type
- 🧩 **Eleven starter sites** — SaaS and product landing pages, studios, hospitality, and real estate, each a complete multi-page site
- 📄 **Real files** — the site lives in `resources/designer` and `public/designer`; publishing edits those files in place, attribute by attribute
- 🖼 **Layouts** — the shared header and footer around every page; edit them on any page and every page updates
- 🌀 **Global blocks** — turn any section into a synced block, place it anywhere, edit it once
- 🗂 **Content** — collections (posts, properties, drinks…) with their own pages, edited in a table
- ✍️ **Draft mode** — edits stay in a draft you can preview at `/studio/preview`; one click publishes the whole site
- 🔎 **SEO per page** — titles, descriptions, Open Graph, X cards, JSON-LD, and a sitemap
- 🛠 **Code mode** (local) — a Monaco editor over the site's files beside the live preview

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- `git` on the server that installs templates (they are cloned from GitHub)

## Installation

```bash
composer require designer/studio
```

Visit `/studio` and pick a starting template. Installing one copies its files into your app and
registers a small service provider that serves the site — then you're in the editor.

> **Production note:** the editor ships with the `web` middleware only. Before deploying,
> protect it with your own auth — see [Securing the Studio](#securing-the-studio).

## Templates

| Landing pages | | Business | |
|---|---|---|---|
| **Pilot** | AI agent framework, off-white and monochrome | **Monarch** | Product studio, bone and black with a lime accent |
| **Amber** | Warm, serif-led studio landing page | **Stone** | Calm studio site on warm paper, light/dark |
| **Draft** | Dark, Linear-grade SaaS with a changelog and docs | **Strata** | Luxury real estate with property pages |
| **Signal** | Warm SaaS marketing site with a changelog and blog | **Crema** | Coffee roaster and bar with single-origin pages |
| **Reply** | Cream-canvas help desk SaaS with customer stories | **Norden** | Photographic coffee house with a page per drink |
| **Lumen** | Deep-green landing page for studios and consultancies | | |

Each lives in its own repository in the [site-templates](https://github.com/site-templates)
organization, and you can add your own to `studio.templates.catalog` — see
[`docs/site-templates.md`](docs/site-templates.md).

```bash
php artisan studio:templates:import amber            # install from the console
php artisan studio:templates:import reply --force    # replace the installed site
```

## How it works

```
resources/designer/
    designer.json                       page titles, SEO settings, order, redirects
    css/site.css                        Tailwind v4 with the template's @theme tokens
    data/site.json                      $site — nav links, contact details, …
    data/collections/*.json             $posts, $properties, … (edited in the Content panel)
    views/pages/*.blade.php             one page per URL; index.blade.php is "/"
    views/components/layouts/*.blade.php    the <head> and the shared header/footer
    views/components/sections/*.blade.php   the sections, each with a .yml of editable fields
public/designer/                        images, scripts, uploads
app/Providers/DesignerServiceProvider.php   serves the site — yours, no dependency on Studio
```

1. **Developers** own the files: sections are Blade components with a `.yml` beside them that
   declares which parts are editable (text, images, toggles, repeaters, …).
2. **Editors** open `/studio`, click sections, and change content with a live preview. Edits
   save to a draft.
3. **Publish** writes the draft into the files — a changed heading rewrites one attribute on one
   line, and every other byte stays as you left it. Files you change yourself are picked up the
   next time the editor loads.

The site is served from `Route::fallback`, so your own routes always win.

## The editor

- **Preview / Edit / Code** — the segmented control in the top bar; Preview follows links like a browser.
- **Sections panel** — drag to reorder; duplicate, hide, make global, or delete from the row actions.
- **Add section** — every section in your site, with live-rendered previews and search.
- **Layout tab** — the sections around every page; edit one from any page and every page updates.
- **Page tab** — title, URL, and the full set of SEO settings.
- **Pages, Content, and Media** panels in the rail on the left.
- **Device toggle** — desktop, tablet (768px), and mobile (390px).
- Shortcuts: `⌘K` command palette · `⌘D` duplicate · `⌘↑`/`⌘↓` move · `⌫` delete · `Esc` deselect.

## Creating your own sections

A section is a Blade component plus a `.yml` of editable fields:

```yaml
# resources/designer/views/components/sections/banner.yml
title: Banner
description: One line and a button

fields:
    heading:
        type: text
        label: Heading
        default: "A better way to work"
    buttonText:
        type: text
        label: Button text
        default: "Get started"
```

```blade
{{-- resources/designer/views/components/sections/banner.blade.php --}}
@props(['heading' => 'A better way to work', 'buttonText' => 'Get started'])

<section class="px-6 py-24 text-center">
    <h1 class="text-5xl font-semibold tracking-tight">{{ $heading }}</h1>
    <a href="/contact" class="mt-8 inline-block rounded-full bg-black px-6 py-3 text-white">{{ $buttonText }}</a>
</section>
```

Reload `/studio` and it's in the Add-section library. Every render path uses real Blade, so
`@props`, loops, conditionals, and nested components all work.

**Field types:** `text`, `textarea`, `url`, `select`, `toggle`, `colorpicker`, `image`, and
`repeater` (repeating groups, optionally nestable; can read from a collection or site data).
The full guide is [`docs/authoring-sections.md`](docs/authoring-sections.md).

## Configuration

```bash
php artisan vendor:publish --tag=studio-config
```

| Option | Default | Purpose |
| --- | --- | --- |
| `path` | `studio` | URL prefix for the editor |
| `middleware` | `['web']` | Middleware for the editor and its API |
| `gate` | `null` | Optional ability required for every Studio route |
| `draft_mode` | `true` | Edits stay in a draft until published (`false` = edits go live at once) |
| `dev_mode` | local only | Code mode, the section code editor, and the Assistant |
| `storage_path` | `storage_path('studio')` | Studio's working data (drafts, template downloads) |
| `templates.catalog` | the eleven above | The templates the picker offers |
| `iframe.*` | — | CDN toggles, extra assets, body classes for the canvas |

## Securing the Studio

Anyone who can reach the Studio can edit your site. Lock it down with either:

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

Uploads land in `public/designer/uploads/`, limited to images (JPEG, PNG, GIF, WebP, AVIF,
SVG) up to 5 MB. Upload, publish, and render endpoints are rate-limited, and when the app runs
in production without auth protection the editor shows a warning until you lock it down.

**The trust model, plainly:** anyone with Studio access can edit everything the Studio manages.
Section fields intentionally allow raw SVG/HTML (logos, icons, custom head tags), so Studio
access is equivalent to publishing content — including markup — on your site. Give it only to
people you'd let edit your templates, and treat the editor URL like an admin panel.

## Hosting

The site is files in your app, so deploy it like the rest of your code: commit
`resources/designer` and `public/designer`. Publishing from the editor in production writes
those files on the server, so either edit locally and deploy, or run the editor on a server with
a persistent disk (and pull its changes back into git). `storage/studio` holds only drafts and
caches — a fresh server rebuilds it on the first visit to `/studio`.

## Artisan commands

```bash
php artisan studio:templates:import <slug>   # install a template (--force replaces the site)
php artisan studio:templates:sync            # clone/update the catalogued templates (--list)
php artisan studio:sync                      # re-read the site's files into the editor
php artisan studio:publish                   # publish the editor's assets to public/vendor/studio
php artisan studio:dev-reset                 # back to a fresh install (development only)
php artisan studio:uninstall                 # remove Studio's own data; the site keeps working
```

## Uninstalling

```bash
php artisan studio:uninstall
composer remove designer/studio
```

The site keeps working: its files and `app/Providers/DesignerServiceProvider.php` are yours.

## License

MIT © [Designer](https://designer.dev)
