<?php

namespace Designer\Studio\Services\Templates;

use Illuminate\Support\Facades\File;

/**
 * Publishes a template's own files and rewrites the URLs that point at them.
 *
 * A template repo is written as though it were the whole application: its
 * markup asks for `/images/hero.jpg` and `/js/main.js` from the web root.
 * Several templates can be installed side by side here, so each one's files
 * are published under a folder of its own and every reference is moved to
 * match. Only paths that actually correspond to a published file are
 * touched, which keeps a link like `/pricing` well alone.
 */
class TemplateAssets
{
    /** @var string[] Top-level names published from the template's public/ */
    protected array $entries = [];

    protected string $base = '';

    public function __construct(protected string $slug) {}

    /**
     * Copy `files/public/*` into the app's web root under this template's
     * own folder, and remember what was published so URLs can be rewritten.
     */
    public function publish(string $templateDir): int
    {
        $source = $templateDir . '/files/public';
        $this->base = '/' . trim((string) config('studio.templates.assets_path', 'studio-templates'), '/') . '/' . $this->slug;
        $target = public_path(ltrim($this->base, '/'));

        if (!is_dir($source)) {
            return 0;
        }

        File::ensureDirectoryExists($target);
        File::copyDirectory($source, $target);

        $this->entries = collect(scandir($source) ?: [])
            ->reject(fn ($entry) => $entry === '.' || $entry === '..' || $entry === '.DS_Store')
            ->values()
            ->all();

        return count($this->entries);
    }

    public function baseUrl(): string
    {
        return $this->base;
    }

    /**
     * Move every reference to a published file under this template's folder.
     *
     * Matching is anchored on the delimiters that surround a URL in markup,
     * CSS, and JSON, so `/images/x.png` is rewritten while the word
     * "images" in a sentence is not.
     */
    public function rewrite(string $text): string
    {
        if ($text === '' || $this->entries === []) {
            return $text;
        }

        foreach ($this->entries as $entry) {
            $quoted = preg_quote($entry, '#');

            // The lookbehind rejects a match in the middle of a longer
            // path (`/blog/images`) while still allowing one at the very
            // start of the subject, which is how a bare `src` arrives.
            $text = preg_replace(
                '#(?<![A-Za-z0-9_./-])/' . $quoted . '(?=[/\'")\s,;]|$)#',
                ltrim($this->base, '/') === '' ? '/' . $entry : $this->base . '/' . $entry,
                $text
            ) ?? $text;
        }

        return $text;
    }

    /** Rewrite every string inside a decoded data structure. */
    public function rewriteData(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->rewrite($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->rewriteData($item), $value);
        }

        return $value;
    }

    /** Remove a previously published folder (a fresh import starts clean). */
    public function purge(): void
    {
        $base = '/' . trim((string) config('studio.templates.assets_path', 'studio-templates'), '/') . '/' . $this->slug;
        $target = public_path(ltrim($base, '/'));

        if (is_dir($target)) {
            File::deleteDirectory($target);
        }
    }
}
