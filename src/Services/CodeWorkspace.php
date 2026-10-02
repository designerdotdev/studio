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
 * The file set the Code view can browse and edit: the whole application
 * root, hidden files included, as real paths relative to it. The tree comes
 * back one folder per request (a project walked whole blows any node cap),
 * with the site's two folders — `resources/designer` and `public/designer` —
 * marked `design` so they stay findable.
 *
 * Files and folders can be created, renamed, duplicated and deleted here
 * too. What must not move is refused: dependency trees, the top-level
 * project folders, and the folders the site itself lives in.
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

    /** A folder holding more than this is not deleted from a browser. */
    public const MAX_DELETE = 500;

    /** The site's two folders, as workspace paths. */
    public function siteRoots(): array
    {
        return [
            SitePaths::relative(SitePaths::resources()),
            SitePaths::relative(SitePaths::public()),
        ];
    }

    /**
     * One folder of the application as a flat node list — {path, name, type,
     * depth, parent, design, lazy, inert, note} — directories before files,
     * both alphabetical. `$dir` null is the root. Every sub-folder is `lazy`:
     * its children are a request of their own. An `inert` node is listed but
     * never opened; `note` says why.
     */
    public function tree(?string $dir = null): array
    {
        $nodes = [];
        $dir = trim((string) $dir, '/') === '' ? null : $this->normaliseDirectory($dir);

        $this->walk(
            $dir === null ? base_path() : base_path($dir),
            $dir,
            $dir === null ? 0 : substr_count($dir, '/') + 1,
            $nodes,
        );

        return $nodes;
    }

    /**
     * Every text file of the site, as workspace paths — the index the
     * palette's quick-open searches, since the tree itself only knows the
     * folders that have been opened.
     *
     * @return list<string>
     */
    public function siteFiles(): array
    {
        $files = [];

        foreach ($this->siteRoots() as $root) {
            if (!is_dir(base_path($root))) {
                continue;
            }

            $walker = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($walker as $entry) {
                if (count($files) >= self::MAX_NODES) {
                    break 2;
                }

                $path = $root . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $walker->getSubPathname());

                if (
                    $entry->isFile()
                    && preg_match(self::PATH_PATTERN, $path)
                    && !in_array(strtolower($entry->getExtension()), self::BINARY_EXTENSIONS, true)
                ) {
                    $files[] = $path;
                }
            }
        }

        sort($files, SORT_STRING | SORT_FLAG_CASE);

        return $files;
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
     * List one directory into $nodes — every entry, hidden ones included. A
     * null $prefix is the application root.
     */
    protected function walk(string $absolute, ?string $prefix, int $depth, array &$nodes): void
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
            $node['lazy'] = true;
            $nodes[] = $node;
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

        // Must already exist — new files go through create()
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

    /**
     * Create an empty file or a folder. Its parent has to exist already, and
     * nothing may be there yet.
     */
    public function create(string $path, string $type): array
    {
        $path = $this->normalise($path);
        $absolute = $this->resolveNew($path);

        if ($type === 'dir') {
            File::makeDirectory($absolute);
        } else {
            if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::BINARY_EXTENSIONS, true)) {
                throw new RuntimeException('The editor only makes text files.');
            }

            // The site reads its JSON on every page: an empty file would not parse
            File::put($absolute, str_ends_with($path, '.json') ? "{}\n" : '');
        }

        app(TemplateLink::class)->touch();

        return ['path' => $path, 'type' => $type === 'dir' ? 'dir' : 'file'];
    }

    /**
     * Rename (or move) a file or a folder. A section is a pair, so renaming
     * either half renames both.
     *
     * @return array{path: string, type: string, language: ?string, moved: array<int, array{from: string, to: string}>}
     */
    public function rename(string $from, string $to): array
    {
        $from = $this->normalise($from);
        $to = $this->normalise($to);

        if ($from === $to) {
            throw new RuntimeException('That is already its name.');
        }

        $source = $this->resolveMovable($from);
        $moves = [[$from, $to]];

        if (is_file($source)) {
            $this->resolveExisting($from);

            // Both halves of a section follow one name
            if ($this->isSectionSource($from)) {
                [$stem, $suffix] = $this->splitSection($from);

                if (!str_ends_with($to, $suffix)) {
                    throw new RuntimeException("A section's file keeps its {$suffix} ending — the editor pairs it with its other half by name.");
                }

                $target = substr($to, 0, -strlen($suffix));
                $moves = [];

                foreach (['.blade.php', '.yml'] as $half) {
                    $moves[] = [$stem . $half, $target . $half];
                }
            }
        }

        foreach ($moves as [, $destination]) {
            $this->resolveNew($destination);
        }

        foreach ($moves as [$origin, $destination]) {
            if (!@rename(base_path($origin), base_path($destination))) {
                throw new RuntimeException('That could not be renamed — check the folder\'s permissions.');
            }
        }

        app(TemplateLink::class)->touch();

        return [
            'path' => $to,
            'type' => is_dir(base_path($to)) ? 'dir' : 'file',
            'language' => is_dir(base_path($to)) ? null : $this->languageFor($to),
            'moved' => array_map(fn ($move) => ['from' => $move[0], 'to' => $move[1]], $moves),
        ];
    }

    /**
     * Copy a file beside itself as `<name>-copy`. A section is copied as a
     * pair, which makes a new section.
     */
    public function duplicate(string $path): array
    {
        $path = $this->normalise($path);
        $this->resolveExisting($path);

        $section = $this->isSectionSource($path);
        [$stem, $suffix] = $section ? $this->splitSection($path) : $this->splitName($path);
        $halves = $section ? ['.blade.php', '.yml'] : [$suffix];

        // -copy, then -copy-2, -copy-3… until every half of the name is free
        for ($n = 1, $copy = null; $copy === null && $n < 100; $n++) {
            $candidate = $stem . '-copy' . ($n > 1 ? '-' . $n : '');

            foreach ($halves as $half) {
                if (file_exists(base_path($candidate . $half))) {
                    continue 2;
                }
            }

            $copy = $candidate;
        }

        if ($copy === null) {
            throw new RuntimeException('There are too many copies of that file already.');
        }

        foreach ($halves as $half) {
            $this->resolveNew($copy . $half);
            File::copy(base_path($stem . $half), base_path($copy . $half));
        }

        app(TemplateLink::class)->touch();

        return ['path' => $copy . $suffix, 'type' => 'file'];
    }

    /**
     * Delete a file — a section takes its field contract with it — or a
     * folder and everything in it.
     */
    public function delete(string $path): array
    {
        $path = $this->normalise($path);

        if (is_dir(base_path($path))) {
            return $this->deleteDirectory($path);
        }

        $absolute = $this->resolveExisting($path);
        $removed = [$path];

        // A section is a pair; leaving half of it behind breaks the library
        if ($this->isSectionSource($path)) {
            [$stem] = $this->splitSection($path);
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

        return ['removed' => $removed, 'type' => 'file'];
    }

    /** A folder goes whole — unless it is one that must stay, or too much to lose in a click. */
    protected function deleteDirectory(string $path): array
    {
        $absolute = $this->resolveMovable($path);
        $count = 0;

        $walker = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($walker as $entry) {
            if (in_array($entry->getFilename(), self::INERT_DIRS, true)) {
                throw new RuntimeException('That folder holds dependencies or a repository — delete it from a terminal.');
            }

            if (++$count > self::MAX_DELETE) {
                throw new RuntimeException('That folder holds more than ' . self::MAX_DELETE . ' files — delete it from a terminal.');
            }
        }

        File::deleteDirectory($absolute);
        app(TemplateLink::class)->touch();

        return ['removed' => [$path], 'type' => 'dir'];
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
     * A path nothing lives at yet, whose parent folder exists inside the
     * application. Returns where it will be on disk.
     */
    protected function resolveNew(string $path): string
    {
        $absolute = base_path($path);
        $parent = realpath(dirname($absolute));

        if ($parent === false || !is_dir($parent) || !$this->insideBase($parent)) {
            throw new RuntimeException('That folder could not be found in the Studio workspace.');
        }

        if (file_exists($absolute) || is_link($absolute)) {
            throw new RuntimeException('"' . basename($path) . '" already exists there.');
        }

        return $parent . DIRECTORY_SEPARATOR . basename($path);
    }

    /**
     * A file or folder that may be renamed or deleted. The folders the app
     * and the site stand on stay where they are.
     */
    protected function resolveMovable(string $path): string
    {
        $absolute = base_path($path);

        if (is_link($absolute)) {
            throw new RuntimeException('That is a link to somewhere else — change it from a terminal.');
        }

        $real = realpath($absolute);

        if ($real === false || !$this->insideBase($real)) {
            throw new RuntimeException('That could not be found in the Studio workspace.');
        }

        if (is_dir($real)) {
            if (!str_contains($path, '/')) {
                throw new RuntimeException('A top-level project folder can\'t be renamed or deleted from Studio.');
            }

            foreach ($this->siteRoots() as $root) {
                if ($root === $path || str_starts_with($root, $path . '/')) {
                    throw new RuntimeException('The site lives in that folder — it can\'t be renamed or deleted.');
                }
            }
        }

        return $real;
    }

    /** A section file as [its path without the ending, `.blade.php` | `.yml`]. */
    protected function splitSection(string $path): array
    {
        $suffix = str_ends_with($path, '.blade.php') ? '.blade.php' : '.yml';

        return [substr($path, 0, -strlen($suffix)), $suffix];
    }

    /** Any file as [its path without the ending, the ending] — `.blade.php` counts as one. */
    protected function splitName(string $path): array
    {
        if (str_ends_with($path, '.blade.php')) {
            return [substr($path, 0, -10), '.blade.php'];
        }

        $name = basename($path);
        $dot = strrpos($name, '.');

        // No ending, or a dotfile (.env): the whole name is the stem
        if ($dot === false || $dot === 0) {
            return [$path, ''];
        }

        $suffix = substr($name, $dot);

        return [substr($path, 0, -strlen($suffix)), $suffix];
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

    /** A folder the tree may list, normalised like a file path. */
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

        [$stem] = $this->splitSection($path);

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
