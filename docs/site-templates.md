# Site templates and the installed site

A site template is a whole starter site — pages, sections, a palette, and its own assets —
living in its own git repository in the DevDojo `site-templates` format. Studio installs one
into your Laravel app, and from then on **the site is a set of ordinary files in your app**:

```
resources/designer/        the site's source (the template's files/resources)
public/designer/           the site's public files (the template's files/public)
app/Providers/DesignerServiceProvider.php   the runtime that serves the site
```

Studio is the editor for those files. Remove it (`composer remove designer/studio`) and the
site keeps working, because nothing it serves comes from the package.

## Installing one

Pick a template on the first visit to `/studio`, or from the console:

```bash
php artisan studio:templates:import pilot            # install into an app with no site yet
php artisan studio:templates:import monarch --force  # replace the installed site
php artisan studio:templates:sync --list             # what is catalogued and downloaded
```

Installing:

1. downloads the repository (a shallow git clone, cached in `storage/studio/templates`);
2. copies `files/resources/**` to `resources/designer/**` and `files/public/**` to
   `public/designer/**` — only those files, nothing else from the repository;
3. moves every URL that points at a public file (`/images/hero.jpg` →
   `/designer/images/hero.jpg`) and points the layout's `@vite([...])` entries at
   `resources/designer/css/…`;
4. writes `app/Providers/DesignerServiceProvider.php` (if it is not there already) and
   registers it in `bootstrap/providers.php`;
5. reads the new site into the editor.

## The catalog

`config/studio.php`:

```php
'templates' => [
    'path' => storage_path('studio/templates'),
    'catalog' => [
        'pilot' => [
            'repo' => 'https://github.com/site-templates/pilot',
            'name' => 'Pilot',
            'description' => '…',
        ],
        'monarch' => 'https://github.com/site-templates/monarch',
    ],
],
```

The catalog decides which templates the picker offers. An entry is a repository URL, or an
array with a `repo` plus the `name`/`description` shown before it is downloaded (the picture
comes from the repository's `thumbnail.png`).

A downloaded clone is an ordinary checkout; one holding uncommitted or unpushed work is never
reset — sync reports it and moves on, and only `--force` discards it.

## What the installed site looks like

```
resources/designer/
  designer.json                       page titles, SEO settings, order, renamed-page redirects
  css/*.css                           Tailwind v4 with @theme tokens
  data/site.json                      $site, in every page and component
  data/collections/<name>.json (+yml) $<name>, likewise; the .yml types the Content panel's columns
  views/pages/*.blade.php             one page per URL; index.blade.php is "/"
  views/pages/<dir>/[posts.slug].blade.php   one page per row of the posts collection
  views/components/layouts/*.blade.php       document shells: <head> + {{ $slot }}
  views/components/sections/*.blade.php + .yml   the sections (see authoring-sections.md)
  views/components/blocks/*.blade.php         global blocks (created in the editor)
public/designer/
  images/, js/, favicon.svg, …        served as static files at /designer/…
  uploads/                            images uploaded from the editor
```

It is the same shape a Pocketknife site has — the files are portable Blade.

## The runtime

`DesignerServiceProvider` is written into your app and depends only on Laravel:

- registers `resources/designer/views/components` as an anonymous component path, so
  `<x-sections.hero>` and `<x-layouts.main>` resolve;
- answers from `Route::fallback`, so **your own routes always win**: a page, a
  `[collection.field]` page, `/sitemap.xml`, a 301 for a renamed page, or the site's own
  `404.blade.php`;
- shares `$site`, every collection, and every `data/content/<dir>/*.md` folder with the page;
- renders `@vite([...])` entries under `resources/designer` for Tailwind's browser build (the
  stylesheet inlined in `<style type="text/tailwindcss">`), so the site needs no build step —
  every other `@vite` entry goes to your app's own Vite as usual;
- adds each page's SEO settings from `designer.json` (canonical URL, robots, Open Graph, X,
  JSON-LD, extra head HTML) to the head its layout writes.

It survives `route:cache` and is yours to edit — Studio never overwrites it once it exists.

## How the editor works with the files

The files are the live site. The editor keeps a **draft** of it (in `storage/studio/draft`):
edits land there, show on the canvas and in the draft preview (`/studio/preview`), and reach
the files when you **Publish**. Publishing edits files in place — a changed heading rewrites
one attribute; every other byte of the file is left as it was.

The editor manages:

- **pages** that are compositions — one `<x-layouts.*>` tag wrapping nothing but section
  tags, whitespace, and comments;
- **layouts** — the sections before and after `{{ $slot }}` in each `layouts/*.blade.php`;
- **global blocks**, **collections**, and **site data**.

Anything else stays hand-written and is left alone: the 404 page, `[collection.field]` pages,
nested pages, and any page with markup of its own between its sections. Those are still
served, and still editable in Code mode. A new editor page can't take a URL a hand-written
page already has.

Files changed outside the editor — in Code mode, by the Assistant, in your own editor, or by a
deploy — are read back in on the next editor load. A change reaches the draft only where the
draft had no unpublished edits of its own, so nobody's work is overwritten silently.

Studio's own storage holds nothing the site needs: a fresh deploy with empty storage rebuilds
it from the files on the first visit to `/studio`.

## Removing Studio

```bash
php artisan studio:uninstall      # removes Studio's working data (drafts, library cache)
composer remove designer/studio
```

`resources/designer`, `public/designer`, and `DesignerServiceProvider` stay, and the site keeps
serving exactly what was last published.
