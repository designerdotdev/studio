<?php

namespace Designer\Studio\Services;

use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\TemplateLink;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The file set Code mode can browse and edit.
 *
 * Two views over one set of real paths (relative to the app root):
 *
 *   designer   only the site — `resources/designer` and `public/designer`,
 *              shown as `resources/` and `public/` holding just `designer/`,
 *              walked whole in one request
 *   laravel    the whole application root, hidden files included, one
 *              folder per request (a project walked whole blows any node
 *              cap), with the site's two folders marked so they stay findable
 *
 * The views overlap (resources/designer is inside resources/), but a file
 * has one path in both, so a tab, an unsaved buffer, or a save is the same
 * whichever view it was opened from.
 *
 * Every file is listed, but not every file opens: dependency trees
 * (self::INERT_DIRS) are shown and never entered, and binary or oversized
 * files are shown and never read. Every path from the browser is resolved
 * with realpath() and rejected if it escapes the application root.
 */
class CodeWorkspace
{
    /** Listed so the project reads true, but never descended into or opened. */
    public const INERT_DIRS = ['vendor', 'node_modules', '.git'];

    /** Never opened — a text editor would only mangle them. */
    public const BINARY_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp', 'tiff', 'psd',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp3', 'mp4', 'mov', 'webm', 'wav', 'ogg', 'm4a',
        'zip', 'gz', 'tgz', 'tar', 'rar', '7z', 'phar', 'pdf',
        'sqlite', 'db', 'so', 'dylib', 'exe', 'bin', 'ds_store',
    ];

    /** What a browser path may look like; a file named otherwise is listed but not opened. */
    protected const PATH_PATTERN = '#^[A-Za-z0-9._/@+-]+$#';

    /** A runaway tree would hang the browser, so the walk is bounded. */
    public const MAX_NODES = 4000;

    /** Refuse to load anything a browser-side editor has no business holding. */
    public const MAX_BYTES = 512 * 1024;

    /** The site's two folders, as workspace paths. */
    public function siteRoots(): array
    {
        return [
            SitePaths::relative(SitePaths::resources()),
            SitePaths::relative(SitePaths::public()),
        ];
    }

    /**
     * A flat node list — {path, name, type, depth, parent, design, lazy,
     * inert, note} — ordered so the browser can render it as a tree without
     * recursing. Directories come before files at every level, both
     * alphabetical. An `inert` node is listed but never opened; `note` says why.
     *
     * The Designer view comes back whole. The Laravel view comes back one
     * folder at a time — the root, or the children of `$dir` — and a folder
     * whose children are still to fetch is `lazy`.
     *
     * @param  string  $view  'designer' (the site) or 'laravel' (the app)
     */
    public function tree(string $view = 'designer', ?string $dir = null): array
    {
        $nodes = [];

        if ($view === 'laravel') {
            $dir = trim((string) $dir, '/') === '' ? null : $this->normaliseDirectory($dir);

            $this->walk(
                $dir === null ? base_path() : base_path($dir),
                $dir,
                $dir === null ? 0 : substr_count($dir, '/') + 1,
                $nodes,
                recursive: false,
            );

            return $nodes;
        }

        foreach ($this->siteRoots() as $root) {
            [$parent, $folder] = explode('/', $root, 2);

            if (!is_dir(base_path($root))) {
                continue;
            }

            $nodes[] = $this->node($parent, $parent, 'dir', 0, null);
            $nodes[] = $this->node($root, $folder, 'dir', 1, $parent);
            $this->walk(base_path($root), $root, 2, $nodes);
        }

        return $nodes;
    }

    /** Is this path part of the site (resources/designer or public/designer)? */
    public function isDesignPath(string $path): bool
    {
        foreach ($this->siteRoots() as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    protected function node(string $path, string $name, string $type, int $depth, ?string $parent, ?string $inert = null): array
    {
        return [
            'path' => $path,
            'name' => $name,
            'type' => $type,
            'depth' => $depth,
            'parent' => $parent,
            'design' => $this->isDesignPath($path),
            'lazy' => false,
            'inert' => $inert !== null,
            'note' => $inert,
        ];
    }

    /**
     * List one directory into $nodes — every entry, hidden ones included —
     * and, when $recursive, everything under it. A null $prefix is the
     * application root.
     */
    protected function walk(string $absolute, ?string $prefix, int $depth, array &$nodes, bool $recursive = true): void
    {
        if (count($nodes) >= self::MAX_NODES) {
            return;
        }

        // scandir, not File::directories()/files(): Finder skips dotfiles
        // and VCS folders, and this tree shows them
        $entries = @scandir($absolute);

        if ($entries === false) {
            // An unreadable or vanished directory is not worth failing a tree over
            return;
        }

        $dirs = [];
        $files = [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $full = $absolute . DIRECTORY_SEPARATOR . $name;

            if (is_dir($full)) {
                $dirs[] = $name;
            } elseif (is_file($full)) {
                $files[] = $name;
            }
        }

        sort($dirs, SORT_STRING | SORT_FLAG_CASE);
        sort($files, SORT_STRING | SORT_FLAG_CASE);

        foreach ($dirs as $name) {
            if (count($nodes) >= self::MAX_NODES) {
                return;
            }

            $path = $prefix === null ? $name : $prefix . '/' . $name;

            // A symlink pointing out of the project would take the tree
            // somewhere it may not go
            $real = realpath($absolute . DIRECTORY_SEPARATOR . $name);

            if ($real === false || !$this->insideBase($real)) {
                continue;
            }

            if (in_array($name, self::INERT_DIRS, true)) {
                $nodes[] = $this->node($path, $name, 'dir', $depth, $prefix, 'Dependencies — Studio does not browse vendor, node_modules or .git.');

                continue;
            }

            $node = $this->node($path, $name, 'dir', $depth, $prefix);

            if (!$recursive) {
                $node['lazy'] = true;
                $nodes[] = $node;

                continue;
            }

            $nodes[] = $node;
            $this->walk($real, $path, $depth + 1, $nodes);
        }

        foreach ($files as $name) {
            if (count($nodes) >= self::MAX_NODES) {
                return;
            }

            $path = $prefix === null ? $name : $prefix . '/' . $name;

            $nodes[] = $this->node($path, $name, 'file', $depth, $prefix, $this->refusal($path, $absolute . DIRECTORY_SEPARATOR . $name));
        }
    }

    /** Why the editor won't open this file, or null when it will. */
    protected function refusal(string $path, string $absolute): ?string
    {
        if (!preg_match(self::PATH_PATTERN, $path)) {
            return 'Studio cannot open a file with that name.';
        }

        $real = realpath($absolute);

        if ($real === false || !$this->insideBase($real)) {
            return 'That file is outside the project.';
        }

        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::BINARY_EXTENSIONS, true)) {
            return 'A binary file — the editor only opens text.';
        }

        if (filesize($real) > self::MAX_BYTES) {
            return 'That file is too large to open in the editor.';
        }

        // No extension to go on (artisan, .DS_Store, a lockfile): a NUL byte
        // near the top is the same test git uses for "binary"
        $handle = @fopen($real, 'rb');
        $head = $handle ? (string) fread($handle, 8000) : "\0";

        if ($handle) {
            fclose($handle);
        }

        return str_contains($head, "\0") ? 'A binary file — the editor only opens text.' : null;
    }

    /** Read one workspace file. */
    public function read(string $path): array
    {
        $absolute = $this->resolveExisting($path);

        return [
            'path' => $this->normalise($path),
            'language' => $this->languageFor($path),
            'contents' => (string) file_get_contents($absolute),
            'display' => $this->displayPath($absolute),
        ];
    }

    /**
     * Write one workspace file. The site's data and section contracts are
     * validated first, because a broken one takes pages down with it; the
     * caller re-syncs the editor afterwards.
     */
    public function write(string $path, string $contents): array
    {
        $path = $this->normalise($path);

        if (strlen($contents) > self::MAX_BYTES) {
            throw new RuntimeException('That file is too large to save.');
        }

        // Must already exist — new files go through createSection()
        $absolute = $this->resolveExisting($path);

        if ($this->isDesignPath($path)) {
            $this->assertValid($path, $contents);
        }

        File::put($absolute, $contents);
        app(TemplateLink::class)->touch();

        return ['path' => $path, 'display' => $this->displayPath($absolute)];
    }

    /**
     * Scaffold a new section — a component and its field contract — in
     * resources/designer/views/components/sections. Returns the workspace
     * path of the Blade file so the caller can open it.
     */
    public function createSection(string $category, string $name, string $label): array
    {
        $category = $this->safeSegment($category) ?: 'content';
        $name = $this->safeSegment($name);
        $label = trim($label) !== '' ? trim($label) : Str::headline($name);

        if ($name === '') {
            throw new RuntimeException('A section needs a name (lowercase letters, numbers and dashes).');
        }

        if (!SitePaths::installed()) {
            throw new RuntimeException('Install a site first — sections live in its components folder.');
        }

        $base = SitePaths::components('sections/' . $name);

        if (is_file($base . '.blade.php') || is_file($base . '.yml')) {
            throw new RuntimeException("A section named \"{$name}\" already exists.");
        }

        File::ensureDirectoryExists(dirname($base));
        File::put($base . '.yml', $this->sectionYamlStub($label, $category));
        File::put($base . '.blade.php', $this->sectionBladeStub($label));
        app(TemplateLink::class)->touch();

        return [
            'path' => SitePaths::relative($base . '.blade.php'),
            'yaml' => SitePaths::relative($base . '.yml'),
            'name' => $name,
        ];
    }

    /** Delete a workspace file. A section takes its field contract with it. */
    public function delete(string $path): array
    {
        $path = $this->normalise($path);
        $absolute = $this->resolveExisting($path);
        $removed = [$path];

        // A section is a pair; leaving half of it behind breaks the library
        if ($this->isSectionSource($path)) {
            $stem = preg_replace('/(\.blade\.php|\.yml)$/', '', $path);
            $removed = [];

            foreach (['.blade.php', '.yml'] as $extension) {
                if (is_file(base_path($stem . $extension))) {
                    File::delete(base_path($stem . $extension));
                    $removed[] = $stem . $extension;
                }
            }
        } else {
            File::delete($absolute);
        }

        app(TemplateLink::class)->touch();

        return ['removed' => $removed];
    }

    /* ------------------------------------------------------------------ */
    /*  Path safety                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a workspace path into an absolute path to a text file inside the
     * application, or throw. This is the only place browser input becomes a
     * file path.
     */
    public function resolveExisting(string $path): string
    {
        $path = $this->normalise($path);
        $candidate = realpath(base_path($path));

        if ($candidate === false || !is_file($candidate) || !$this->insideBase($candidate)) {
            throw new RuntimeException('That file could not be found in the Studio workspace.');
        }

        if ($reason = $this->refusal($path, $candidate)) {
            throw new RuntimeException($reason);
        }

        return $candidate;
    }

    /**
     * Validate a workspace path's shape — every traversal trick and every
     * dependency tree rejected up front — and return it normalised.
     */
    protected function normalise(string $path): string
    {
        $path = trim(trim($path), '/');

        if ($path === '' || !preg_match(self::PATH_PATTERN, $path)) {
            throw new RuntimeException('That is not a valid file path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('That is not a valid file path.');
            }

            if (in_array($segment, self::INERT_DIRS, true)) {
                throw new RuntimeException('Studio does not open files inside vendor, node_modules or .git.');
            }
        }

        return $path;
    }

    /** A folder the Laravel view may list, normalised like a file path. */
    protected function normaliseDirectory(string $path): string
    {
        $path = $this->normalise($path);
        $real = realpath(base_path($path));

        if ($real === false || !is_dir($real) || !$this->insideBase($real)) {
            throw new RuntimeException('That folder could not be found in the Studio workspace.');
        }

        return $path;
    }

    protected function insideBase(string $real): bool
    {
        $base = realpath(base_path()) ?: base_path();

        return $real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR);
    }

    protected function safeSegment(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($value))) ?? '', '-');
    }

    /* ------------------------------------------------------------------ */
    /*  Sections                                                           */
    /* ------------------------------------------------------------------ */

    /** A component with a field contract (the pair the section library reads). */
    public function isSectionSource(string $path): bool
    {
        $components = SitePaths::relative(SitePaths::components()) . '/';

        if (!str_starts_with($path, $components) || !preg_match('/(\.blade\.php|\.yml)$/', $path)) {
            return false;
        }

        $stem = preg_replace('/(\.blade\.php|\.yml)$/', '', $path);

        return is_file(base_path($stem . '.blade.php')) && is_file(base_path($stem . '.yml'));
    }

    /** A broken data file or contract would take pages down — refuse to save it. */
    protected function assertValid(string $path, string $contents): void
    {
        if (preg_match('/\.ya?ml$/', $path)) {
            try {
                Yaml::parse($contents);
            } catch (ParseException $e) {
                throw new RuntimeException('Invalid YAML: ' . $e->getMessage());
            }
        }

        if (str_ends_with($path, '.json') && str_starts_with($path, SitePaths::relative(SitePaths::resources()) . '/')) {
            json_decode($contents);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON: ' . json_last_error_msg() . '. The site reads this file on every page.');
            }
        }
    }

    protected function sectionYamlStub(string $label, string $category): string
    {
        $title = Yaml::dump($label);

        return <<<YAML
        # The editor's contract for this section — each field is a prop the
        # inspector can change. Keys match the names in @props.
        title: {$title}
        category: {$category}
        fields:
            heading:
                type: text
                label: Heading
                default: {$title}
            body:
                type: textarea
                label: Body
                default: "Describe this section."

        YAML;
    }

    protected function sectionBladeStub(string $label): string
    {
        $heading = var_export($label, true);

        return <<<BLADE
        @props([
            'heading' => {$heading},
            'body' => 'Describe this section.',
        ])
        <!-- {$label} -->
        <section class="px-6 py-24">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-4xl font-semibold tracking-tight">{{ \$heading }}</h2>
                <p class="mt-4 text-lg opacity-75">{{ \$body }}</p>
            </div>
        </section>

        BLADE;
    }

    /* ------------------------------------------------------------------ */

    protected function languageFor(string $path): string
    {
        if (str_ends_with($path, '.blade.php')) {
            return 'html';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'css',
            'js', 'mjs', 'cjs' => 'javascript',
            'yml', 'yaml' => 'yaml',
            'json', 'lock' => 'json',
            'md' => 'markdown',
            'php' => 'php',
            'html', 'htm', 'svg', 'xml', 'vue' => 'html',
            default => 'plaintext',
        };
    }

    public function displayPath(string $absolute): string
    {
        return str_starts_with($absolute, base_path())
            ? ltrim(substr($absolute, strlen(base_path())), '/')
            : $absolute;
    }
}
