<?php

namespace Designer\Studio\Services\Templates;

use Designer\Studio\Services\Site\PhpLiteral;
use Designer\Studio\Support\DataBag;
use Designer\Studio\Support\NestedBlade;

/**
 * Reads a site's page layout for everything that isn't a section.
 *
 * `components/layouts/main.blade.php` is where a site keeps the parts every
 * page shares: the font links, the stylesheets it hands to `@vite`, the
 * script its motion depends on, and the classes on `<html>` and `<body>`
 * that set the page's colour and type. Studio's canvas renders sections one
 * by one rather than through that layout, so those parts are lifted out
 * here and stamped onto the canvas document instead.
 *
 * Head fragments are compiled against the site data first, since a font
 * URL is often written as `{{ $site->theme->fonts_url ?? '…' }}`, and
 * against the defaults the layout's own `@props` declares (`{{ $favicon }}`).
 */
class TemplateChrome
{
    /** The layout's `@props` defaults, for the fragments that use them. */
    protected array $props = [];

    /**
     * @param  callable(string): ?string  $stylesheet  path of a `@vite` entry, or null
     * @return array{head_html: string, scripts: string[], theme_css: string, body_class: string, html_class: string}
     */
    public function extract(string $layoutSource, array $siteData, callable $stylesheet): array
    {
        $this->props = $this->propDefaults($layoutSource);
        $head = $this->section($layoutSource, 'head');

        return [
            'head_html' => $this->headHtml($head, $siteData),
            // Head first, then the ones a layout loads at the end of <body>
            'scripts' => array_values(array_unique([
                ...$this->scripts($head),
                ...$this->scripts($this->section($layoutSource, 'body')),
            ])),
            'theme_css' => $this->themeCss($head, $stylesheet),
            'body_class' => $this->bodyClass($layoutSource, $siteData),
            'html_class' => $this->attributeClass($layoutSource, 'html', $siteData),
        ];
    }

    /**
     * Font links, preconnects, the favicon, and any inline script the layout
     * runs before first paint. Stylesheet and script *sources* are handled
     * separately, so they are dropped here to avoid loading them twice.
     */
    protected function headHtml(string $head, array $siteData): string
    {
        $keep = [];

        // `[^>]*` would stop at the `>` of a `$site->theme` expression, so
        // the arrow is matched explicitly as part of the attribute text.
        if (preg_match_all('/<link\b(?:->|[^>])*>/i', $head, $links)) {
            foreach ($links[0] as $link) {
                if (preg_match('/rel\s*=\s*["\'](preconnect|dns-prefetch|icon|apple-touch-icon|stylesheet|preload)["\']/i', $link)) {
                    $keep[] = $link;
                }
            }
        }

        // Inline scripts are usually a one-liner that flags JS support so
        // scroll reveals never flash; the sections depend on it.
        if (preg_match_all('/<script\b(?!(?:->|[^>])*\bsrc=)(?:->|[^>])*>.*?<\/script>/is', $head, $inline)) {
            foreach ($inline[0] as $script) {
                $keep[] = $script;
            }
        }

        // One at a time, so a link Studio can't resolve costs only itself.
        $compiled = array_map(fn (string $element) => $this->compile($element, $siteData), $keep);

        return implode("\n", array_filter($compiled, fn (string $element) => $element !== ''));
    }

    /** @return string[] */
    protected function scripts(string $head): array
    {
        $sources = [];

        if (preg_match_all('/<script\b(?:->|[^>])*?\bsrc\s*=\s*["\']([^"\']+)["\'](?:->|[^>])*>/i', $head, $matches)) {
            $sources = $matches[1];
        }

        return array_values(array_unique($sources));
    }

