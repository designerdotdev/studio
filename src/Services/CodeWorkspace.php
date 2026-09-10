<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\SiteRepository;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The file set Code mode can browse and edit.
 *
 * Two views over one allowlist. The Design view shows only the surfaces
 * Studio itself renders:
 *
 *   designer/    the section sources (<name>.html + <name>.yml)
 *   components/  support components copied in by a template import
 *   site/        virtual files backed by the site document (theme CSS,
 *                head HTML, scripts)
 *
 * The Laravel view shows the host application's own directories instead
 * (self::LARAVEL_DIRS), with the design folders marked so they stay findable.
 * Both views resolve through the same roots, so switching view changes what
 * is listed, never what is reachable.
 *
 * Whatever the view, every path from the browser is resolved against a root
 * with realpath() and rejected if it escapes one; `..`, hidden files and
 * unlisted extensions never resolve, which is what keeps `.env` and the rest
 * of the project out. Paths that land inside a design root are rewritten to
 * their canonical `designer/` or `components/` form (see canonical()) so one
 * file cannot have two identities.
 */
class CodeWorkspace
{
    /** Extensions the editor will open. Anything else is invisible. */
    public const EXTENSIONS = ['html', 'yml', 'yaml', 'css', 'js', 'php', 'json', 'md', 'txt'];

