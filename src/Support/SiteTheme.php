<?php

namespace Designer\Studio\Support;

/**
 * The site's theme, as the Theme panel sets it: a palette, an accent, corners
 * and a type pairing laid over the template's own — nothing more than values
 * for the Designer global variables (`--background`, `--primary`, `--radius`…)
 * and the template tokens derived from them.
 *
 * It lives in the site document (`theme`), so it is drafted, published,
 * discarded and undone with everything else, and on disk in two places:
 * the choices in designer.json, what they come to as one marked block at the
 * end of css/site.css, and the type pairing's font as one marked <link> in
 * each layout's <head>.
 * Until it is published the canvas and the draft preview carry it as a
 * `<style>` of their own (`head()`).
 */
class SiteTheme
{
    /** Every variable a theme may set. */
    public const VARIABLES = [
        '--background', '--foreground', '--card', '--card-foreground', '--popover', '--popover-foreground',
        '--primary', '--primary-foreground', '--secondary', '--secondary-foreground', '--muted', '--muted-foreground',
        '--accent', '--accent-foreground', '--destructive', '--border', '--input', '--ring',
        '--radius', '--radius-medium', '--radius-large', '--radius-control', '--font-display', '--font-sans',
        '--color-border-strong', '--color-accent-soft', '--color-accent-deep', '--color-accent-hover',
        '--color-accent-bright', '--color-shade-accent', '--color-shade', '--color-shade-foreground',
        '--color-shade-muted-foreground', '--color-shade-border',
    ];

    protected const CHOICES = ['palette', 'accent', 'radius', 'type'];

    protected const START = "/* designer:theme — set in Studio's Theme panel. Change it there; removing this block puts the template's own values back. */";

    protected const END = '/* /designer:theme */';

    /** The attribute that marks the theme's font <link> in a layout's <head>. */
    protected const FONT = 'data-designer-theme-font';

    /** The stylesheet the block is written into. */
    public static function stylesheet(): string
    {
        return SitePaths::resources('css/site.css');
    }

    /**
     * A theme as the panel posted it, reduced to what is safe to write into
     * a stylesheet — or null when it changes nothing.
     */
    public static function clean(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $theme = [];

        foreach (self::CHOICES as $key) {
            $value = $input[$key] ?? 'own';
            $theme[$key] = is_string($value) && preg_match('/^[a-z0-9-]{1,32}$/', $value) ? $value : 'own';
        }

        $vars = [];

        foreach (self::VARIABLES as $name) {
            $value = $input['vars'][$name] ?? null;

            // A CSS value and nothing else: no way out of the declaration
            if (is_string($value)
                && strlen($value) <= 200
                && preg_match('/^[#(),.%\'\w\s\/+-]+$/', $value)
                && ! preg_match('/url\s*\(|expression|[\r\n]/i', $value)
            ) {
                $vars[$name] = trim($value);
            }
        }

        if ($vars === []) {
            return null;
        }

        $font = $input['font'] ?? null;
        $scheme = $input['scheme'] ?? null;

        return $theme + [
            'scheme' => in_array($scheme, ['light', 'dark'], true) ? $scheme : null,
            // A Google Fonts css2 `family=` query, as the type pairing names it
            'font' => is_string($font) && strlen($font) <= 300 && preg_match('/^[A-Za-z0-9+:;@,.&=]+$/', $font) ? $font : null,
            'vars' => $vars,
        ];
    }

    public static function fontUrl(array $theme): ?string
    {
        return empty($theme['font']) ? null : 'https://fonts.googleapis.com/css2?family=' . $theme['font'] . '&display=swap';
    }

    /** The declarations, one per line. */
    protected static function declarations(array $theme, string $indent): string
    {
        $lines = [];

        foreach ($theme['vars'] ?? [] as $name => $value) {
            $lines[] = $indent . $name . ': ' . $value . ';';
        }

        if (! empty($theme['scheme'])) {
            $lines[] = $indent . 'color-scheme: ' . $theme['scheme'] . ';';
        }

        return implode("\n", $lines);
    }

    /**
     * The theme for a document's <head> — the canvas and the draft preview,
     * where it has not reached site.css yet. `html:root` so it stands over
     * the stylesheet's own `:root`, published block included.
     */
    public static function head(?array $theme): string
    {
        if (empty($theme['vars'])) {
            return '';
        }

        $font = self::fontUrl($theme);

        return ($font ? '<link rel="stylesheet" href="' . e($font) . '" data-studio-theme>' . "\n" : '')
            . '<style id="studio-theme">html:root {' . "\n" . self::declarations($theme, '    ') . "\n" . '}</style>';
    }

    /**
     * A stylesheet with the theme written into it: one block, last, so it
     * stands over the template's values. Without a theme it is taken out,
     * and a stylesheet that never had one comes back byte for byte.
     *
     * The type pairing's font is NOT imported here: the runtime hands this
     * file to Tailwind's browser build, which only brings Tailwind in for a
     * stylesheet with no `@import` of its own — see `link()`.
     */
    public static function write(string $css, ?array $theme): string
    {
        $css = preg_replace('/\R*' . preg_quote(self::START, '/') . '.*?' . preg_quote(self::END, '/') . '\R?/s', "\n", $css) ?? $css;

        if (empty($theme['vars'])) {
            return $css;
        }

        return rtrim($css) . "\n\n" . self::START . "\n:root {\n" . self::declarations($theme, '    ') . "\n}\n" . self::END . "\n";
    }

    /**
     * A layout file with the type pairing's font linked in its <head> — one
     * marked <link> as the last thing there, replaced when the pairing
     * changes and removed with it. A layout that never had one, or has no
     * </head>, comes back byte for byte.
     */
    public static function link(string $layout, ?array $theme): string
    {
        $layout = preg_replace('/^[ \t]*<link\b[^>]*\b' . self::FONT . '[^>]*>\R/mi', '', $layout) ?? $layout;
        $font = $theme ? self::fontUrl($theme) : null;

        if ($font === null || ! preg_match('/^([ \t]*)<\/head>/mi', $layout, $match, PREG_OFFSET_CAPTURE)) {
            return $layout;
        }

        $indent = $match[1][0] . '    ';
        $tag = $indent . '<link rel="stylesheet" href="' . e($font) . '" ' . self::FONT . '>' . "\n";

        return substr_replace($layout, $tag, $match[0][1], 0);
    }
}
