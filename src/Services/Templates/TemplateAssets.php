<?php

namespace Designer\Studio\Services\Templates;

/**
 * Moves a template's URLs to where its public files are installed.
 *
 * A template repo is written as though it were the whole application: its
 * markup asks for `/images/hero.jpg` and `/js/main.js` from the web root.
 * Installed, those files live under `public/designer`, so every reference
 * is moved to `/designer/images/hero.jpg`. Only paths that correspond to a
 * file the template actually ships are touched, which keeps a link like
 * `/pricing` well alone.
 */
class TemplateAssets
{
    /** @var string[] Top-level names inside the template's public/ */
    protected array $entries = [];

    public function __construct(protected string $base) {}

    /** Remember what the template's public folder holds. */
    public function scan(string $publicDir): int
    {
        $this->entries = is_dir($publicDir)
            ? collect(scandir($publicDir) ?: [])
                ->reject(fn ($entry) => $entry === '.' || $entry === '..' || str_starts_with($entry, '.'))
                ->values()
                ->all()
            : [];

        return count($this->entries);
    }

    public function baseUrl(): string
    {
        return $this->base;
    }

    /**
     * Move every reference to a public file under the install folder.
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
            // The lookbehind rejects a match in the middle of a longer path
            // (`/blog/images`, or one already moved) while still allowing
            // one at the very start of the subject.
            $text = preg_replace(
                '#(?<![A-Za-z0-9_./-])/' . preg_quote($entry, '#') . '(?=[/\'")\s,;?\#]|$)#',
                $this->base . '/' . $entry,
                $text
            ) ?? $text;
        }

        return $text;
    }
}
