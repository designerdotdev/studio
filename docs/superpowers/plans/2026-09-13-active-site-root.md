# Active Site Root Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a developer point Studio at a template's working tree (`templates/<slug>`) instead of the host site, edit it with every Studio surface, and publish straight back into the template repo — while `/` keeps serving the host site.

**Architecture:** A `SiteRoot` value object describes where the edited site lives; `ActiveSite` resolves the current one from `storage/studio/active.json` (dev-only gates) and `SitePaths` delegates to it, so the ~90 existing call sites follow the switch unchanged. A template root gets its own storage tree, a seeded `designer.json`, a render-time component-path swap, and a middleware serving its `files/public` at the web root.

**Tech Stack:** PHP 8.2+, Laravel 12 package (`Designer\Studio`), Blade, Livewire 3, Alpine; no test framework — verification is scratch PHP probes bootstrapping the host app plus `curl` against the running local site.

**Spec:** `docs/superpowers/specs/2026-09-13-active-site-root-design.md`

## Global Constraints

- All code lives in the package: `/Users/tonylea/Sites/designer/packages/designer/studio` (its own git repo — commit there). The host app is `/Users/tonylea/Sites/designer`; never commit host files.
- A template root is honored only when `DevMode::enabled()` **and** `TemplatePreview::enabled()`; otherwise the host root is always active.
- The runtime provider stub (`stubs/DesignerServiceProvider.php.stub`) and the host's `app/Providers/DesignerServiceProvider.php` are not touched.
- Nothing is written into a template repo except through publish/Code mode/Media (the site's own files). Studio state for a template lives in `storage/studio/sites/<slug>/`.
- Template files keep their conventions: URLs `/images/…`, `@vite(['resources/css/site.css'])`. No `/designer/` or `/template/<slug>/_files/` rewriting.
- The provider's boot-time `Blade::anonymousComponentPath()` registers the **host** components path only.
- Comment density and naming follow the surrounding code (descriptive docblocks, `//` comments explaining why).
- The local site answers at `http://designer.test` (Herd). The template working trees live in `/Users/tonylea/Sites/designer/templates` (`STUDIO_TEMPLATE_PREVIEW_PATH`); `artisan` is the test template.
- Probe scripts go in the scratchpad dir, never in either repo. Bootstrap them with:

```php
<?php
require '/Users/tonylea/Sites/designer/vendor/autoload.php';
$app = require '/Users/tonylea/Sites/designer/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
```

---

## File Structure

**Create**
- `src/Support/SiteRoot.php` — immutable description of one site root (host or template): dirs, URL base, storage, manifest.
- `src/Support/ActiveSite.php` — reads/writes `active.json`, applies the gates, resolves the current `SiteRoot`. Singleton.
- `src/Support/ComponentPaths.php` — "render with only this anonymous component path" helper, shared by `TemplatePreview` and `NestedBlade`.
- `src/Http/Middleware/ServeSiteFiles.php` — serves the active template's `files/public` at the web root.
- `src/Http/Controllers/SiteSwitchController.php` — `POST /studio/api/site/switch`.

**Modify**
- `src/Support/SitePaths.php` — delegate to the active root; docblock.
- `src/StudioServiceProvider.php` — register `ActiveSite`; host-only component path; prepend middleware for template roots.
- `src/Services/Storage/StudioStorage.php` — base path from the active root.
- `src/Services/Site/SiteManifest.php` — `derive()` + seeding for template roots.
- `src/Services/Site/SiteInstaller.php` — use `SiteManifest::derive()`.
- `src/Support/NestedBlade.php` — component-path swap for template roots.
- `src/Services/Templates/TemplatePreview.php` — use `ComponentPaths`; `MIME` public.
- `src/Support/SiteChrome.php` — `@vite` entries resolve against the root.
- `src/Services/SectionRenderer.php` — root marker so compiled strings never cross roots.
- `src/Support/SiteUrls.php` — template roots link to `/template/<slug>`.
- `src/Http/Controllers/StudioController.php` — guards, notice, storage path.
- `resources/views/home.blade.php` — notice tone/action; path text.
- `resources/views/iframe.blade.php` + `resources/js/studio.js` — components path for ⌥-click.
- `routes/web.php` — switch route.
- `resources/views/template-viewer.blade.php` — small Edit button.
- `src/Services/CodeWorkspace.php` — root allowlist includes the site roots.
- `src/Console/Commands/{DevReset,Uninstall,TemplatesImport}.php` — refuse on a template root.
- `CLAUDE.md` — document the switch.

---

### Task 1: `SiteRoot`, `ActiveSite`, and `SitePaths` delegation

**Files:**
- Create: `src/Support/SiteRoot.php`
- Create: `src/Support/ActiveSite.php`
- Modify: `src/Support/SitePaths.php`
- Modify: `src/StudioServiceProvider.php:33-61` (register), `:73-78` (component path)

**Interfaces:**
- Produces: `SiteRoot` (`host()`, `template(string $slug, string $dir, string $label)`, `isHost()`, `slug`, `label`, `resources($path='')`, `public($path='')`, `url($path='')`, `storage()`, `manifest()`, `templateDir()`), `ActiveSite` (`current(): SiteRoot`, `switchTo(?string $slug): SiteRoot`, `available(): bool`, `stateFile(): string`, `forget(): void`). `SitePaths` API unchanged.

- [ ] **Step 1: Write the probe (the "failing test")**

Save as `<scratchpad>/probe-root.php`:

```php
<?php
require '/Users/tonylea/Sites/designer/vendor/autoload.php';
$app = require '/Users/tonylea/Sites/designer/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Designer\Studio\Support\ActiveSite;
use Designer\Studio\Support\SitePaths;

$site = app(ActiveSite::class);
$state = $site->stateFile();
@unlink($state);
$site->forget();

$host = $site->current();
assert($host->isHost(), 'default is host');
assert(SitePaths::resources() === resource_path('designer'), 'host resources');
assert(SitePaths::public() === public_path('designer'), 'host public');
assert(SitePaths::url('images/a.png') === '/designer/images/a.png', 'host url');
assert($host->storage() === storage_path('studio'), 'host storage');
assert(SitePaths::manifest() === resource_path('designer/designer.json'), 'host manifest');

$root = $site->switchTo('artisan');
assert(!$root->isHost() && $root->slug === 'artisan', 'switched');
assert(SitePaths::resources() === base_path('templates/artisan/files/resources'), 'template resources: ' . SitePaths::resources());
assert(SitePaths::public() === base_path('templates/artisan/files/public'), 'template public');
assert(SitePaths::url('images/a.png') === '/images/a.png', 'template url: ' . SitePaths::url('images/a.png'));
assert(SitePaths::url() === '/', 'template url root');
assert($root->storage() === storage_path('studio/sites/artisan'), 'template storage');
assert(SitePaths::manifest() === storage_path('studio/sites/artisan/designer.json'), 'template manifest');
assert(SitePaths::installed(), 'template counts as installed');
assert(json_decode(file_get_contents($state), true) === ['template' => 'artisan'], 'state file');

try { $site->switchTo('no-such-template'); assert(false, 'unknown slug must throw'); } catch (InvalidArgumentException $e) {}

// A slug that stops resolving falls back to the host and clears the file
file_put_contents($state, json_encode(['template' => 'no-such-template']));
$site->forget();
assert($site->current()->isHost(), 'unknown slug -> host');
assert(!is_file($state), 'stale state cleared');

// Gates: with dev mode off the file is ignored
config(['studio.dev_mode' => false]);
$site->switchTo(null); // host
file_put_contents($state, json_encode(['template' => 'artisan']));
$site->forget();
assert($site->current()->isHost(), 'dev mode off -> host');
assert(!$site->available(), 'not available with dev mode off');
config(['studio.dev_mode' => null]);

$site->forget();
$site->switchTo(null);
assert(!is_file($state) || json_decode(file_get_contents($state), true)['template'] === null, 'back to host');
echo "probe-root OK\n";
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php -d zend.assertions=1 -d assert.exception=1 <scratchpad>/probe-root.php`
Expected: fatal error — class `Designer\Studio\Support\ActiveSite` not found.

- [ ] **Step 3: Create `SiteRoot`**

```php
<?php

namespace Designer\Studio\Support;

/**
 * Where the site Studio is editing lives.
 *
 * The host site is fixed: `resources/designer` + `public/designer`, served
 * at `/designer/…`, with Studio's documents in `studio.storage_path`. In
 * dev mode Studio can instead be pointed at a template's working tree
 * (`templates/<slug>/files/{resources,public}`), whose public files are
 * addressed from the web root and whose Studio documents — including the
 * designer.json a template repo does not carry — live under
 * `<storage>/sites/<slug>` so nothing but the site's own files lands in
 * the template repository.
 */
final class SiteRoot
{
    private function __construct(
        public readonly string $slug,
        public readonly string $label,
        private readonly string $resourcesDir,
        private readonly string $publicDir,
        private readonly string $urlBase,
        private readonly string $storageDir,
        private readonly string $manifestFile,
        private readonly ?string $templateDir,
    ) {
    }

    /** The site installed into the host application. */
    public static function host(): self
    {
        $storage = rtrim((string) config('studio.storage_path', storage_path('studio')), '/');

        return new self(
            slug: 'site',
            label: 'Site',
            resourcesDir: resource_path(SitePaths::FOLDER),
            publicDir: public_path(SitePaths::FOLDER),
            urlBase: '/' . SitePaths::FOLDER,
            storageDir: $storage,
            manifestFile: resource_path(SitePaths::FOLDER . '/' . SitePaths::MANIFEST),
            templateDir: null,
        );
    }

    /** A template repository's working tree, edited in place. */
    public static function template(string $slug, string $dir, string $label): self
    {
        $dir = rtrim($dir, '/');
        $storage = rtrim((string) config('studio.storage_path', storage_path('studio')), '/') . '/sites/' . $slug;

        return new self(
            slug: $slug,
            label: $label,
            resourcesDir: $dir . '/files/resources',
            publicDir: $dir . '/files/public',
            urlBase: '',
            storageDir: $storage,
            manifestFile: $storage . '/' . SitePaths::MANIFEST,
            templateDir: $dir,
        );
    }

    public function isHost(): bool
    {
        return $this->templateDir === null;
    }

    public function resources(string $path = ''): string
    {
        return $this->join($this->resourcesDir, $path);
    }

    public function public(string $path = ''): string
    {
        return $this->join($this->publicDir, $path);
    }

    /** Root-relative URL of a public file: "/designer/…" for the host, "/…" for a template. */
    public function url(string $path = ''): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return ($this->urlBase === '' && $path === '') ? '/' : $this->urlBase . ($path === '' ? '' : '/' . $path);
    }

    public function storage(): string
    {
        return $this->storageDir;
    }

    public function manifest(): string
    {
        return $this->manifestFile;
    }

    public function templateDir(): ?string
    {
        return $this->templateDir;
    }

    private function join(string $base, string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === '' ? $base : $base . '/' . $path;
    }
}
```

- [ ] **Step 4: Create `ActiveSite`**

```php
<?php

namespace Designer\Studio\Support;

use Designer\Studio\Services\Templates\TemplatePreview;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Which site root Studio is editing right now.
 *
 * The host site, unless — in dev mode, with the template preview folder
 * configured — `<storage>/active.json` names a template in that folder.
 * The gates are applied on every read, not just when switching, so a
 * copied state file can never point production Studio at anything but
 * the host site. Resolved once per request.
 */
final class ActiveSite
{
    public const STATE = 'active.json';

    private ?SiteRoot $current = null;

    public function current(): SiteRoot
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $slug = $this->available() ? $this->read() : null;
        $root = $slug === null ? null : $this->resolve($slug);

        if ($slug !== null && $root === null) {
            // The template it named is gone (or is no longer one): forget it
            // rather than leave the editor pointing at nothing.
            $this->write(null);
        }

        return $this->current = $root ?? SiteRoot::host();
    }

    /**
     * Point Studio at a template (or back at the host site with null).
     *
     * @throws \InvalidArgumentException when the slug is not a template in the preview folder
     */
    public function switchTo(?string $slug): SiteRoot
    {
        $slug = $slug === null || trim($slug) === '' ? null : trim($slug);

        if ($slug !== null && (! $this->available() || $this->resolve($slug) === null)) {
            throw new \InvalidArgumentException("[{$slug}] is not a template in the preview folder.");
        }

        $this->write($slug);
        $this->forget();

        return $this->current();
    }

    /** Whether switching is possible at all: dev mode on and a template folder configured. */
    public function available(): bool
    {
        return DevMode::enabled() && TemplatePreview::enabled();
    }

    /** Drop the per-request cache (after the state file changed). */
    public function forget(): void
    {
        $this->current = null;
    }

    /** The state file, always beside the host site's Studio documents. */
    public function stateFile(): string
    {
        return SiteRoot::host()->storage() . '/' . self::STATE;
    }

    protected function read(): ?string
    {
        $file = $this->stateFile();
        $decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $slug = is_array($decoded) ? ($decoded['template'] ?? null) : null;

        return is_string($slug) && preg_match('/^[a-z0-9-]+$/', $slug) ? $slug : null;
    }

    protected function write(?string $slug): void
    {
        $file = $this->stateFile();

        if ($slug === null) {
            if (is_file($file)) {
                File::delete($file);
            }

            return;
        }

        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode(['template' => $slug], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    protected function resolve(string $slug): ?SiteRoot
    {
        $preview = TemplatePreview::make();
        $dir = $preview->directory($slug);

        if ($dir === null) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($dir . '/template.json'), true);
        $label = is_array($manifest) && is_string($manifest['name'] ?? null) ? $manifest['name'] : Str::headline($slug);

        return SiteRoot::template($slug, $dir, $label);
    }
}
```

- [ ] **Step 5: Make `SitePaths` delegate**

Replace the whole of `src/Support/SitePaths.php` with:

```php
<?php

namespace Designer\Studio\Support;

/**
 * Where the site Studio edits lives in the host application.
 *
 * A template's `files/resources` tree is installed to `resources/designer`
 * and its `files/public` tree to `public/designer`, so a developer always
 * knows where every file of the site is. Those locations are fixed on
 * purpose: the runtime provider Studio installs into the app reads the same
 * two folders, and it has to keep working after Studio itself is removed.
 *
 * In dev mode Studio can instead be pointed at a template's working tree
 * (see ActiveSite / SiteRoot); every path here follows that switch, so the
 * writer, mirror, reader, Code mode and the media library edit the template
 * in place. The runtime provider does not follow it — `/` keeps serving the
 * host site.
 */
final class SitePaths
{
    /** The folder name used under both resources/ and public/ for the host site. */
    public const FOLDER = 'designer';

    /** Studio's own settings for the site (titles, SEO, page order). */
    public const MANIFEST = 'designer.json';

    /** Folder (under components/) that holds global blocks. */
    public const BLOCKS = 'blocks';

    /** Folder (under components/) that holds page layouts. */
    public const LAYOUTS = 'layouts';

    /** Folder (under the public dir) where media uploads land. */
    public const UPLOADS = 'uploads';

    /** The root every path below is relative to. */
    public static function root(): SiteRoot
    {
        return app(ActiveSite::class)->current();
    }

    /** Absolute path inside the site's resources folder. */
    public static function resources(string $path = ''): string
    {
        return self::root()->resources($path);
    }

    /** Absolute path inside the site's public folder. */
    public static function public(string $path = ''): string
    {
        return self::root()->public($path);
    }

    /** Root-relative URL for a public file ("/designer/…" for the host site). */
    public static function url(string $path = ''): string
    {
        return self::root()->url($path);
    }

    public static function views(string $path = ''): string
    {
        return self::join(self::resources('views'), $path);
    }

    public static function pages(string $path = ''): string
    {
        return self::join(self::views('pages'), $path);
    }

    public static function components(string $path = ''): string
    {
        return self::join(self::views('components'), $path);
    }

    public static function data(string $path = ''): string
    {
        return self::join(self::resources('data'), $path);
    }

    public static function manifest(): string
    {
        return self::root()->manifest();
    }

    /** A site is installed once its pages folder exists. */
    public static function installed(): bool
    {
        return is_dir(self::pages());
    }

    /** Path relative to the application root, for messages and the code tree. */
    public static function relative(string $absolute): string
    {
        $base = rtrim(base_path(), '/') . '/';

        return str_starts_with($absolute, $base) ? substr($absolute, strlen($base)) : $absolute;
    }

    protected static function join(string $base, string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === '' ? $base : $base . '/' . $path;
    }
}
```

- [ ] **Step 6: Register the singleton and pin the boot-time component path to the host**

In `src/StudioServiceProvider.php` add after line 38 (`$this->app->singleton(StudioStorage::class);`):

```php
        $this->app->singleton(\Designer\Studio\Support\ActiveSite::class);
```

Replace lines 73-78:

```php
        // Sections compose the site's other components (<x-nav>, an icon…).
        // The runtime provider registers the same path for the live site;
        // Studio needs it for the canvas even before that provider exists.
        // Always the host site's path: when Studio is switched to a template
        // its components are swapped in per render (NestedBlade), so the
        // live site never resolves a shared name to the template's file.
        $hostComponents = \Designer\Studio\Support\SiteRoot::host()->resources('views/components');

        if (is_dir($hostComponents)) {
            Blade::anonymousComponentPath($hostComponents);
        }
```

- [ ] **Step 7: Run the probe**

Run: `php -d zend.assertions=1 -d assert.exception=1 <scratchpad>/probe-root.php`
Expected: `probe-root OK`. Also `curl -s -o /dev/null -w '%{http_code}\n' http://designer.test/studio` → `200` (host unchanged).

- [ ] **Step 8: Commit (package repo)**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add src/Support/SiteRoot.php src/Support/ActiveSite.php src/Support/SitePaths.php src/StudioServiceProvider.php
git commit -m "Add SiteRoot/ActiveSite: SitePaths follows a switchable site root"
```

---

### Task 2: Per-root storage and a seeded manifest

**Files:**
- Modify: `src/Services/Storage/StudioStorage.php:25-28`
- Modify: `src/Services/Site/SiteManifest.php`
- Modify: `src/Services/Site/SiteInstaller.php:184-225` (+ its `SiteManifest::write($this->manifest($slug, $dir))` call at `:71`)
- Modify: `src/Http/Controllers/StudioController.php:112`

**Interfaces:**
- Consumes: `ActiveSite::current()`, `SiteRoot::storage()/templateDir()/slug`.
- Produces: `SiteManifest::derive(string $template, array $templateManifest): array` (public static). `SiteManifest::read()` seeds and writes the manifest for a template root when the file is missing.

- [ ] **Step 1: Probe**

Save as `<scratchpad>/probe-storage.php`:

```php
<?php
require '/Users/tonylea/Sites/designer/vendor/autoload.php';
$app = require '/Users/tonylea/Sites/designer/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Designer\Studio\Services\Site\SiteManifest;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\ActiveSite;
use Illuminate\Support\Facades\File;

$site = app(ActiveSite::class);
$site->switchTo('artisan');
File::deleteDirectory(storage_path('studio/sites/artisan'));
$app->forgetInstance(StudioStorage::class);

$storage = app(StudioStorage::class);
assert($storage->getBasePath() === storage_path('studio/sites/artisan'), 'storage per root: ' . $storage->getBasePath());

$manifest = SiteManifest::read();
assert($manifest['template'] === 'artisan', 'template slug');
assert(isset($manifest['pages']['home']) && $manifest['pages']['home']['title'] === 'Home', 'home page seeded: ' . json_encode($manifest['pages']));
assert(is_file(storage_path('studio/sites/artisan/designer.json')), 'manifest written into storage');
assert(!is_file(base_path('templates/artisan/designer.json')) && !is_file(base_path('templates/artisan/files/resources/designer.json')), 'nothing in the template repo');

$site->switchTo(null);
$app->forgetInstance(StudioStorage::class);
assert(app(StudioStorage::class)->getBasePath() === storage_path('studio'), 'host storage unchanged');
echo "probe-storage OK\n";
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php -d zend.assertions=1 -d assert.exception=1 <scratchpad>/probe-storage.php`
Expected: assertion `storage per root` fails (base path is still `storage/studio`).

- [ ] **Step 3: `StudioStorage` takes its base path from the active root**

Replace lines 25-28 of `src/Services/Storage/StudioStorage.php`:

```php
    public function __construct(\Designer\Studio\Support\ActiveSite $site)
    {
        // The host site's documents live in studio.storage_path; a template
        // being edited in place gets its own tree under <storage>/sites/<slug>
        $this->basePath = $site->current()->storage();
    }
```

- [ ] **Step 4: `SiteManifest::derive()` + seeding**

In `src/Services/Site/SiteManifest.php`, add `use Illuminate\Support\Str;` and `use Designer\Studio\Support\ActiveSite;`, then replace `read()`:

```php
    public static function read(): array
    {
        $path = SitePaths::manifest();

        if (! is_file($path)) {
            $root = app(ActiveSite::class)->current();

            // A template repo carries template.json, not designer.json:
            // derive the first manifest from it, exactly as installing the
            // template would, and keep it with Studio's documents
            if (! $root->isHost()) {
                $template = json_decode((string) @file_get_contents($root->templateDir() . '/template.json'), true);
                $seed = self::normalise(self::derive($root->slug, is_array($template) ? $template : []));

                // Written directly: write() consults read() for its no-op
                // check, which would come straight back here
                File::ensureDirectoryExists(dirname($path));
                File::put($path, self::encode($seed));

                return $seed;
            }
        }

        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::normalise(is_array($decoded) ? $decoded : []);
    }

    /**
     * The first designer.json for a site: which template it is, and the page
     * names and order its template.json lists (the editor's Pages panel
     * reads them; a page file only knows its document title).
     */
    public static function derive(string $template, array $templateManifest): array
    {
        $names = array_values(array_filter((array) ($templateManifest['pages'] ?? []), 'is_string'));
        $home = is_file(SitePaths::pages('home.blade.php')) ? 'index' : 'home';
        $pages = [];

        foreach (glob(SitePaths::pages('*.blade.php')) ?: [] as $file) {
            $base = basename($file, '.blade.php');

            if ($base === '404' || ! preg_match('/^[a-z0-9-]+$/', $base)) {
                continue;
            }

            $key = $base === 'index' ? $home : $base;
            $position = null;

            foreach ($names as $i => $name) {
                if (Str::slug($name) === $base || ($base === 'index' && in_array(Str::slug($name), ['home', 'index'], true))) {
                    $position = $i;
                    $pages[$key] = ['title' => $name, 'order' => $i];
                    break;
                }
            }

            if ($position === null) {
                $pages[$key] = ['title' => $base === 'index' ? 'Home' : Str::headline($base)];
            }
        }

        return [
            'template' => $template,
            'home' => $home,
            'pages' => $pages,
            'layouts' => [],
            'blocks' => [],
        ];
    }
```

`write()` stays as is: it calls `read()` for its no-op check, and `read()` now writes the seed itself (not through `write()`) so the two never recurse.

- [ ] **Step 5: `SiteInstaller` uses `derive()`**

Delete `SiteInstaller::manifest()` (lines 184-225, including its docblock) and change line 71 to:

```php
        SiteManifest::write(SiteManifest::derive($slug, $this->sync->manifest($slug) ?? []));
```

Remove the now-unused `use Illuminate\Support\Str;` from `SiteInstaller.php` only if nothing else in the file uses `Str` (grep first).

- [ ] **Step 6: Storage writability notice reads the real base path**

In `src/Http/Controllers/StudioController.php` replace line 112:

```php
        $storagePath = app(\Designer\Studio\Services\Storage\StudioStorage::class)->getBasePath();
```

- [ ] **Step 7: Run the probe**

Run: `php -d zend.assertions=1 -d assert.exception=1 <scratchpad>/probe-storage.php`
Expected: `probe-storage OK`. Then `cd /Users/tonylea/Sites/designer/templates/artisan && git status --short` → empty (nothing written into the template repo).

- [ ] **Step 8: Commit**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add src/Services/Storage/StudioStorage.php src/Services/Site/SiteManifest.php src/Services/Site/SiteInstaller.php src/Http/Controllers/StudioController.php
git commit -m "Per-root Studio storage and a designer.json seeded from template.json"
```

---

### Task 3: Rendering against a template root

**Files:**
- Create: `src/Support/ComponentPaths.php`
- Modify: `src/Support/NestedBlade.php:23-34`
- Modify: `src/Services/Templates/TemplatePreview.php:40` (`MIME` public), `:292-313` (use the helper)
- Modify: `src/Support/SiteChrome.php:140-145`
- Modify: `src/Services/SectionRenderer.php:56-75`

**Interfaces:**
- Produces: `ComponentPaths::only(string $path, \Closure $render): mixed`. `TemplatePreview::MIME` becomes `public const`.

- [ ] **Step 1: Probe (HTTP)**

Save as `<scratchpad>/probe-render.sh`:

```bash
#!/bin/zsh
set -e
STATE=/Users/tonylea/Sites/designer/storage/studio/active.json
echo '{"template":"artisan"}' > "$STATE"
# distinctive token from artisan's stylesheet
TOKEN=$(grep -oE '\-\-[a-z-]+:' /Users/tonylea/Sites/designer/templates/artisan/files/resources/css/site.css | head -1)
echo "token: $TOKEN"
HOME_HTML=$(curl -s http://designer.test/studio/page/home/iframe)
echo "$HOME_HTML" | grep -q "text/tailwindcss" || { echo "FAIL: no inlined stylesheet"; exit 1; }
echo "$HOME_HTML" | grep -qF "$TOKEN" || { echo "FAIL: artisan css not inlined"; exit 1; }
# the host site's nav must not leak in: pick a string only the host nav has
HOST_NAV=$(grep -oE 'href="/(about|services|journal)"' /Users/tonylea/Sites/designer/resources/designer/views/components/nav.blade.php | head -1 || true)
if [ -n "$HOST_NAV" ]; then echo "$HOME_HTML" | grep -qF "$HOST_NAV" && { echo "FAIL: host nav leaked"; exit 1; }; fi
curl -s -o /dev/null -w 'studio %{http_code}\n' http://designer.test/studio | grep -q 200 || { echo "FAIL: /studio"; exit 1; }
curl -s -o /dev/null -w 'preview %{http_code}\n' http://designer.test/studio/preview | grep -q 200 || { echo "FAIL: draft preview"; exit 1; }
rm -f "$STATE"
curl -s -o /dev/null -w 'host / %{http_code}\n' http://designer.test/ | grep -q 200 || { echo "FAIL: host /"; exit 1; }
echo "probe-render OK"
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `zsh <scratchpad>/probe-render.sh`
Expected: `FAIL: artisan css not inlined` (SiteChrome still resolves `resources/css/site.css` against the host app) — or a 500 on the iframe. Either is the failing state.

- [ ] **Step 3: Create `ComponentPaths`**

```php
<?php

namespace Designer\Studio\Support;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * Render with one folder as Blade's only anonymous component path.
 *
 * The host site's components path is registered at boot (by Studio and by
 * the runtime provider) and would win for every shared name — <x-nav>
 * would be the host's nav inside a template's hero. Blade has no public
 * way to unregister a path, so the compiler's list is swapped for the
 * render and restored afterwards, even when the render throws. Namespaces
 * the paths registered earlier already added to the view factory are left
 * alone: compiled views reference them by hash.
 */
