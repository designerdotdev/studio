<?php

namespace Designer\Studio\Services\Templates;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Renders a template straight from its repository's working tree, without
 * installing it — local development only (`studio.template_preview`).
 *
 * A template repository is written as though it were the whole app: pages
 * under files/resources/views/pages, components beside them, data under
 * files/resources/data, public files under files/public addressed from the
 * web root. A URL becomes a page and its data by the runtime provider's
 * rules (stubs/DesignerServiceProvider.php.stub), read against the template
 * folder instead of resources/designer. Three things differ because the
 * template is not installed:
 *
 *   - components: for the length of one render Blade looks up anonymous
 *     components in the template's views/components only, so
 *     <x-sections.hero> is the template's hero and never the host site's;
 *   - @vite: `resources/css/…` entries are inlined from the template for
 *     Tailwind's browser build, exactly as an installed site's are;
 *   - URLs: public files move under /template/{slug}/_files and root links
 *     under /template/{slug}, so images load and the nav stays in the preview.
 *
 * Nothing is written anywhere: edit a file in the template, refresh.
 */
class TemplatePreview
{
    /** Tailwind's browser build, which compiles the inlined stylesheets. */
    public const TAILWIND = 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4';

    /** Public files whose text may carry URLs to other public files. */
    protected const TEXT = ['css', 'js', 'mjs', 'svg', 'json', 'webmanifest', 'xml', 'txt'];

    protected const MIME = [
        'css' => 'text/css; charset=UTF-8', 'js' => 'text/javascript; charset=UTF-8', 'mjs' => 'text/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml', 'json' => 'application/json', 'webmanifest' => 'application/manifest+json',
        'xml' => 'application/xml', 'txt' => 'text/plain; charset=UTF-8', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'avif' => 'image/avif', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf',
    ];

    public function __construct(protected string $root, protected string $prefix = 'template')
    {
        $this->root = rtrim($root, '/');
        $this->prefix = trim($prefix, '/');
    }

    /** On in the local environment, when the configured folder exists. */
    public static function enabled(): bool
    {
        return app()->isLocal() && ($root = self::configuredRoot()) !== null && is_dir($root);
    }

