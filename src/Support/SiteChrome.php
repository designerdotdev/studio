<?php

namespace Designer\Studio\Support;

use Designer\Studio\Services\Storage\SiteRepository;
use Designer\Studio\Services\Templates\TemplateChrome;
use Illuminate\Support\HtmlString;

/**
 * The head and body chrome a site needs beyond its sections, for the
 * surfaces Studio renders itself: the editor canvas, the draft preview, and
 * the library thumbnails.
 *
 * It is read straight from the site's main layout file, so it is always
 * what the live site uses — edit the layout's fonts or a stylesheet in Code
 * mode and the canvas follows. The stylesheets are handed to Tailwind's
 * browser build as `<style type="text/tailwindcss">`, exactly as the
 * runtime's `@vite` does, which is what turns the site's `@theme` tokens
 * into real utilities (`bg-canvas`, `text-ink`).
 */
class SiteChrome
{
    /** @var array<string, array> memoised per layout file + data */
    protected array $cache = [];

    public function __construct(
        protected SiteRepository $site,
        protected TemplateChrome $chrome,
    ) {}

    /** Everything that belongs in <head>, in the order it must appear. */
    public function head(): HtmlString
    {
        $parts = array_filter([
            $this->parts()['head_html'],
            $this->themeStyle(),
            $this->scriptTags(),
        ]);

        return new HtmlString(implode("\n", $parts));
    }

    /**
     * The site's stylesheets, wrapped so Tailwind's browser build compiles
     * them. `@import` rules are hoisted out first: CSS requires them before
     * any other rule, and the font imports are what carry the typeface.
     */
    public function themeStyle(): string
    {
        $css = trim($this->parts()['theme_css']);

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

        // `@source` scans the filesystem at build time; there is no build
        // here, and leaving it in makes the browser build warn.
        $css = preg_replace('/@source\s+[^;]+;/i', '', $css) ?? $css;

        return trim($links . "\n" . '<style type="text/tailwindcss">' . "\n" . trim($css) . "\n" . '</style>');
    }

    public function scriptTags(): string
    {
        return collect($this->parts()['scripts'])
            ->map(fn ($src) => '<script src="' . e($src) . '" defer></script>')
            ->implode("\n");
    }

    /** The layout's own body classes, or the configured default. */
    public function bodyClass(): string
    {
        $class = trim($this->parts()['body_class']);

        return $class !== ''
            ? $class
            : (string) config('studio.iframe.body_class', 'min-h-screen w-full');
    }

    public function htmlClass(): string
    {
        return trim($this->parts()['html_class']);
    }

    /** The site's main layout: `layouts/main`, else the first layout file. */
    public static function layoutFile(): ?string
    {
        $main = SitePaths::components(SitePaths::LAYOUTS . '/main.blade.php');

        if (is_file($main)) {
            return $main;
        }

        foreach (glob(SitePaths::components(SitePaths::LAYOUTS . '/*.blade.php')) ?: [] as $file) {
            if (preg_match('/\{\{\s*\$slot\s*\}\}/', (string) file_get_contents($file))) {
                return $file;
            }
        }

        return null;
    }

    protected function parts(): array
    {
        $file = self::layoutFile();
        $data = $this->site->data();
        $key = ($file ? $file . '@' . filemtime($file) : '') . '|' . md5(json_encode($data));

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $empty = ['head_html' => '', 'scripts' => [], 'theme_css' => '', 'body_class' => '', 'html_class' => ''];

        if ($file === null) {
            return $this->cache[$key] = $empty;
        }

        return $this->cache[$key] = $this->chrome->extract(
            (string) file_get_contents($file),
            $data,
            fn (string $entry) => str_starts_with($entry, 'resources/') ? base_path($entry) : null
        ) + $empty;
    }
}