final class ComponentPaths
{
    public static function only(string $path, \Closure $render): mixed
    {
        $compiler = app('blade.compiler');
        $swap = \Closure::bind(function (array $paths): array {
            $previous = $this->anonymousComponentPaths;
            $this->anonymousComponentPaths = $paths;

            return $previous;
        }, $compiler, BladeCompiler::class);

        $previous = $swap([]);

        try {
            if (is_dir($path)) {
                $compiler->anonymousComponentPath($path);
            }

            return $render();
        } finally {
            $swap($previous);
        }
    }
}
```

- [ ] **Step 4: `TemplatePreview` uses it; `MIME` goes public**

In `src/Services/Templates/TemplatePreview.php` change line 40 `protected const MIME = [` to `public const MIME = [`, and replace `withTemplateComponents()` (lines 285-313, docblock included) with:

```php
    /** Render with the template's components as the only anonymous component path. */
    protected function withTemplateComponents(string $components, \Closure $render): string
    {
        return \Designer\Studio\Support\ComponentPaths::only($components, $render);
    }
```

Remove the now-unused `use Illuminate\View\Compilers\BladeCompiler;` import.

- [ ] **Step 5: `NestedBlade` swaps components for a template root**

Replace `NestedBlade::render()` (lines 23-34):

```php
    public static function render(string $html, array $data = [], bool $deleteCachedView = false): string
    {
        $factory = app('view');
        $state = self::state($factory);
        $render = fn () => Blade::render($html, $data, $deleteCachedView);

        try {
            // A template edited in place must resolve <x-nav> to its own
            // nav, not the host site's (registered globally at boot)
            $root = SitePaths::root();

            return $root->isHost() ? $render() : ComponentPaths::only($root->resources('views/components'), $render);
        } catch (\Throwable $e) {
            self::restore($factory, $state);

            throw $e;
        }
    }
```

(`SitePaths` and `ComponentPaths` are in the same namespace — no imports needed.)

- [ ] **Step 6: `SiteChrome` resolves `@vite` entries against the root**

Replace lines 140-145 of `src/Support/SiteChrome.php` (the `extract(...)` call) with:

```php
        return $this->cache[$key] = $this->chrome->extract(
            (string) file_get_contents($file),
            $data,
            function (string $entry): ?string {
                // `resources/css/site.css` (a template's spelling) and
                // `resources/designer/css/site.css` (the installed one) both
                // name the site's own stylesheet; anything else is the app's
                if (preg_match('#^resources/(?:' . SitePaths::FOLDER . '/)?(.+)$#', $entry, $m) && is_file($site = SitePaths::resources($m[1]))) {
                    return $site;
                }

                return str_starts_with($entry, 'resources/') ? base_path($entry) : null;
            }
        ) + $empty;
```

- [ ] **Step 7: `SectionRenderer` marks compiled strings with the root**

`Blade::render()` caches compiled strings by content hash; a section byte-identical between the host and a template would reuse a compiled file whose `<x-nav>` already resolved to the other root. In `src/Services/SectionRenderer.php`, in `renderHtml()` (line 71-75), change the `NestedBlade::render(...)` call to pass the marked source:

```php
            return NestedBlade::render($this->marked($this->source($html, $fields, $instrument)), $this->context($variables));
```

and the equivalent call in `render()` at line 59 — whatever expression it passes as the first argument, wrap it in `$this->marked(...)`. Add the method after `source()`:

```php
    /**
     * Blade::render() caches compiled strings by content hash, so a section
     * whose source is identical in the host site and in a template being
     * edited would share one compiled file — with <x-nav> resolved to
     * whichever root compiled it first. A comment naming the root keeps
     * them apart; it renders to nothing.
     */
    protected function marked(string $source): string
    {
        $root = SitePaths::root();

        return $root->isHost() ? $source : $source . "\n{{-- studio-root:{$root->slug} --}}";
    }
```

Add `use Designer\Studio\Support\SitePaths;` if the file lacks it.

- [ ] **Step 8: Run the probe**

Run: `zsh <scratchpad>/probe-render.sh`
Expected: `probe-render OK`. Then open `http://designer.test/studio` in a browser with `active.json` set to artisan (`echo '{"template":"artisan"}' > storage/studio/active.json`): the canvas shows Artisan with its own nav/footer and styling; select a section and edit a heading — the live re-render works. Images will still 404 until Task 4. Remove the file afterwards.

- [ ] **Step 9: Commit**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add src/Support/ComponentPaths.php src/Support/NestedBlade.php src/Services/Templates/TemplatePreview.php src/Support/SiteChrome.php src/Services/SectionRenderer.php
git commit -m "Render a template root with its own components and stylesheet"
```

---

### Task 4: Serve a template's public files; template-aware page URLs

**Files:**
- Create: `src/Http/Middleware/ServeSiteFiles.php`
- Modify: `src/StudioServiceProvider.php` (boot, after the routes are loaded)
- Modify: `src/Support/SiteUrls.php:26-39`

**Interfaces:**
- Consumes: `TemplatePreview::MIME` (public since Task 3), `ActiveSite::current()`.

- [ ] **Step 1: Probe**

Save as `<scratchpad>/probe-files.sh`:

```bash
#!/bin/zsh
set -e
STATE=/Users/tonylea/Sites/designer/storage/studio/active.json
IMG=$(cd /Users/tonylea/Sites/designer/templates/artisan/files/public && find images -type f | head -1)
echo "image: /$IMG"
echo '{"template":"artisan"}' > "$STATE"
curl -s -o /dev/null -w '%{http_code} %{content_type}\n' "http://designer.test/$IMG" | grep -qE '^200 image/' || { echo "FAIL: template image not served"; exit 1; }
curl -s -o /dev/null -w '%{http_code} %{content_type}\n' "http://designer.test/js/main.js" | grep -qE '^200 text/javascript' || { echo "FAIL: template js not served"; exit 1; }
curl -s -o /dev/null -w '%{http_code}\n' "http://designer.test/images/../../resources/data/site.json" | grep -qv 200 || { echo "FAIL: traversal served"; exit 1; }
curl -s -o /dev/null -w '%{http_code}\n' "http://designer.test/.gitignore" | grep -qv 200 || { echo "FAIL: dotfile served"; exit 1; }
rm -f "$STATE"
curl -s -o /dev/null -w '%{http_code}\n' "http://designer.test/$IMG" | grep -q 404 || { echo "FAIL: served with host root active"; exit 1; }
echo "probe-files OK"
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `zsh <scratchpad>/probe-files.sh`
Expected: `FAIL: template image not served` (404 from the runtime's fallback).

- [ ] **Step 3: Create the middleware**

```php
<?php

namespace Designer\Studio\Http\Middleware;

use Closure;
use Designer\Studio\Services\Templates\TemplatePreview;
use Designer\Studio\Support\ActiveSite;
use Illuminate\Http\Request;

/**
 * Serves the active template's files/public at the web root.
 *
 * A template's pages say `/images/hero.jpg` and `/js/main.js`, addressed
 * from the root as they will be once the template is installed. Studio
 * neither rewrites those on the way into the canvas nor writes a different
 * spelling back into the files; while a template is being edited this
 * middleware answers such requests from its public folder instead. Only
 * prepended by the provider when the active root is a template, so the
 * host site never pays for it. Files also present in the host's public/
 * are served by the web server before Laravel sees them and win.
 */
class ServeSiteFiles
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $root = app(ActiveSite::class)->current();
        $path = trim($request->path(), '/');

        // hidden segments never resolve; '' is the home page
        if ($root->isHost() || $path === '' || preg_match('#(^|/)\.#', $path)) {
            return $next($request);
        }

        $public = realpath($root->public());
        $file = $public === false ? false : realpath($public . '/' . $path);

        if ($file === false || ! is_file($file) || ! str_starts_with($file, $public . DIRECTORY_SEPARATOR)) {
            return $next($request);
        }

        $type = TemplatePreview::MIME[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;

        if ($type === null) {
            return $next($request);
        }

        return response()->file($file, ['Content-Type' => $type, 'Cache-Control' => 'no-cache']);
    }
}
```

- [ ] **Step 4: Prepend it for template roots**

In `src/StudioServiceProvider.php::boot()`, directly after the template-preview `loadRoutesFrom` block (after line 70), add:

```php
        // A template edited in place addresses its public files from the
        // web root; answer those requests from its folder while it is active
        if (! $this->app->runningInConsole() && ! $this->app->make(\Designer\Studio\Support\ActiveSite::class)->current()->isHost()) {
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->prependMiddleware(\Designer\Studio\Http\Middleware\ServeSiteFiles::class);
        }
```

- [ ] **Step 5: `SiteUrls` for a template root**

Replace `pageUrl()` and `ownsRoot()` in `src/Support/SiteUrls.php`:

```php
    public static function pageUrl(string $slug): string
    {
        $root = SitePaths::root();

        // A template edited in place is browsable through the template
        // preview, which renders it straight from its working tree
        if (! $root->isHost()) {
            $home = $slug === self::homeSlug();

            return route('studio.template-preview.show', $home ? ['slug' => $root->slug] : ['slug' => $root->slug, 'path' => $slug]);
        }

        if ($slug === self::homeSlug() && self::ownsRoot()) {
            return url('/');
        }

        return url('/' . $slug);
    }

    /** Whether '/' reaches the site (no app route of its own claims it). A host-site question. */
    public static function ownsRoot(): bool
    {
        return ! SitePaths::root()->isHost() || ! self::appDefinesRootRoute();
    }
```

- [ ] **Step 6: Run the probe**

Run: `zsh <scratchpad>/probe-files.sh`
Expected: `probe-files OK`. Then, with `active.json` set to artisan, `curl -s http://designer.test/studio | grep -o 'designer.test/template/artisan[^"]*' | head -2` shows the live-site links pointing at the template preview. Remove the file afterwards.

- [ ] **Step 7: Commit**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add src/Http/Middleware/ServeSiteFiles.php src/StudioServiceProvider.php src/Support/SiteUrls.php
git commit -m "Serve a template root's public files; link its pages to the template preview"
```

---

### Task 5: Switching — route, viewer button, editor notice and guards

**Files:**
- Create: `src/Http/Controllers/SiteSwitchController.php`
- Modify: `routes/web.php:89-93` (inside the `api` group)
- Modify: `resources/views/template-viewer.blade.php:39-42` (css), `:131-134` (button)
- Modify: `src/Http/Controllers/StudioController.php:41-52`, `:108-158`
- Modify: `resources/views/home.blade.php:1-9`, `:394-400`, `:736-757`
- Modify: `resources/views/iframe.blade.php:1046` (the `window.__studioPreview = {` literal)
- Modify: `resources/js/studio.js:1546`

**Interfaces:**
- Consumes: `ActiveSite::available()/switchTo()/current()`, `SiteRoot::label/slug/templateDir()`.
- Produces: route `studio.api.site.switch` (`POST /studio/api/site/switch`, field `template`, empty = host). Notice shape gains optional `action: {label, url, template}`. `window.__studioPreview.componentsPath`.

- [ ] **Step 1: Probe**

Save as `<scratchpad>/probe-switch.sh`:

```bash
#!/bin/zsh
set -e
STATE=/Users/tonylea/Sites/designer/storage/studio/active.json
rm -f "$STATE"
# viewer shows the small Edit form posting to the switch route
curl -s http://designer.test/templates/artisan | grep -q 'action="http://designer.test/studio/api/site/switch"' || { echo "FAIL: no Edit form in viewer"; exit 1; }
curl -s http://designer.test/templates/artisan | grep -q 'name="template" value="artisan"' || { echo "FAIL: form lacks slug"; exit 1; }
# switch (CSRF: fetch a token from the viewer page via the session cookie)
JAR=$(mktemp)
TOKEN=$(curl -s -c "$JAR" http://designer.test/templates/artisan | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/')
curl -s -b "$JAR" -o /dev/null -w '%{http_code} %{redirect_url}\n' -X POST -d "_token=$TOKEN&template=artisan" http://designer.test/studio/api/site/switch | grep -q '302 http://designer.test/studio' || { echo "FAIL: switch did not redirect"; exit 1; }
[ -f "$STATE" ] || { echo "FAIL: state not written"; exit 1; }
# editor shows the notice with the way back
EDITOR=$(curl -s -b "$JAR" http://designer.test/studio)
echo "$EDITOR" | grep -q 'Editing template Artisan' || { echo "FAIL: notice missing"; exit 1; }
echo "$EDITOR" | grep -q 'Back to site' || { echo "FAIL: back action missing"; exit 1; }
echo "$EDITOR" | grep -q 'templates/artisan/files/resources' || { echo "FAIL: site-files text still says resources/designer"; exit 1; }
echo "$EDITOR" | grep -q 'resources/designer/views/pages' && { echo "FAIL: host page path leaked"; exit 1; }
curl -s -b "$JAR" http://designer.test/studio/page/home/iframe | grep -q 'componentsPath' || { echo "FAIL: iframe lacks componentsPath"; exit 1; }
# switch back
curl -s -b "$JAR" -o /dev/null -X POST -d "_token=$TOKEN&template=" http://designer.test/studio/api/site/switch
[ -f "$STATE" ] && { echo "FAIL: state not cleared"; exit 1; }
# unknown slug is refused
curl -s -b "$JAR" -o /dev/null -w '%{http_code}\n' -X POST -d "_token=$TOKEN&template=nope" http://designer.test/studio/api/site/switch | grep -q 422 || { echo "FAIL: unknown slug accepted"; exit 1; }
rm -f "$JAR"
echo "probe-switch OK"
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `zsh <scratchpad>/probe-switch.sh`
Expected: `FAIL: no Edit form in viewer`.

- [ ] **Step 3: The controller and route**

Create `src/Http/Controllers/SiteSwitchController.php`:

```php
<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Support\ActiveSite;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Point Studio at a template in the preview folder (or back at the host
 * site with an empty `template`). Dev mode + template preview only; 404
 * otherwise, like every other dev-only surface.
 */
class SiteSwitchController extends Controller
{
    public function __invoke(Request $request, ActiveSite $site)
    {
        abort_unless($site->available(), 404);

        $slug = trim((string) $request->input('template', ''));

        try {
            $site->switchTo($slug === '' ? null : $slug);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return redirect()->route('studio.index');
    }
}
```

In `routes/web.php`, inside the `api` group before the `// Draft publishing` comment (line 89), add:

```php
        // Dev mode — which site root Studio edits (404s unless the gate passes)
        Route::post('/site/switch', \Designer\Studio\Http\Controllers\SiteSwitchController::class)->middleware('throttle:30,1')->name('api.site.switch');
```

- [ ] **Step 4: The small Edit button in the viewer**

In `resources/views/template-viewer.blade.php` add after line 42 (`.btn.outline {…}`):

```css
        .btn.small { height: 26px; padding: 0 8px; font-size: 12px; }
        form.inline { display: contents; }
```

Then, directly after the `Open` anchor (after line 134, `</a>`), add:

```blade
            @if (\Designer\Studio\Support\DevMode::enabled())
                <form method="post" action="{{ route('studio.api.site.switch') }}" class="inline">
                    @csrf
                    <input type="hidden" name="template" value="{{ $template['slug'] }}">
                    <button type="submit" class="btn small outline" title="Open this template in Studio">Edit</button>
                </form>
            @endif
```

- [ ] **Step 5: Editor-load guards and the notice**

In `src/Http/Controllers/StudioController.php` replace lines 41-52 (from `// The site is served by the runtime provider…` through `$homeClaimed = …;`):

```php
        $root = SitePaths::root();

        // The host site is served by the runtime provider in the app. If it
        // has gone missing (or was never registered), put it back. A
        // template edited in place is served by the template preview and
        // needs none of this.
        if ($root->isHost()) {
            $runtime = app(RuntimeInstaller::class);

            if (!$runtime->installed()) {
                $runtime->install();
            }
        }

        // Pull in anything that changed in the site's files since the last
        // visit (Code mode, the Assistant, the developer's own editor, a
        // fresh deploy with empty storage) and re-sync the section library.
        $this->mirror->sync();

        // Self-heal: sites published while the stock welcome route still
        // owned '/' get claimed here.
        $homeClaimed = $root->isHost() && $this->pruner()->claimHome();
```

In `editorNotices()`, after the `unprotected` block and before the `// The app owns '/'` comment, add:

```php
        $root = SitePaths::root();

        if (!$root->isHost()) {
            $notices[] = [
                'id' => 'editing-template',
                'tone' => 'info',
                'dismissible' => false,
                'text' => "Editing template {$root->label} — publishing writes to " . SitePaths::relative($root->templateDir()) . '.',
                'action' => ['label' => 'Back to site', 'url' => route('studio.api.site.switch'), 'template' => ''],
            ];
        }
```

and change the `$rootBlocked` expression to start with `$root->isHost() && !$homeClaimed`.

- [ ] **Step 6: Notice view: info tone and action**

In `resources/views/home.blade.php` replace the notice `<div … class="pointer-events-auto …">` (line 742) `class` expression and the icon so the three tones render, and add the action after the text span. The block from line 739 to 757 becomes:

```blade
                <div
                    x-data="{ show: {{ $notice['dismissible'] ? "localStorage.getItem('studio.notice.{$notice['id']}') !== '1'" : 'true' }} }"
                    x-show="show"
                    class="pointer-events-auto flex max-w-2xl items-center gap-2.5 rounded-xl border px-3.5 py-2.5 shadow-[0_12px_32px_-8px_rgba(0,0,0,0.5)] backdrop-blur-md {{ match($notice['tone']) { 'danger' => 'border-danger/40 bg-danger/15', 'info' => 'border-accent/40 bg-accent/10', default => 'border-warn/40 bg-warn/10' } }}"
                >
                    @if($notice['tone'] === 'info')
                        <svg class="h-4 w-4 shrink-0 text-accent" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd"/></svg>
                    @else
                        <svg class="h-4 w-4 shrink-0 {{ $notice['tone'] === 'danger' ? 'text-danger' : 'text-warn' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    @endif
                    <span class="text-[12.5px] leading-snug text-ink/90">{{ $notice['text'] }}</span>
                    @if(!empty($notice['action']))
                        <form method="post" action="{{ $notice['action']['url'] }}" class="contents">
                            @csrf
                            <input type="hidden" name="template" value="{{ $notice['action']['template'] }}">
                            <button type="submit" class="s-btn-ghost shrink-0 !h-6 !px-2 text-[12px]">{{ $notice['action']['label'] }}</button>
                        </form>
                    @endif
                    @if($notice['dismissible'])
                        <button
                            @click="show = false; localStorage.setItem('studio.notice.{{ $notice['id'] }}', '1')"
                            class="s-icon-btn !h-6 !w-6 shrink-0"
                            title="Dismiss"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                        </button>
                    @endif
                </div>
```

If `s-btn-ghost` does not exist in `resources/css/studio.css` (grep `\.s-btn`), use the smallest existing button class the file defines (e.g. `s-btn s-btn-sm`) — check before choosing.

- [ ] **Step 7: Path text in the editor**

In `home.blade.php` lines 1-9 replace the `$pageFile` line with:

```php
    $siteDir = \Designer\Studio\Support\SitePaths::relative(\Designer\Studio\Support\SitePaths::resources());
    // Where publishing writes this page (index.blade.php for the home page)
    $pageFile = \Designer\Studio\Support\SitePaths::relative(\Designer\Studio\Support\SitePaths::pages(($page->slug === $homeSlug ? 'index' : $page->slug) . '.blade.php'));
```

and in lines 397-398 replace the literal `resources/designer` with `{{ $siteDir }}` and `Str::after($pageFile, 'resources/designer/')` with `Str::after($pageFile, $siteDir . '/')`.

- [ ] **Step 8: Components path for ⌥-click open-in-code**

In `resources/views/iframe.blade.php`, inside the `window.__studioPreview = {` object literal (line 1046), add as the first entry:

```js
            componentsPath: @js(\Designer\Studio\Support\SitePaths::relative(\Designer\Studio\Support\SitePaths::components())),
```

In `resources/js/studio.js` line 1546 replace:

```js
                            path: 'resources/designer/views/components/' + source + '.blade.php',
```

with:

```js
                            path: ((window.__studioPreview && window.__studioPreview.componentsPath) || 'resources/designer/views/components') + '/' + source + '.blade.php',
```

Then rebuild: `cd /Users/tonylea/Sites/designer/packages/designer/studio && npm run build`. If `public/vendor/studio` exists in the host, the build re-publishes; otherwise `AssetController` serves `dist/` directly.

- [ ] **Step 9: Run the probe**

Run: `zsh <scratchpad>/probe-switch.sh`
Expected: `probe-switch OK`. Then in a browser: `/templates/artisan` → small **Edit** beside Open → Studio opens Artisan with the info notice; **Back to site** returns to the host site with its draft intact.

- [ ] **Step 10: Commit**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add src/Http/Controllers/SiteSwitchController.php routes/web.php resources/views/template-viewer.blade.php src/Http/Controllers/StudioController.php resources/views/home.blade.php resources/views/iframe.blade.php resources/js/studio.js dist
git commit -m "Switch Studio to a template from the viewer; editor notice and guards"
```

---

### Task 6: Code mode roots and command refusals

**Files:**
- Modify: `src/Services/CodeWorkspace.php:295-302` (and any other `LARAVEL_DIRS` membership check — grep `LARAVEL_DIRS` first)
- Modify: `src/Console/Commands/DevReset.php:19`, `src/Console/Commands/Uninstall.php:18`, `src/Console/Commands/TemplatesImport.php:18`

**Interfaces:**
- Consumes: `CodeWorkspace::siteRoots()`, `ActiveSite::current()/stateFile()`.
- Produces: `CodeWorkspace::allowedRoots(): array` (protected).

- [ ] **Step 1: Probe**

Save as `<scratchpad>/probe-code.php`:

```php
<?php
require '/Users/tonylea/Sites/designer/vendor/autoload.php';
$app = require '/Users/tonylea/Sites/designer/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Designer\Studio\Services\CodeWorkspace;
use Designer\Studio\Support\ActiveSite;

app(ActiveSite::class)->switchTo('artisan');
$ws = app(CodeWorkspace::class);
$tree = $ws->tree('designer');
$paths = array_column($tree, 'path');
assert(in_array('templates/artisan/files/resources', $paths, true), 'tree roots at the template: ' . implode(',', array_slice($paths, 0, 4)));
assert(!in_array('resources/designer', $paths, true), 'host site absent from the designer view');
$file = $ws->resolveExisting('templates/artisan/files/resources/views/pages/index.blade.php');
assert(is_file($file), 'template file resolves');
try { $ws->resolveExisting('storage/studio/active.json'); assert(false, 'storage must stay unreachable'); } catch (RuntimeException $e) {}
app(ActiveSite::class)->switchTo(null);
echo "probe-code OK\n";
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php -d zend.assertions=1 -d assert.exception=1 <scratchpad>/probe-code.php`
Expected: `RuntimeException: That file is outside the Studio workspace.` from `resolveExisting`.

- [ ] **Step 3: Allow the site's top-level folders**

In `src/Services/CodeWorkspace.php`, add after `siteRoots()`:

```php
    /**
     * Top-level folders a workspace path may start with: the app's own,
     * plus wherever the site being edited lives (`templates/…` for a
     * template edited in place). `storage/` is never among them.
     */
    protected function allowedRoots(): array
    {
        $roots = self::LARAVEL_DIRS;

        foreach ($this->siteRoots() as $root) {
            $roots[] = explode('/', $root)[0];
        }

        return array_values(array_unique($roots));
    }
```

Replace every `in_array($root, self::LARAVEL_DIRS, true)` membership check (line 300 and any other hit from the grep) with `in_array($root, $this->allowedRoots(), true)`. Leave `tree('laravel')`'s `foreach (self::LARAVEL_DIRS …)` alone — the Laravel view lists the app's folders only.

- [ ] **Step 4: Refuse site-mutating commands on a template root**

Add this as the first statement of `handle()` in each of `DevReset.php`, `Uninstall.php`, `TemplatesImport.php`:

```php
        if (!($root = SitePaths::root())->isHost()) {
            $site = app(\Designer\Studio\Support\ActiveSite::class);
            $this->error("Studio is switched to template [{$root->slug}] — this command only works on the host site.");
            $this->line('  Switch back with "Back to site" in the editor, or delete ' . SitePaths::relative($site->stateFile()) . '.');

            return self::FAILURE;
        }
```

`TemplatesImport.php` and `Uninstall.php` already import `SitePaths`; check `DevReset.php` does too (it does, line 7).

- [ ] **Step 5: Run the probes**

Run: `php -d zend.assertions=1 -d assert.exception=1 <scratchpad>/probe-code.php` → `probe-code OK`.
Then: `cd /Users/tonylea/Sites/designer && echo '{"template":"artisan"}' > storage/studio/active.json && php artisan studio:uninstall --force; echo "exit $?"; rm storage/studio/active.json` → prints the refusal and `exit 1`, and `storage/studio/pages` still exists.

- [ ] **Step 6: Commit**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add src/Services/CodeWorkspace.php src/Console/Commands/DevReset.php src/Console/Commands/Uninstall.php src/Console/Commands/TemplatesImport.php
git commit -m "Code mode follows the site root; host-only commands refuse a template root"
```

---

### Task 7: Round-trip verification and docs

**Files:**
- Modify: `CLAUDE.md` (Architecture → "The installed site" bullet, Routes list, Gotchas)

- [ ] **Step 1: Publish round-trip on the template**

```bash
cd /Users/tonylea/Sites/designer
echo '{"template":"artisan"}' > storage/studio/active.json
rm -rf storage/studio/sites/artisan
(cd templates/artisan && git status --short)          # must be empty before
curl -s -o /dev/null http://designer.test/studio       # seeds mirror + draft
php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $r = app(Designer\Studio\Services\PublishService::class)->publishAll(); echo json_encode($r["notes"] ?? []), "\n";'
(cd templates/artisan && git status --short)          # must STILL be empty: publishing an unedited draft changes nothing
```

Expected: both `git status` outputs empty; notes `[]`.

- [ ] **Step 2: Edit, publish, verify in the preview**

In the browser at `http://designer.test/studio`: change the hero heading, Publish. Then:

```bash
(cd /Users/tonylea/Sites/designer/templates/artisan && git status --short && git diff --stat)
curl -s http://designer.test/template/artisan | grep -c "<the new heading text>"
```

Expected: exactly one page file modified; the preview shows the new heading. Revert with `(cd templates/artisan && git checkout -- .)`, then rebuild the mirror: `curl -s -o /dev/null http://designer.test/studio`.

- [ ] **Step 3: Media and Code mode by hand**

In the editor: Media → upload an image → its URL starts with `/images/` or `/uploads/` (no `/designer`, no `/template/`), and the file appears under `templates/artisan/files/public/`. Delete it from the Media panel afterwards. Code mode → Files: the Designer view lists `templates/artisan/files/resources` and `…/public`; save a section file → the canvas refreshes.

- [ ] **Step 4: Gates and baseline**

```bash
cd /Users/tonylea/Sites/designer
# dev mode off: the state file is ignored and the switch route 404s
STUDIO_DEV_MODE=false php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); var_dump(app(Designer\Studio\Support\ActiveSite::class)->current()->isHost());'   # bool(true)
rm -f storage/studio/active.json
php artisan studio:inline:verify
```

Expected: `bool(true)`; inline:verify reports its baseline (`Coverage: 365/369 …`, `Inertness: … 0 diverged …`) on the host root.

- [ ] **Step 5: Document**

In `CLAUDE.md`:

1. In the **Architecture → Package Structure** "The installed site" bullet, after `Support/SitePaths` is the single source for its locations, add: `— resolved through `Support/ActiveSite` → `Support/SiteRoot`: the host site, or (dev mode + template preview) the template named in `storage/studio/active.json`, edited in place from `templates/<slug>/files/{resources,public}` with its Studio documents and a seeded `designer.json` under `storage/studio/sites/<slug>/`. `/` keeps serving the host site; a template's public files are served at the root by `Http/Middleware/ServeSiteFiles`, its pages link to `/template/<slug>`, and `NestedBlade` renders it with only its own components. Switch from the small Edit button on `/templates/<slug>`; "Back to site" in the editor notice returns.`
2. In **Routes**, add: `- `POST /studio/api/site/switch` — point Studio at a template in the preview folder (`template` slug, empty = host site; dev mode + template preview only)`.
3. In **Gotchas**, add: `- **`SitePaths` follows the active site root.** `studio:dev-reset`, `studio:uninstall` and `studio:templates:import` refuse to run while a template is active, because they would delete or overwrite the template's working tree. The provider's boot-time `anonymousComponentPath` is deliberately the host's — registering the template's globally would make the live site pick up its components for shared names.`

- [ ] **Step 6: Commit**

```bash
cd /Users/tonylea/Sites/designer/packages/designer/studio
git add CLAUDE.md docs/superpowers
git commit -m "Document the switchable site root"
```
