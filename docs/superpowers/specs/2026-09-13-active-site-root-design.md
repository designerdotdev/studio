# Active site root: editing a template in Studio

**Date:** 2026-09-13
**Status:** approved design, not yet implemented

## Problem

Studio edits exactly one site: `resources/designer` + `public/designer`, with
its working documents in `storage/studio`. In the `designer` host app that
site is the designer.dev homepage. The same app also holds the template
working trees (`templates/<slug>`, previewed read-only at `/template/<slug>`
and `/templates/<slug>`), and Tony wants to open any of those in Studio to
edit, refine, and exercise Studio against it, then publish straight back into
the template's own repo.

Studio is single-site on purpose and nobody edits two projects at once, so
this is not multi-tenancy. It is a **switch**: at any moment Studio points at
one site root, and a developer can point it at a template instead of the host
site.

## Decisions

- **Switch, not per-request root.** A `?root=` query parameter would have to
  travel through every editor URL, Livewire update and API call, and one
  missed hop silently writes template edits into the host site. The active
  root is a single value read once per request.
- **The host site keeps serving at `/`.** The public site is rendered by the
  runtime provider in the host app, which reads `resources/designer` and is
  untouched by this work. Studio's canvas, draft preview, Code mode, Media,
  Assistant and publish all follow the switch; "View live site" for a
  template points at the existing `/template/<slug>` preview.
- **Dev-only, host-only.** A template root is honored only when
  `DevMode::enabled()` and `TemplatePreview::enabled()` (local environment
  with `STUDIO_TEMPLATE_PREVIEW_PATH` naming a folder). Anywhere else the
  active root is always the host site, whatever a stray state file says.
- **Templates keep their own conventions.** A template's files say
  `/images/x.png`, `/js/main.js`, `@vite(['resources/css/site.css'])`. Studio
  neither rewrites those to `/designer/…` on the way in nor leaks
  `/template/<slug>/_files/…` into them on the way out; a dev middleware
  serves the template's `files/public` at the web root instead.
- **Nothing lands in the template repo but the site's files.** Studio's own
  state for a template (mirror, draft, library, assistant threads, and its
  `designer.json`) lives under `storage/studio/sites/<slug>/`.

## Design

### `Support\SiteRoot`

An immutable value object describing where the site being edited lives.

```
SiteRoot::host()                         resources/designer, public/designer, url base '/designer',
                                         storage <studio.storage_path>, manifest resources/designer/designer.json
SiteRoot::template(string $slug, string $dir)
                                         <dir>/files/resources, <dir>/files/public, url base '',
                                         storage <studio.storage_path>/sites/<slug>,
                                         manifest <storage>/designer.json
```

Members: `resources(path)`, `public(path)`, `url(path)`, `storage()`,
`manifest()`, `isHost()`, `slug()` (`site` for the host, the template slug
otherwise), `label()` ("Artisan"), `templateDir()` (null for the host).

`SitePaths` keeps its static API — it has ~90 call sites across the writer,
mirror, reader, installer, Code workspace, media library and the assistant
prompt — and delegates every path to the active `SiteRoot`. `FOLDER` stays as
the host's folder name; `url()` becomes root-aware. Its docblock is updated:
the host site's locations are fixed; in dev mode Studio can be pointed at a
template's working tree.

### `Support\ActiveSite`

Resolves and switches the active root. Registered as a singleton; resolved
once per request and cached.

- State file: `<studio.storage_path>/active.json` → `{"template": "artisan"}`.
  Absent, empty or `{"template": null}` means the host site.
- `current()`: the host root unless all of these hold — dev mode on, template
  preview enabled, the file names a slug, and
  `TemplatePreview::make()->directory($slug)` returns a directory. A slug
  that no longer resolves is treated as the host root **and** the file is
  cleared, so a deleted template can't wedge the editor.
- `switchTo(?string $slug)`: validates the slug the same way (null = host),
  writes the file. Throws on an unknown slug.