    public static function configuredRoot(): ?string
    {
        $path = config('studio.template_preview.path');

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, '~')) {
            $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '') : ''));
            $path = rtrim((string) $home, '/') . substr($path, 1);
        }

        return rtrim($path, '/');
    }

    public static function make(): self
    {
        return new self((string) self::configuredRoot(), (string) config('studio.template_preview.prefix', 'template'));
    }

    public function root(): string
    {
        return $this->root;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /* ------------------------------------------------------------------ */
    /*  The folder                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Every template in the folder, for the index page.
     *
     * @return list<array{slug: string, name: string, description: string, category: string, theme: string, accent: ?string, status: ?string, thumbnail: bool}>
     */
    public function catalog(): array
    {
        $ledger = $this->ledger();
        $rows = [];

        foreach (glob($this->root . '/*/template.json') ?: [] as $file) {
            $slug = basename(dirname($file));

            if ($this->directory($slug) === null) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($file), true);
            $manifest = is_array($manifest) ? $manifest : [];

            $rows[] = [
                'slug' => $slug,
                'name' => (string) ($manifest['name'] ?? Str::headline($slug)),
                'description' => (string) ($manifest['description'] ?? ''),
                'category' => (string) ($manifest['category'] ?? ''),
                'theme' => (string) ($manifest['theme'] ?? ''),
                'accent' => is_string($manifest['accent'] ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $manifest['accent']) ? $manifest['accent'] : null,
                'status' => $ledger[$slug] ?? null,
                'thumbnail' => $this->thumbnail($slug) !== null,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['slug'], $b['slug']));

        return $rows;
    }

    /** A template's repository folder, or null for anything that isn't one. */
    public function directory(string $slug): ?string
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        $dir = $this->root . '/' . $slug;

        return is_file($dir . '/template.json') && is_dir($dir . '/files/resources/views/pages') ? $dir : null;
    }

    /** The repo's thumbnail.png, else the newest batch shot of the template. */
    public function thumbnail(string $slug): ?string
    {
        if (($dir = $this->directory($slug)) === null) {
            return null;
        }

        if (is_file($dir . '/thumbnail.png')) {
            return $dir . '/thumbnail.png';
        }

        $shots = glob($this->root . '/.batches/*/shots/' . $slug . '/thumbnail.png') ?: [];
        usort($shots, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $shots[0] ?? null;
    }

    /** slug => status from LEDGER.json, when the folder keeps one. */
    protected function ledger(): array
    {
        $rows = is_file($this->root . '/LEDGER.json') ? json_decode((string) file_get_contents($this->root . '/LEDGER.json'), true) : null;
        $statuses = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && is_string($row['slug'] ?? null) && is_string($row['status'] ?? null)) {
                $statuses[$row['slug']] = $row['status'];
            }
        }

        return $statuses;
    }

    /* ------------------------------------------------------------------ */
    /*  Pages                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * The page at $path, rendered — or the template's 404 page with a 404.
     *
     * @return array{html: string, status: int}|null  null when neither exists
     */
    public function render(string $slug, string $path): ?array
    {
        if (($dir = $this->directory($slug)) === null) {
            return null;
        }

        $resources = $dir . '/files/resources';
        $data = $this->loadData($resources);
        $page = $this->resolve($resources, trim($path, '/'), $data);
        $status = 200;

        if ($page === null) {
            if (! is_file($notFound = $resources . '/views/pages/404.blade.php')) {
                return null;
            }

            [$page, $status] = [['file' => $notFound, 'data' => []], 404];
        }

        $html = $this->withTemplateComponents(
            $resources . '/views/components',
            fn () => $this->renderView($page, $data, $resources)
        );

        return ['html' => $this->rewriteUrls($html, $slug, $dir), 'status' => $status];
    }

    /**
     * The page file for a path, plus the data it renders with — the runtime
     * provider's resolution: a flat file, a folder's index, or a
     * [collection.field] page matched against that collection's rows.
     *
     * @return array{file: string, data: array}|null
     */
    protected function resolve(string $resources, string $path, array $data): ?array
    {
        $segments = $path === '' ? [] : explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment[0] === '.') {
                return null; // traversal and hidden files never resolve
            }
        }

        $pages = $resources . '/views/pages';
        $relative = $path === '' ? 'index' : $path;

        foreach ([$relative . '.blade.php', $relative . '/index.blade.php'] as $candidate) {
            if (is_file($pages . '/' . $candidate)) {
                return ['file' => $pages . '/' . $candidate, 'data' => []];
            }
        }

        if ($segments === []) {
            return null;
        }

        $value = array_pop($segments);
        $folder = $pages . ($segments === [] ? '' : '/' . implode('/', $segments));

        foreach (glob($folder . '/[[]*[]].blade.php') ?: [] as $file) {
            if (! preg_match('/^\[([A-Za-z_]\w*)\.([A-Za-z_]\w*)\]$/', basename($file, '.blade.php'), $m)) {
                continue;
            }

            $entries = $data[$m[1]] ?? [];

            foreach (is_array($entries) ? $entries : [] as $entry) {
                $field = is_object($entry) ? ($entry->{$m[2]} ?? null) : null;

                if (is_scalar($field) && (string) $field === $value) {
                    return ['file' => $file, 'data' => [$m[1] => $entry, 'entries' => $entries]];
                }
            }
        }

        return null;
    }

    protected function renderView(array $page, array $data, string $resources): string
    {
        $factory = app('view');

        // Collections first and $site last, so a collection named "site"
        // can never displace the site object.
        foreach ($data as $key => $value) {
            $factory->share($key, $value);
        }

        $vite = app(Vite::class);
        app()->instance(Vite::class, $this->inlineVite($vite, $resources));

        try {
            return $factory->file($page['file'], $page['data'])->render();
        } finally {
            app()->instance(Vite::class, $vite);
        }
    }

    /**
     * Render with the template's components as the only anonymous component
     * path. The host site's own path (registered at boot by its runtime
     * provider and by Studio) would otherwise win for every shared name.
     * Blade has no public way to unregister a path, so the list is swapped
     * for the render and restored afterwards, even when the render throws.
     */
    protected function withTemplateComponents(string $components, \Closure $render): string
    {
        $compiler = app('blade.compiler');
        $swap = \Closure::bind(function (array $paths): array {
            $previous = $this->anonymousComponentPaths;
            $this->anonymousComponentPaths = $paths;

            return $previous;
        }, $compiler, BladeCompiler::class);

        $previous = $swap([]);

        try {
            if (is_dir($components)) {
                $compiler->anonymousComponentPath($components);
            }

            return $render();
        } finally {
            $swap($previous);
        }
    }

    /**
     * The app's Vite, except that entries naming the template's resources
     * (`resources/css/site.css`, or the installed spelling under
     * resources/designer) come back inlined for Tailwind's browser build.
     */
    protected function inlineVite(Vite $vite, string $resources): Vite
    {
        return new class ($vite, $resources) extends Vite {
            protected string $templateResources;

            public function __construct(Vite $app, string $resources)
            {
                foreach (get_object_vars($app) as $property => $value) {
                    $this->{$property} = $value;
                }

                $this->templateResources = $resources;
            }

            public function __invoke($entrypoints = null, $buildDirectory = null)
            {
                $styles = [];
                $app = [];

                foreach (is_string($entrypoints) ? [$entrypoints] : (array) $entrypoints as $entry) {
                    $relative = preg_replace('#^resources/(designer/)?#', '', (string) $entry, 1, $count);

                    if ($count === 1 && is_file($file = $this->templateResources . '/' . $relative)) {
                        if (! str_ends_with($relative, '.js')) {
                            $styles[] = '<style type="text/tailwindcss">' . file_get_contents($file) . '</style>';
                        }

                        continue;
                    }

                    $app[] = $entry;
                }

                $html = $app === [] ? '' : (string) parent::__invoke($app, $buildDirectory);

                if ($styles !== []) {
                    $html .= '<script src="' . TemplatePreview::TAILWIND . '"></script>' . "\n" . implode("\n", $styles);
                }

                return new HtmlString($html);
            }
        };
    }

    /** Public files under /template/{slug}/_files; root links under /template/{slug}. */
    protected function rewriteUrls(string $html, string $slug, string $dir): string
    {
        $base = '/' . $this->prefix . '/' . $slug;
        $html = $this->assets($slug, $dir)->rewrite($html);

        return preg_replace_callback(
            '#(\shref\s*=\s*)(["\'])/(?!/)([^"\']*)\2#i',
            function (array $m) use ($base) {
                if (str_starts_with($m[3], $this->prefix . '/')) {
                    return $m[0]; // already inside the preview (a moved public file)
                }

                $target = $m[3] === '' ? $base : $base . (str_starts_with($m[3], '#') || str_starts_with($m[3], '?') ? '' : '/') . $m[3];

                return $m[1] . $m[2] . $target . $m[2];
            },
            $html
        ) ?? $html;
    }

    protected function assets(string $slug, string $dir): TemplateAssets
    {
        $assets = new TemplateAssets('/' . $this->prefix . '/' . $slug . '/_files');
        $assets->scan($dir . '/files/public');

        return $assets;
    }

    /* ------------------------------------------------------------------ */
    /*  Public files                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * A file from the template's files/public, with URLs in text files moved.
     *
     * @return array{path: string, type: string, body: ?string}|null
     */
    public function file(string $slug, string $path): ?array
    {
        if (($dir = $this->directory($slug)) === null || ($public = realpath($dir . '/files/public')) === false) {
            return null;
        }

        $file = realpath($public . '/' . ltrim($path, '/'));

        if ($file === false || ! is_file($file) || ! str_starts_with($file, $public . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return [
            'path' => $file,
            'type' => self::MIME[$extension] ?? (mime_content_type($file) ?: 'application/octet-stream'),
            'body' => in_array($extension, self::TEXT, true) ? $this->assets($slug, $dir)->rewrite((string) file_get_contents($file)) : null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Data                                                               */
    /* ------------------------------------------------------------------ */

    /** $site, every collection, and every markdown content folder, as objects. */
    protected function loadData(string $resources): array
    {
        $data = [];

        foreach (glob($resources . '/data/collections/*.json') ?: [] as $file) {
            $name = basename($file, '.json');
            $rows = json_decode((string) file_get_contents($file));

            if (preg_match('/^[A-Za-z_]\w*$/', $name) && is_array($rows)) {
                $data[$name] = $rows;
            }
        }

        foreach (glob($resources . '/data/content/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $name = basename($folder);
            $files = glob($folder . '/*.md') ?: [];
            sort($files);

            if (! preg_match('/^[A-Za-z_]\w*$/', $name) || $files === []) {
                continue;
            }

            $data[$name] = array_values(array_filter(array_map(fn ($file) => $this->markdownEntry($file, $name), $files)));
        }

        $site = is_file($resources . '/data/site.json') ? json_decode((string) file_get_contents($resources . '/data/site.json')) : null;
        $data['site'] = is_object($site) ? $site : new \stdClass;

        return $data;
    }

    /** A markdown file as {…frontmatter, content, link}, or null if unreadable. */
    protected function markdownEntry(string $file, string $folder): ?object
    {
        $source = (string) file_get_contents($file);
        $fields = [];

        if (preg_match('/\A\x{FEFF}?---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/su', $source, $m)) {
            foreach (preg_split('/\r?\n/', $m[1]) as $line) {
                if (trim($line) === '') {
                    continue;
                }

                if (! preg_match('/^([^\s:][^:]*):\s*(.+)$/', $line, $pair)) {
                    return null; // only flat `key: value` frontmatter is supported
                }

                $value = trim($pair[2]);
                $fields[trim($pair[1])] = match (true) {
                    preg_match('/^(["\']).*\1$/', $value) === 1 => substr($value, 1, -1),
                    $value === 'true' => true,
                    $value === 'false' => false,
                    preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
                    preg_match('/^-?\d+\.\d+$/', $value) === 1 => (float) $value,
                    default => $value,
                };
            }

            $source = $m[2];
        }

        $fields['content'] = Str::markdown($source, ['html_input' => 'allow', 'allow_unsafe_links' => true]);
        $fields['link'] = '/' . $folder . '/' . basename($file, '.md');

        return (object) $fields;
    }
}