    /**
     * The host application's own directories, offered by the Laravel tree
     * view. Deliberately an allowlist rather than "the project root minus a
     * few things": `storage/` holds Studio's own JSON documents (hand-editing
     * them desyncs the editor) and nothing here is a dotfile, so `.env` is
     * unreachable — it is never listed, and never resolves.
     */
    public const LARAVEL_DIRS = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'tests'];

    /** Never descended into, wherever they appear. */
    public const SKIP_DIRS = ['vendor', 'node_modules', '.git'];

    /** A runaway tree would hang the browser, so the walk is bounded. */
    public const MAX_NODES = 4000;

    /** Refuse to load anything a browser-side editor has no business holding. */
    public const MAX_BYTES = 512 * 1024;

    /** The virtual site-document files, in the order they should be listed. */
    public const SITE_FILES = [
        'theme.css' => ['key' => 'theme_css', 'language' => 'css'],
        'head.html' => ['key' => 'head_html', 'language' => 'html'],
        'scripts.txt' => ['key' => 'scripts', 'language' => 'plaintext'],
    ];

    public function __construct(
        protected DesignSyncService $designSync,
        protected SiteRepository $site,
    ) {}

    /**
     * The on-disk roots, keyed by the prefix used in workspace paths.
     * `write` marks where edits land — designer/ reads may come from the
     * package copy, but writes always go to the app's copy.
     */
    public function roots(): array
    {
        $roots = [
            'designer' => [
                'label' => 'Sections',
                'read' => $this->designSync->getDesignsPath(),
                'write' => resource_path('views/designer'),
            ],
            'components' => [
                'label' => 'Components',
                'read' => $this->componentsPath(),
                'write' => $this->componentsPath(),
            ],
        ];

        // The host app's own directories. They overlap the design roots
        // (resources/views/designer lives under `resources`), which is why
        // every path is put through canonical() before it is used — one file
        // must not have two identities, or a section edit through the Laravel
        // view would skip its YAML check and library re-sync.
        foreach (self::LARAVEL_DIRS as $dir) {
            $roots[$dir] = [
                'label' => $dir,
                'read' => base_path($dir),
                'write' => base_path($dir),
            ];
        }

        return $roots;
    }

    protected function componentsPath(): string
    {
        return config('studio.templates.components_path', resource_path('views/components/studio-templates'));
    }

    /**
     * The design roots are the authoritative identity for the files they
     * hold, so a Laravel-view path that lands inside one is rewritten to its
     * `designer/` or `components/` form.
     */
    public function canonical(string $path): string
    {
        $path = ltrim(trim($path), '/');

        foreach ($this->designAliases() as $prefix => $canonical) {
            // The directory itself maps too, so the folder is tinted, not
            // just what is inside it.
            if ($path === rtrim($prefix, '/')) {
                return $canonical;
            }

            if (str_starts_with($path, $prefix)) {
                return $canonical . '/' . substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    /**
     * Laravel-view prefix => design-root prefix, as workspace paths. Built
     * from the real directories so a relocated components_path still maps.
     */
    protected function designAliases(): array
    {
        $aliases = ['resources/views/designer/' => 'designer'];

        $components = $this->relativeToBase($this->componentsPath());

        if ($components !== null) {
            $aliases[$components . '/'] = 'components';
        }

        return $aliases;
    }

    /** Does this resolved absolute path sit inside the application? */
    protected function insideBase(string $real): bool
    {
        $base = realpath(base_path()) ?: base_path();

        return $real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR);
    }

    /** A workspace-style relative path for an absolute one inside the app. */
    protected function relativeToBase(string $absolute): ?string
    {
        $real = realpath($absolute) ?: $absolute;
        $base = realpath(base_path()) ?: base_path();

        return str_starts_with($real, $base . '/')
            ? trim(substr($real, strlen($base)), '/')
            : null;
    }

    /**
     * A flat node list — {path, name, type, depth, parent, design} — ordered
     * so the browser can render it as a tree without recursing. Directories
     * come before files at every level, both alphabetical.
     *
     * `design` marks the folders and files Studio itself renders: in the
     * Laravel view they are the two needles in the haystack, so the tree
     * tints them.
     *
     * @param  string  $view  'design' (the Studio surface) or 'laravel'
     */
    public function tree(string $view = 'design'): array
    {
        $nodes = [];
        $roots = $this->roots();

        $prefixes = $view === 'laravel'
            ? self::LARAVEL_DIRS
            : ['designer', 'components'];

        foreach ($prefixes as $prefix) {
            $root = $roots[$prefix] ?? null;

            if (! $root || ! is_dir($root['read'])) {
                continue;
            }

            $nodes[] = [
                'path' => $prefix,
                'name' => $root['label'],
                'type' => 'dir',
                'depth' => 0,
                'parent' => null,
                'design' => $this->isDesignPath($prefix),
            ];

            $this->walk($root['read'], $prefix, 1, $nodes);
        }

        // The site document, presented as files because that is how they read
        $nodes[] = ['path' => 'site', 'name' => 'Site', 'type' => 'dir', 'depth' => 0, 'parent' => null, 'design' => true];

        foreach (array_keys(self::SITE_FILES) as $name) {
            $nodes[] = ['path' => 'site/' . $name, 'canonical' => 'site/' . $name, 'name' => $name, 'type' => 'file', 'depth' => 1, 'parent' => 'site', 'design' => true];
        }

        return $nodes;
    }

    /** Is this path one of the surfaces Studio renders from? */
    public function isDesignPath(string $path): bool
    {
        if ($path === 'site' || str_starts_with($path, 'site/')) {
            return true;
        }

        $canonical = $this->canonical($path);

        foreach (['designer', 'components'] as $prefix) {
            if ($canonical === $prefix || str_starts_with($canonical, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function walk(string $absolute, string $prefix, int $depth, array &$nodes): void
    {
        if (count($nodes) >= self::MAX_NODES) {
            return;
        }

        try {
            $entries = File::directories($absolute);
        } catch (\Throwable $e) {
            // An unreadable or vanished directory is not worth failing a tree over
            return;
        }

        sort($entries);

        foreach ($entries as $dir) {
            $name = basename($dir);

            // Dependency trees and anything hidden stay out — the second rule
            // is what keeps .env and friends from ever being listed.
            if (in_array($name, self::SKIP_DIRS, true) || str_starts_with($name, '.')) {
                continue;
            }

            // A dangling symlink (public/katana) looks like a directory to
            // File::directories() but explodes on descent; one pointing out of
            // the project would take the tree somewhere it may not go.
            $real = realpath($dir);

            if ($real === false || ! is_dir($real) || ! $this->insideBase($real)) {
                continue;
            }

            $path = $prefix . '/' . $name;
            $nodes[] = [
                'path' => $path,
                'name' => $name,
                'type' => 'dir',
                'depth' => $depth,
                'parent' => $prefix,
                'design' => $this->isDesignPath($path),
            ];
            $this->walk($dir, $path, $depth + 1, $nodes);
        }

        try {
            $found = File::files($absolute);
        } catch (\Throwable $e) {
            return;
        }

        $files = array_filter(
            $found,
            fn ($file) => in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)
                && ! str_starts_with($file->getFilename(), '.')
        );

        usort($files, fn ($a, $b) => strcmp($a->getFilename(), $b->getFilename()));

        foreach ($files as $file) {
            $path = $prefix . '/' . $file->getFilename();
            $nodes[] = [
                'path' => $path,
                // A design file reached through the Laravel view is the same
                // file as the one under designer/ — the editor keys tabs and
                // dirty state on this, so both views point at one buffer.
                'canonical' => $this->canonical($path),
                'name' => $file->getFilename(),
                'type' => 'file',
                'depth' => $depth,
                'parent' => $prefix,
                'design' => $this->isDesignPath($path),
            ];
        }
    }

    /** Read one workspace file. */
    public function read(string $path): array
    {
        $path = $this->canonical($path);

        if ($this->isSitePath($path)) {
            return [
                'path' => $path,
                'language' => self::SITE_FILES[basename($path)]['language'],
                'contents' => $this->readSiteFile(basename($path)),
                'display' => 'site document · ' . basename($path),
            ];
        }

        $absolute = $this->resolveExisting($path);

        if (filesize($absolute) > self::MAX_BYTES) {
            throw new RuntimeException('That file is too large to open in the editor.');
        }

        return [
            'path' => $path,
            'language' => $this->languageFor($path),
            'contents' => (string) file_get_contents($absolute),
            'display' => $this->displayPath($absolute),
        ];
    }

    /**
     * Write one workspace file. Section sources are validated (the YAML must
     * parse and keep its `name`) and copy-on-written into the app's designer
     * directory; the caller re-syncs the library afterwards.
     */
    public function write(string $path, string $contents): array
    {
        $path = $this->canonical($path);

        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('That file is too large to save.');
        }

        if ($this->isSitePath($path)) {
            $this->writeSiteFile(basename($path), $contents);

            return ['path' => $path, 'display' => 'site document · ' . basename($path)];
        }

        // Must already exist — new files go through create()
        $this->resolveExisting($path);

        if ($this->isSectionYaml($path)) {
            $this->assertValidSectionYaml($path, $contents);
        }

        $target = $this->writeTarget($path);
        File::ensureDirectoryExists(dirname($target));
        File::put($target, $contents);

        return ['path' => $path, 'display' => $this->displayPath($target)];
    }

    /**
     * Scaffold a new section: `<category>/<name>.html` plus a matching
     * `.yml`, both under designer/. Returns the workspace path of the HTML
     * file so the caller can open it.
     */
    public function createSection(string $category, string $name, string $label): array
    {
        $category = $this->safeSegment($category);
        $name = $this->safeSegment($name);
        $label = trim($label) !== '' ? trim($label) : ucfirst(str_replace('-', ' ', $name));

        if ($category === '' || $name === '') {
            throw new RuntimeException('A section needs a category and a name (lowercase letters, numbers and dashes).');
        }

        if ($this->designSync->findDesignPath($name)) {
            throw new RuntimeException("A section named \"{$name}\" already exists.");
        }

        $this->ensureAppDesigns();

        $dir = resource_path('views/designer/' . $category);
        File::ensureDirectoryExists($dir);

        File::put($dir . '/' . $name . '.yml', $this->sectionYamlStub($name, $label, $category));
        File::put($dir . '/' . $name . '.html', $this->sectionHtmlStub($label));

        return [
            'path' => 'designer/' . $category . '/' . $name . '.html',
            'yaml' => 'designer/' . $category . '/' . $name . '.yml',
            'name' => $name,
        ];
    }

    /** Delete a workspace file. Section sources take their sibling with them. */
    public function delete(string $path): array
    {
        $path = $this->canonical($path);

        if ($this->isSitePath($path)) {
            throw new RuntimeException('Site document files cannot be deleted — clear their contents instead.');
        }

        $absolute = $this->resolveExisting($path);
        $removed = [$path];

        // A section is a pair; leaving half of it behind breaks the sync
        if ($this->isSectionSource($path)) {
            foreach (['html', 'yml'] as $extension) {
                $sibling = preg_replace('/\.(html|yml)$/', '.' . $extension, $absolute);
                if (is_file($sibling)) {
                    File::delete($sibling);
                }
            }

            $removed = [
                preg_replace('/\.(html|yml)$/', '.html', $path),
                preg_replace('/\.(html|yml)$/', '.yml', $path),
            ];
        } else {
            File::delete($absolute);
        }

        return ['removed' => $removed];
    }

    /* ------------------------------------------------------------------ */
    /*  Path safety                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a workspace path into an absolute path inside one of the roots,
     * or throw. This is the only place browser input becomes a file path.
     */
    public function resolveExisting(string $path): string
    {
        [$prefix, $relative] = $this->split($path);

        $root = $this->roots()[$prefix] ?? null;

        if (! $root) {
            throw new RuntimeException('That file is outside the Studio workspace.');
        }

        // Prefer the app's copy where one exists (it is what actually renders)
        foreach ([$root['write'], $root['read']] as $base) {
            $candidate = realpath(rtrim($base, '/') . '/' . $relative);

            if ($candidate === false || ! is_file($candidate)) {
                continue;
            }

            $realBase = realpath($base);

            if ($realBase !== false && str_starts_with($candidate, $realBase . DIRECTORY_SEPARATOR)) {
                return $candidate;
            }
        }

        throw new RuntimeException('That file could not be found in the Studio workspace.');
    }

    /** Where an edit to `$path` should land (always the app's own copy). */
    protected function writeTarget(string $path): string
    {
        [$prefix, $relative] = $this->split($path);

        if ($prefix === 'designer') {
            $this->ensureAppDesigns();
        }

        return rtrim($this->roots()[$prefix]['write'], '/') . '/' . $relative;
    }

    /**
     * Split a workspace path into its root prefix and the remainder, with
     * every traversal trick rejected up front.
     */
    protected function split(string $path): array
    {
        $path = trim($path);

        if ($path === '' || ! preg_match('#^[A-Za-z0-9._/-]+$#', $path)) {
            throw new RuntimeException('That is not a valid file path.');
        }

        $segments = explode('/', trim($path, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('That is not a valid file path.');
            }
        }

        $prefix = array_shift($segments);
        $relative = implode('/', $segments);

        if ($relative === '') {
            throw new RuntimeException('That is a folder, not a file.');
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new RuntimeException('Studio only edits ' . implode(', ', self::EXTENSIONS) . ' files.');
        }

        return [$prefix, $relative];
    }

    protected function safeSegment(string $value): string
    {
        return preg_replace('/[^a-z0-9-]/', '', strtolower(trim($value))) ?? '';
    }

    /* ------------------------------------------------------------------ */
    /*  Sections                                                           */
    /* ------------------------------------------------------------------ */

    public function isSectionSource(string $path): bool
    {
        $path = $this->canonical($path);

        return str_starts_with($path, 'designer/') && preg_match('/\.(html|yml)$/', $path) === 1;
    }

    protected function isSectionYaml(string $path): bool
    {
        return str_starts_with($path, 'designer/') && str_ends_with($path, '.yml');
    }

    /** The library name a section source belongs to (its filename stem). */
    public function sectionName(string $path): string
    {
        return preg_replace('/\.(html|yml)$/', '', basename($path));
    }

    protected function assertValidSectionYaml(string $path, string $contents): void
    {
        try {
            $parsed = Yaml::parse($contents);
        } catch (ParseException $e) {
            throw new RuntimeException('Invalid YAML: ' . $e->getMessage());
        }

        $name = $this->sectionName($path);

        if (! is_array($parsed) || ($parsed['name'] ?? null) !== $name) {
            throw new RuntimeException("The YAML `name` key must stay \"{$name}\" — it has to match the filename.");
        }
    }

    /**
     * An app copy of a single section would hide every packaged one
     * (DesignSyncService reads app OR package, never both), so the whole
     * set is published before the first write.
     */
    protected function ensureAppDesigns(): void
    {
        $appDir = resource_path('views/designer');

        if (File::isDirectory($appDir)) {
            return;
        }

        $packageDir = dirname(__DIR__, 2) . '/resources/views/designer';
        File::ensureDirectoryExists($appDir);

        if (File::isDirectory($packageDir)) {
            File::copyDirectory($packageDir, $appDir);
        }
    }

    protected function sectionYamlStub(string $name, string $label, string $category): string
    {
        return <<<YAML
        name: {$name}
        title: {$label}
        category: {$category}
        fields:
          heading:
            type: text
            label: Heading
            default: {$label}
          body:
            type: textarea
            label: Body
            default: Describe this section.

        YAML;
    }

    protected function sectionHtmlStub(string $label): string
    {
        return <<<HTML
        <!-- {$label} -->
        <section class="px-6 py-24">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-4xl font-semibold tracking-tight">{{ \$heading }}</h2>
                <p class="mt-4 text-lg text-gray-600">{{ \$body }}</p>
            </div>
        </section>

        HTML;
    }

    /* ------------------------------------------------------------------ */
    /*  The virtual site/ files                                            */
    /* ------------------------------------------------------------------ */

    protected function isSitePath(string $path): bool
    {
        return str_starts_with($path, 'site/')
            && isset(self::SITE_FILES[basename($path)])
            && basename($path) === substr($path, 5);
    }

    protected function readSiteFile(string $name): string
    {
        return match (self::SITE_FILES[$name]['key']) {
            'theme_css' => $this->site->themeCss(),
            'head_html' => $this->site->headHtml(),
            // One URL per line reads better than JSON for a hand-edited list
            'scripts' => implode("\n", $this->site->scripts()),
        };
    }

    protected function writeSiteFile(string $name, string $contents): void
    {
        $key = self::SITE_FILES[$name]['key'];

        $value = $key === 'scripts'
            ? array_values(array_filter(array_map('trim', preg_split('/\R/', $contents) ?: [])))
            : $contents;

        $this->site->save(array_merge($this->site->get(), [$key => $value]));
    }

    /* ------------------------------------------------------------------ */

    protected function languageFor(string $path): string
    {
        if (str_ends_with($path, '.blade.php')) {
            return 'html';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'css',
            'js' => 'javascript',
            'yml', 'yaml' => 'yaml',
            'json' => 'json',
            'md' => 'markdown',
            'php' => 'php',
            'txt' => 'plaintext',
            default => 'html',
        };
    }

    public function displayPath(string $absolute): string
    {
        return str_starts_with($absolute, base_path())
            ? ltrim(substr($absolute, strlen(base_path())), '/')
            : $absolute;
    }
}