- The gates are checked in `current()`, not only in `switchTo()`, so
  production can never be switched by copying a file.

### Storage per root

`StudioStorage` takes its base path from the active root instead of config.
For the host that is `config('studio.storage_path')` as today, so the
existing `storage/studio` tree is untouched. For a template it is
`storage/studio/sites/<slug>/` with the same layout (`pages/`, `layouts/`,
`blocks/`, `collections/`, `site/`, `components/library/`, `draft/`,
`mirror.json`, `assistant/`). The provider's boot-time directory seeding and
draft seeding run against whichever root is active.

`StudioController::editorNotices()` currently reads the storage path from
config for the writability check; it reads it from the active root.

### The manifest for a template root

Templates ship `template.json`, not `designer.json`. For a template root,
`SitePaths::manifest()` points into the per-template storage dir, and
`SiteManifest::read()` seeds it from `template.json` when the file is
missing — the same derivation `SiteInstaller::manifest()` does today (page
names/order from `pages`, home from the files, `template` = slug). That
derivation moves to a shared helper both callers use.

Page title/order/SEO edits made in Studio therefore live in the storage dir,
not in the template repo. Writing them back into `template.json` is out of
scope.

### Rendering against a template root

Two things in Studio's render path assume the host layout:

1. **`@vite` entries.** `SiteChrome::parts()` resolves `resources/…` entries
   with `base_path()`, which for a template would pick up the host app's own
   `resources/css/site.css`. It resolves `^resources/(designer/)?` against
   `SitePaths::resources()` instead — the same rule
   `TemplatePreview::inlineVite()` already applies.
2. **Anonymous component lookup.** `<x-nav>` inside a section resolves
   through Blade's registered anonymous component paths, where the host's
   `views/components` is registered at boot by both Studio and the runtime
   provider. Every Studio site render (sections, lifted layout chrome, `php:`
   / `blade:` bindings) goes through `NestedBlade::render()`, so that is
   where the swap lives: when the active root is a template, the compiler's
   anonymous component paths are replaced with just the root's
   `views/components` for the duration of the render and restored afterwards,
   including on failure — `TemplatePreview::withTemplateComponents()` does
   exactly this and the mechanism is shared, not duplicated. The host site at
   `/` keeps its global registration and is unaffected.

   `Blade::render()` caches compiled strings by content hash. A section whose
   source is byte-identical between the host site and a template (a copied
   nav, say) would otherwise reuse a compiled file that already resolved
   `<x-nav>` to the other root. `SectionRenderer::source()` appends a Blade
   comment naming the root slug when the root is not the host, so the two
   never share a compiled file.

The provider's boot-time `Blade::anonymousComponentPath(SitePaths::components())`
keeps registering the **host** path (it exists for the canvas before the
runtime provider does); it must not follow the switch, or the live site
would pick up the template's components for shared names.

### Serving a template's public files

`Http\Middleware\ServeSiteFiles`, prepended to the HTTP kernel by the
provider only when the active root is a template. For a `GET`/`HEAD` whose
path names an existing file inside the root's `public()` (realpath-checked
against that directory, hidden segments refused, extension allowlist and
MIME table shared with `TemplatePreview::file()`), it returns that file
with `Cache-Control: no-cache`; everything else passes through. Files also
present in the host's `public/` are served by the web server first and win —
a known, acceptable limit.

This is what makes `/images/x.png` load in the canvas and draft preview, and
it is why `MediaLibrary::url()` (via `SitePaths::url()`) yields `/images/…`
for a template root: those URLs are exactly what the template repo expects
to contain.

### URLs

`SiteUrls::pageUrl()` for a template root returns
`route('studio.template-preview.show', [$slug, $page])` (the home page
without a path). `ownsRoot()`/`appDefinesRootRoute()` are host-only
questions and answer `true`/`false` respectively for a template root.

