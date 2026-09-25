# Site templates and the installed site

A site template is a whole starter site — pages, sections, a palette, and its own assets —
living in its own git repository in the Designer template repository format. Studio installs one
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
php artisan studio:templates:import starter          # install into an app with no site yet
php artisan studio:templates:import aisle --force    # replace the installed site
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
        'aisle' => [
            'repo' => 'https://github.com/designer-templates/aisle',
            'name' => 'Aisle',
            'category' => 'landing',
            'description' => '…',
        ],
        'my-template' => 'https://github.com/acme/my-template',
    ],
],
```

The catalog decides which templates the picker offers, and in what order. An entry is a
repository URL, or an array with a `repo` plus the `name`/`description` shown before it is
downloaded (the picture comes from the repository's `thumbnail.png`) and a `category` the
picker's filters group by (`starter`, `landing`, `business`, or any word of your own).

Studio ships with ten — two starting points and eight landing pages:

| Starting points | | Landing pages | |
|---|---|---|---|
| Blank | The token layer, a nav and a footer, an empty home page | Aisle | Hardware-store inventory, sage-grey with a paint-drawdown close |
| Starter | A neutral kit of ready-made sections across four pages | Ascent | App Store analytics for indie iOS developers |
| | | Bramble | Mobile pet groomers, warm and playful with drawn characters |
| | | Canary | Preview environments, warm-black with a coral horizon |
| | | Pacer | A running-club app, honey-yellow with a route map that draws itself |
| | | Pinnacle | Contractor payouts, a blue-hour treasury site |
| | | Pioneer | A travel-and-expense agent, sand and canyon orange |
| | | Quill | A knowledge base, warm cream with a pricing page and a blog |

Any repository in this format works — add your own to the list.

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

## Editing a template in Studio

A template folder can be edited through Studio by linking the installed site to it. Install
the template into a throwaway app, link it, and from then on everything Studio writes to the
site — a publish, a Code mode save, an upload or a change in the media library — is written
back into the template's `files/` at the end of the same request. Commit from the template
folder as usual. Links only work in the local environment.

```bash
php artisan studio:templates:link /path/to/templates/starter   # an absolute path…
php artisan studio:templates:link starter                      # …or a slug in STUDIO_TEMPLATE_PREVIEW_PATH
php artisan studio:templates:link                              # what is linked, and whether it is in step
php artisan studio:templates:export                            # push by hand (--force, see below)
php artisan studio:templates:link --unlink
```

The export is the inverse of installing: `resources/designer/**` goes to `files/resources/**`
and `public/designer/**` to `files/public/**`, with `/designer/images/…` URLs moved back to
`/images/…` and `@vite` entries back to `resources/…`. Files the site no longer has are removed
from `files/`; `designer.json` never enters the template. The only write outside `files/` is
the `pages` list in `template.json`, kept in step so a page added in Studio keeps its title on
the next install.

Linking needs the installed site and the folder to agree, because the export mirrors the whole
site over `files/`. An app with no site yet gets the template installed from that folder.
An app whose site differs is refused until you say which side wins: `--install` replaces the
site with the folder (the usual choice), `--export` overwrites the folder with the site.

An export never overwrites work done in the folder by hand: when `files/` changed since the
last export, the export is skipped, the editor shows a notice, and `studio:templates:link`
reports it. Re-install from the folder (`studio:templates:import <slug> --force`) to take the
hand edits, or `studio:templates:export --force` to overwrite them.

## Removing Studio

```bash
php artisan studio:uninstall      # removes Studio's working data (drafts, library cache)
composer remove designer/studio
```

`resources/designer`, `public/designer`, and `DesignerServiceProvider` stay, and the site keeps
serving exactly what was last published.