    /**
     * The stylesheets the layout hands to Vite, concatenated in the order it
     * lists them — tokens first, then the rules that use them.
     */
    protected function themeCss(string $head, callable $stylesheet): string
    {
        if (!preg_match('/@vite\s*\(\s*\[?(.*?)\]?\s*\)/s', $head, $match)) {
            return '';
        }

        $parts = [];

        if (preg_match_all('/["\']([^"\']+\.css)["\']/', $match[1], $paths)) {
            foreach ($paths[1] as $relative) {
                $file = $stylesheet($relative);

                if ($file !== null && is_file($file)) {
                    $parts[] = "/* {$relative} */\n" . file_get_contents($file);
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * The classes that shape the page.
     *
     * Some templates hang the page's width and padding on `<body>`; others
     * wrap everything in a container inside it. Studio renders sections as
     * direct children of `<body>`, so the wrapper's classes are folded in —
     * without them a centred, narrow template renders edge to edge.
     */
    protected function bodyClass(string $source, array $siteData): string
    {
        $classes = [
            $this->attributeClass($source, 'body', $siteData),
            $this->slotAncestorClasses($source, $siteData),
        ];

        return trim(implode(' ', array_filter($classes)));
    }

    /**
     * Classes on the elements that still enclose `{{ $slot }}` at the point
     * it appears — found by walking the markup before it and keeping a
     * stack of the tags that have not closed again.
     */
    protected function slotAncestorClasses(string $source, array $siteData): string
    {
        $body = $this->section($source, 'body');
        $slotAt = preg_match('/\{\{\s*\$slot\s*\}\}/', $body, $m, PREG_OFFSET_CAPTURE)
            ? $m[0][1]
            : null;

        if ($slotAt === null) {
            return '';
        }

        $before = substr($body, 0, $slotAt);
        $stack = [];

        // Void elements never enclose anything, so they never open a scope.
        $void = ['br', 'hr', 'img', 'input', 'meta', 'link', 'source', 'track', 'wbr'];

        preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:->|[^>])*)>/', $before, $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            [, $closing, $name, $attributes] = $tag;
            $name = strtolower($name);

            if (in_array($name, $void, true) || str_starts_with($name, 'x-')) {
                continue;
            }

            if ($closing === '/') {
                array_pop($stack);

                continue;
            }

            if (str_ends_with(rtrim($attributes), '/')) {
                continue; // self-closing
            }

            $stack[] = preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attributes, $class)
                ? $class[1]
                : '';
        }

        $combined = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($stack))) ?? '');

        return $this->compile($combined, $siteData);
    }

    /** The class attribute of `<html>` or `<body>`, Blade resolved. */
    protected function attributeClass(string $source, string $tag, array $siteData): string
    {
        if (!preg_match('/<' . $tag . '\b[^>]*\bclass\s*=\s*"([^"]*)"/i', $source, $match)) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', $this->compile($match[1], $siteData)) ?? '');
    }

    /** The contents of a `<head>`/`<body>` element. */
    protected function section(string $source, string $tag): string
    {
        return preg_match('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', $source, $match)
            ? $match[1]
            : '';
    }

    /**
     * Resolve the Blade in a chrome fragment against the template's own site
     * data. A failure means the fragment referenced something Studio cannot
     * supply, in which case the raw markup is worse than nothing.
     */
    protected function compile(string $fragment, array $siteData): string
    {
        if (trim($fragment) === '' || !str_contains($fragment, '{{')) {
            return trim($fragment);
        }

        $data = ['site' => new DataBag($siteData)];

        foreach ($this->props as $name => $value) {
            $data[$name] ??= DataBag::wrap($value);
        }

        try {
            return trim(NestedBlade::render($fragment, $data));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * The literal defaults of the layout's `@props([...])` — parsed, never
     * evaluated, so a computed default is simply left out.
     */
    protected function propDefaults(string $source): array
    {
        if (!preg_match('/@props\s*\(/', $source, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $quote = null;

        for ($i = $start, $length = strlen($source); $i < $length && $depth > 0; $i++) {
            $char = $source[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            }
        }

        [$isLiteral, $value] = PhpLiteral::parse(substr($source, $start, $i - $start - 1));

        if (!$isLiteral) {
            return [];
        }

        return array_filter((array) $value, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
    }
}