The editor's "this page is `…`" hint (`home.blade.php`) and the ⌥-click
open-in-code path in `studio.js` currently hard-code
`resources/designer/…`; both take their prefix from the server
(`SitePaths::relative(SitePaths::pages())` / `…components()`), exposed on the
editor page alongside the existing `window.__studioPages`.

### Code mode

`CodeWorkspace`'s Designer view lists the root's resources and public
directories (already via `SitePaths`), shown as `resources/` and `public/`
holding the root's folder. Its root allowlist (`LARAVEL_DIRS`) gains the
active root's two relative directories so paths under `templates/<slug>/…`
resolve; the Laravel view is unchanged. `storage/` stays unreachable.

### Guards when the root is a template

On editor load (`StudioController::index`): skip the runtime self-heal
(`RuntimeInstaller::install()`) and `WelcomeRoutePruner::claimHome()`; they
exist to make the host app serve the host site. `SiteMirror::sync()` still
runs. `editorNotices()` skips `app-owns-root` and adds:

```
id: editing-template · tone: info · dismissible: false
"Editing template Artisan — publishing writes to templates/artisan."
action: Back to site  (POST switch, template = null)
```

Commands that install into or delete the site refuse to run against a
template root with a one-line message naming the active template:
`studio:dev-reset`, `studio:uninstall`, `studio:templates:import`.
`studio:sync` works on whichever root is active.

Onboarding (`SitePaths::installed()` false) cannot happen for a template
root, because `ActiveSite` only accepts directories whose pages folder
exists.

### Switching

`POST /studio/api/site/switch` with `template` (slug, or empty for the host).
Inside the studio route group; 404 unless dev mode and template preview are
enabled. Validates the slug, calls `ActiveSite::switchTo()`, and redirects to
`/studio`.

The only entry point is the template viewer, `/templates/<slug>`
(`template-viewer.blade.php`): a small **Edit** button in the header bar
beside **Open**, rendered as a one-field form posting to the switch route.
The viewer is already local-only (its routes load only when template
preview is enabled). No button on the gallery cards.

### Config

No new config keys. `studio.storage_path` and `studio.template_preview.*`
are reused.

## Testing

The package has no test suite; verification is against the host app.

1. **Host unchanged.** With no `active.json`, `/studio` and `/` behave as
   before; `storage/studio` gains no new files besides `sites/` when a
   template is first opened.
2. **Switch.** From `/templates/artisan`, Edit → `/studio` shows Artisan's
   pages; the notice names the template; `storage/studio/sites/artisan/`
   appears with `mirror.json` and a seeded `designer.json`; `/` still serves
   the host site.
3. **Canvas fidelity.** The canvas shows Artisan's own nav/footer and
   stylesheet; its images load (`/images/…` → 200 through the middleware).
   Live re-render, inline editing and the draft preview all work.
4. **Publish round-trip.** Publish with no edits: no file in
   `templates/artisan/files` changes (`git status` clean in the template
   repo). Edit a heading, publish: only that page file changes, and the
   change reads correctly in `/template/artisan`.
5. **Code mode and Media.** The Files panel roots at
   `templates/artisan/files/{resources,public}`; a save re-syncs the
   mirror. A media upload lands in `templates/artisan/files/public/…` and
   its URL is `/…` (no `/designer`, no `/template/` prefix).
6. **Back to site.** The notice's action returns Studio to the host site
   with its previous draft intact.
7. **Gates.** With `STUDIO_DEV_MODE=false` and `active.json` naming a
   template, `/studio` edits the host site and the switch route 404s.
8. **Refusals.** `studio:dev-reset` / `studio:uninstall` /
   `studio:templates:import` exit non-zero while a template is active.
9. **`studio:inline:verify`** still reports the baseline on the host root.

## Out of scope

- Writing page titles/order back into `template.json`.
- Editing a template that is not under `STUDIO_TEMPLATE_PREVIEW_PATH`
  (e.g. one in the clone cache).
- Any change to the runtime provider stub.
- Multi-user or per-request roots.
