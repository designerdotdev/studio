<?php

namespace Designer\Studio\Support;

/**
 * Resolves URLs for the compiled Studio assets.
 *
 * Published copies (public/vendor/studio, via `studio:publish` or
 * `vendor:publish --tag=studio-assets`) are preferred — they're served as
 * static files by the web server. Without them, assets are served from the
 * package dist/ through AssetController.
 */
class StudioAssets
{
    public const FILES = [
        'studio.js',
        'studio-css.css',
        'studio-monaco.js',
        'studio-monaco.css',
        'monaco-editor-worker.js',
        'monaco-html-worker.js',
        'codicon.ttf',
    ];

    public const PUBLISH_PATH = 'vendor/studio';

    public static function url(string $file): string
    {
        $published = public_path(self::PUBLISH_PATH . '/' . $file);

        if (file_exists($published)) {
            return url(self::PUBLISH_PATH . '/' . $file) . '?v=' . filemtime($published);
        }

        $prefix = config('studio.path', 'studio');

        $dist = dirname(__DIR__, 2) . '/dist/' . $file;

        return url($prefix . '/assets/' . $file) . '?v=' . (is_file($dist) ? filemtime($dist) : 'dev');
    }
}
