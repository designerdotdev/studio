<?php

namespace Designer\Studio\Services;

use Designer\Studio\Support\SitePaths;
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
 *              shown as `resources/` and `public/` holding just `designer/`
 *   laravel    the host application's own directories (self::LARAVEL_DIRS),
 *              with the site's two folders marked so they stay findable
 *
 * The views overlap (resources/designer is inside resources/), but a file
 * has one path in both, so a tab, an unsaved buffer, or a save is the same
 * whichever view it was opened from.
 *
 * Every path from the browser is resolved with realpath() against one of
 * the application's own directories and rejected if it escapes it; `..`,
 * hidden files, and unlisted extensions never resolve, which is what keeps
 * `.env` and the rest of the project out.
 */
class CodeWorkspace
{
    /** Extensions the editor will open. Anything else is invisible. */
    public const EXTENSIONS = ['html', 'yml', 'yaml', 'css', 'js', 'php', 'json', 'md', 'txt', 'svg'];

    /**
     * The host application's own directories, offered by the Laravel tree
     * view. Deliberately an allowlist rather than "the project root minus a
     * few things": `storage/` holds Studio's own documents (hand-editing
     * them desyncs the editor) and nothing here is a dotfile, so `.env` is
     * unreachable — it is never listed, and never resolves.
     */
    public const LARAVEL_DIRS = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'tests'];

    /** Never descended into, wherever they appear. */
    public const SKIP_DIRS = ['vendor', 'node_modules', '.git'];

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
     * A flat node list — {path, name, type, depth, parent, design} — ordered
     * so the browser can render it as a tree without recursing. Directories
     * come before files at every level, both alphabetical.
     *
     * @param  string  $view  'designer' (the site) or 'laravel' (the app)
     */
    public function tree(string $view = 'designer'): array
    {
        $nodes = [];

        if ($view === 'laravel') {
            foreach (self::LARAVEL_DIRS as $dir) {
                if (!is_dir(base_path($dir))) {
                    continue;
                }

                $nodes[] = $this->node($dir, $dir, 'dir', 0, null);
                $this->walk(base_path($dir), $dir, 1, $nodes);
            }

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

    protected function node(string $path, string $name, string $type, int $depth, ?string $parent): array
    {
        return [
            'path' => $path,
            'name' => $name,
            'type' => $type,
            'depth' => $depth,
            'parent' => $parent,
            'design' => $this->isDesignPath($path),
        ];
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

            // A dangling symlink looks like a directory to File::directories()
            // but explodes on descent; one pointing out of the project would
            // take the tree somewhere it may not go.
            $real = realpath($dir);

            if ($real === false || !is_dir($real) || !$this->insideBase($real)) {
                continue;
            }

            $path = $prefix . '/' . $name;
            $nodes[] = $this->node($path, $name, 'dir', $depth, $prefix);
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
                && !str_starts_with($file->getFilename(), '.')
        );

        usort($files, fn ($a, $b) => strcmp($a->getFilename(), $b->getFilename()));

        foreach ($files as $file) {
            $nodes[] = $this->node($prefix . '/' . $file->getFilename(), $file->getFilename(), 'file', $depth, $prefix);
        }
    }

    /** Read one workspace file. */
    public function read(string $path): array
    {
        $absolute = $this->resolveExisting($path);

        if (filesize($absolute) > self::MAX_BYTES) {
            throw new RuntimeException('That file is too large to open in the editor.');
        }

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

        return ['removed' => $removed];
    }

    /* ------------------------------------------------------------------ */
    /*  Path safety                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Turn a workspace path into an absolute path inside one of the app's
     * directories, or throw. This is the only place browser input becomes a
     * file path.
     */
    public function resolveExisting(string $path): string
    {
        $path = $this->normalise($path);
        $root = explode('/', $path)[0];

        if (!in_array($root, self::LARAVEL_DIRS, true)) {
            throw new RuntimeException('That file is outside the Studio workspace.');
        }

        $candidate = realpath(base_path($path));
        $base = realpath(base_path($root));

        if ($candidate === false || $base === false || !is_file($candidate) || !str_starts_with($candidate, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('That file could not be found in the Studio workspace.');
        }

        return $candidate;
    }

    /**
     * Validate a workspace path's shape — every traversal trick and every
     * unlisted extension rejected up front — and return it normalised.
     */
    protected function normalise(string $path): string
    {
        $path = trim(trim($path), '/');

        if ($path === '' || !preg_match('#^[A-Za-z0-9._/@-]+$#', $path)) {
            throw new RuntimeException('That is not a valid file path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
                throw new RuntimeException('That is not a valid file path.');
            }
        }

        if (!str_contains($path, '/')) {
            throw new RuntimeException('That is a folder, not a file.');
        }

        if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            throw new RuntimeException('Studio only edits ' . implode(', ', self::EXTENSIONS) . ' files.');
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
