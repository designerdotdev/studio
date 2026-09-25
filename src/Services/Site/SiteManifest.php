<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Facades\File;

/**
 * `resources/designer/designer.json` — the settings a page file cannot
 * carry itself.
 *
 *   {
 *     "template": "monarch",
 *     "home": "home",
 *     "pages":   { "about": { "title": "About", "order": 1, "meta": {…}, "previous_slugs": ["team"] } },
 *     "layouts": { "main":  { "name": "Main" } },
 *     "blocks":  { "cta":   { "name": "Closing CTA" } }
 *   }
 *
 * The runtime provider in the app reads the same file for per-page SEO tags
 * and for redirecting renamed pages, which is why it lives with the site and
 * not in Studio's storage.
 */
final class SiteManifest
{
    public static function read(): array
    {
        $path = SitePaths::manifest();
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::normalise(is_array($decoded) ? $decoded : []);
    }

    /** Write the manifest, touching the file only when its content changed. */
    public static function write(array $manifest): bool
    {
        $manifest = self::normalise($manifest);

        if ($manifest === self::read() && is_file(SitePaths::manifest())) {
            return false;
        }

        File::ensureDirectoryExists(dirname(SitePaths::manifest()));
        File::put(SitePaths::manifest(), self::encode($manifest));

        return true;
    }

    public static function encode(array $manifest): string
    {
        // Empty maps stay objects so the file reads the same in any language
        foreach (['pages', 'layouts', 'blocks'] as $key) {
            if (($manifest[$key] ?? []) === []) {
                $manifest[$key] = new \stdClass();
            }
        }

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    protected static function normalise(array $manifest): array
    {
        return [
            'template' => isset($manifest['template']) && is_string($manifest['template']) ? $manifest['template'] : null,
            'home' => isset($manifest['home']) && is_string($manifest['home']) && $manifest['home'] !== '' ? $manifest['home'] : null,
            'pages' => self::map($manifest['pages'] ?? []),
            'layouts' => self::map($manifest['layouts'] ?? []),
            'blocks' => self::map($manifest['blocks'] ?? []),
        ];
    }

    protected static function map(mixed $value): array
    {
        return is_array($value)
            ? array_filter($value, fn ($entry, $key) => is_string($key) && is_array($entry), ARRAY_FILTER_USE_BOTH)
            : [];
    }
}
