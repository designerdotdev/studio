<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\Services\RenderContext;
use Designer\Studio\Support\SitePaths;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * The draft preview of a page Studio keeps no document for.
 *
 * A code page — a [collection.field] dynamic page, a nested page, one with
 * markup of its own — is served by the runtime straight from its file, so
 * the draft preview has nothing to compose it from. It is rendered here the
 * way the runtime renders it (same resolution, same static @vite projection),
 * with the draft's `$site` and collections in place of the published files:
 * a post edited in Content shows at its preview address before Publish.
 */
class CodePagePreview
{
    /** Tailwind's browser build, as the runtime loads it. */
    protected const TAILWIND = 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4';

    public function __construct(protected RenderContext $context) {}

    /** The rendered page for a path under the preview, or null when no file answers it. */
    public function render(string $path): ?string
    {
        $data = $this->context->globals();

        if (($page = $this->resolve(trim($path, '/'), $data)) === null) {
            return null;
        }

        $factory = app('view');

        foreach ($data as $key => $value) {
            $factory->share($key, $value);
        }

        $vite = app(Vite::class);
        app()->instance(Vite::class, $this->staticVite($vite));

        try {
            return $factory->file($page['file'], $page['data'])->render();
        } finally {
            app()->instance(Vite::class, $vite);
        }
    }

    /**
     * The page file for a path, plus the data it renders with — the runtime
     * provider's resolution: a flat file, a folder's index, or a
     * [collection.field] page matched against that collection's rows.
     *
     * @return array{file: string, data: array}|null
     */
    protected function resolve(string $path, array $data): ?array
    {
        $segments = $path === '' ? [] : explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment[0] === '.') {
                return null; // traversal and hidden files never resolve
            }
        }

        if ($segments === []) {
            return null;
        }

        $pages = SitePaths::pages();

        // A flat file wins over a same-named folder's index
        foreach ([$path . '.blade.php', $path . '/index.blade.php'] as $candidate) {
            if (is_file($pages . '/' . $candidate)) {
                return ['file' => $pages . '/' . $candidate, 'data' => []];
            }
        }

        // /<folder>/<value> against [collection.field].blade.php in <folder>
        $value = array_pop($segments);
        $folder = $pages . ($segments === [] ? '' : '/' . implode('/', $segments));

        foreach (glob($folder . '/[[]*[]].blade.php') ?: [] as $file) {
            if (! preg_match('/^\[([A-Za-z_]\w*)\.([A-Za-z_]\w*)\]$/', basename($file, '.blade.php'), $m)) {
                continue;
            }

            $entries = $data[$m[1]] ?? [];

            foreach (is_iterable($entries) ? $entries : [] as $entry) {
                $field = is_object($entry) ? ($entry->{$m[2]} ?? null) : null;

                if (is_scalar($field) && (string) $field === $value) {
                    // The row shadows its collection in this page only
                    return ['file' => $file, 'data' => [$m[1] => $entry, 'entries' => $entries]];
                }
            }
        }

        return null;
    }

    /**
     * The app's Vite, except that entries under resources/designer come back
     * inlined for Tailwind's browser build — the runtime's own projection.
     */
    protected function staticVite(Vite $vite): Vite
    {
        return new class ($vite, self::TAILWIND) extends Vite {
            protected string $tailwind;

            public function __construct(Vite $app, string $tailwind)
            {
                foreach (get_object_vars($app) as $property => $value) {
                    $this->{$property} = $value;
                }

                $this->tailwind = $tailwind;
            }

            public function __invoke($entrypoints = null, $buildDirectory = null)
            {
                $styles = [];
                $app = [];

                foreach (is_string($entrypoints) ? [$entrypoints] : (array) $entrypoints as $entry) {
                    if (! str_starts_with((string) $entry, 'resources/designer/')) {
                        $app[] = $entry;
                    } elseif (! str_ends_with($entry, '.js') && is_file(base_path($entry))) {
                        $styles[] = '<style type="text/tailwindcss">' . file_get_contents(base_path($entry)) . '</style>';
                    }
                }

                $html = $app === [] ? '' : (string) parent::__invoke($app, $buildDirectory);

                if ($styles !== []) {
                    $html .= '<script src="' . $this->tailwind . '"></script>' . "\n" . implode("\n", $styles);
                }

                return new HtmlString($html);
            }
        };
    }
}
