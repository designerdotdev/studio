<?php

namespace Designer\Studio\Support;

use Designer\Studio\Services\Storage\SiteRepository;
use Illuminate\Support\HtmlString;

/**
 * The head and body chrome a site needs beyond its sections.
 *
 * A template brings a palette, a font pairing, and usually a small script
 * that drives its motion. Those belong to the whole site rather than to any
 * one section, so they live in the site document and are stamped onto every
 * surface that renders it: the editor canvas, the published page, the draft
 * preview, and Blade exports.
 *
 * The CSS is handed to Tailwind's browser build as
 * `<style type="text/tailwindcss">`, which is what lets a template's
 * `@theme` tokens become real utilities (`bg-canvas`, `text-ink`) exactly as
 * they do under a compiled build.
 */
class SiteChrome
{
    public function __construct(
        protected SiteRepository $site
    ) {}

    /** Everything that belongs in <head>, in the order it must appear. */
    public function head(): HtmlString
    {
        $parts = array_filter([
            $this->site->headHtml(),
            $this->themeStyle(),
            $this->scriptTags(),
        ]);

        return new HtmlString(implode("\n", $parts));
    }

    /**
     * The template's stylesheet, wrapped so Tailwind's browser build
     * compiles it. `@import` rules are hoisted out first: CSS requires them
     * before any other rule, and the font imports are what carry the
     * template's typeface.
     */
    public function themeStyle(): string
    {
        $css = trim($this->site->themeCss());

        if ($css === '') {
            return '';
        }

        $imports = [];

        $css = preg_replace_callback(
            '/@import\s+(?:url\()?["\']?([^"\')\s;]+)["\']?\)?[^;]*;/i',
            function (array $match) use (&$imports) {
                // Tailwind itself is already on the page via the browser
                // build — re-importing it would be a no-op at best.
                if (!str_contains($match[1], 'tailwindcss')) {
                    $imports[] = $match[1];
                }

                return '';
            },
            $css
        ) ?? $css;

        $links = collect($imports)
            ->unique()
            ->map(fn ($href) => '<link rel="stylesheet" href="' . e($href) . '">')
            ->implode("\n");

        // `@source` scans the filesystem at build time; there is no
        // filesystem here, and leaving it in makes the browser build warn.
        $css = preg_replace('/@source\s+[^;]+;/i', '', $css) ?? $css;

        return trim($links . "\n" . '<style type="text/tailwindcss">' . "\n" . trim($css) . "\n" . '</style>');
    }

    public function scriptTags(): string
    {
        return collect($this->site->scripts())
            ->map(fn ($src) => '<script src="' . e($src) . '" defer></script>')
            ->implode("\n");
    }

    /** The template's own body classes, or the configured default. */
    public function bodyClass(): string
    {
        $class = trim($this->site->bodyClass());

        return $class !== ''
            ? $class
            : (string) config('studio.iframe.body_class', 'min-h-screen w-full');
    }

    public function htmlClass(): string
    {
        return trim($this->site->htmlClass());
    }
}
