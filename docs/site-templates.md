# Site templates

A site template is a whole starter site — pages, sections, a palette, and its own assets —
living in its own git repository. One template is maintained in one place and installed into
any app that runs Studio.

The format is the one the DevDojo `site-templates` organisation already publishes, so the
repositories are shared rather than duplicated.

## Using one

```bash
php artisan studio:templates:sync --list          # what is catalogued, what is on disk
php artisan studio:templates:sync                 # clone or fast-forward every entry
php artisan studio:templates:sync --template=monarch
php artisan studio:templates:import monarch       # build a site from it
```

Synced templates also appear in the first-run picker at `/studio`, alongside the templates
built into the package. Picking one there runs the same import.

Importing **replaces** the current site — every page, layout, and block. Pass `--keep` to
import alongside what is already there instead.

## The catalog

`config/studio.php`:

```php
'templates' => [
    'path' => resource_path('studio-templates'),
    'catalog' => [
        'monarch' => 'https://github.com/site-templates/monarch',
    ],
],
```

The catalog is the authority on which templates exist. Sync clones each entry into `path`
and prunes folders that have left the list. That folder ignores its own contents, so the
clones never reach the host app's git history.

The clones are ordinary git checkouts: edit one in place, then commit and push from inside
its folder. A clone holding uncommitted or unpushed work is never reset — sync reports it
and moves on, and only `--force` discards it.

## What a repository looks like

```
template.json                       name, description, page list
thumbnail.png                       the picture shown in the picker
files/
  public/                           images, js, favicon
  resources/
    css/*.css                       @theme tokens and the site's own rules
    data/site.json                  site-wide content, read as $site
    data/collections/*.json         repeating rows, bound to sections
    views/pages/*.blade.php         one file per page
    views/components/
      layouts/main.blade.php        fonts, stylesheet, scripts, nav, footer
      sections/*.blade.php + .yml   the sections
      *.blade.php                   supporting components (no .yml)
```

A component counts as a **section** when it ships a `.yml` beside it. That file is the
template's own contract for what an editor may change, which is exactly what Studio's
inspector needs. Everything else is a supporting component.

## What the import does

| From | To |
| --- | --- |
| `sections/*.blade.php` + `.yml` | library components, named `<template>-<section>` |
| supporting components | `resources/views/components/studio-templates/<template>/`, tags rewritten |
| `data/collections/*` | the values stored on each section instance |
| `data/site.json` | the site document, injected into sections as `$site` |
| `pages/*.blade.php` | Studio pages |
| `layouts/main.blade.php` | a Studio layout, plus the site's fonts, theme CSS, and scripts |
| `public/*` | `public/studio-templates/<template>/`, with every URL rewritten |

The template's stylesheet is stored on the site document and handed to Tailwind's browser
build as `<style type="text/tailwindcss">`, which is what lets its `@theme` tokens become
real utilities (`bg-canvas`, `text-ink`) with no build step.

Section names are prefixed with the template slug, so several templates can be installed
side by side without colliding.

## What does not come across

**Collection-driven pages.** A file like `pages/guides/[guides.slug].blade.php` is one URL
per row of a collection. Studio routes a fixed set of pages, so these are reported and
skipped; rebuild them as ordinary pages if you need them.

**Nested page URLs.** Studio page slugs are a single segment, so `pages/legal/terms.blade.php`
becomes `/legal-terms`. The import reports every page it renames.

**`404.blade.php`**, which the host application owns.
