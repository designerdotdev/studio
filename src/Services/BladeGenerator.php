<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\DataTransferObjects\PageData;
use Illuminate\Support\Facades\File;

class BladeGenerator
{
    public function __construct(
        protected PageRepository $pages,
        protected ComponentRepository $components
    ) {}

    protected function getOutputPath(): string
    {
        return config('studio.output_path', resource_path('views/designer'));
    }

    /**
     * Generate Blade file for a single page
     */
    public function generatePage(string $pageSlug): string
    {
        $page = $this->pages->find($pageSlug);

        if (!$page) {
            throw new \RuntimeException("Page not found: {$pageSlug}");
        }

        $blade = $this->buildPageBlade($page);
        $outputPath = $this->getOutputPath() . "/{$page->slug}.blade.php";

        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($outputPath, $blade);

        return $outputPath;
    }

    /**
     * Generate Blade files for all pages
     */
    public function generateAll(): array
    {
        $generated = [];

        foreach ($this->pages->all() as $page) {
            $generated[] = $this->generatePage($page->slug);
        }

        return $generated;
    }

    /**
     * Build the Blade content for a page.
     *
     * With no layout configured the export is a fully standalone HTML
     * document (works in any app, zero setup). When `studio.default_layout`
     * (or the page's own layout) is set, the sections are wrapped in that
     * Blade component instead.
     */
    protected function buildPageBlade(PageData $page): string
    {
        $layout = $page->layout ?: config('studio.default_layout');

        return $layout
            ? $this->buildLayoutWrappedBlade($page, $layout)
            : $this->buildStandaloneBlade($page);
    }

    protected function buildLayoutWrappedBlade(PageData $page, string $layout): string
    {
        $sections = [];
        $sections[] = "<x-{$layout}>";

        // Add SEO title slot if available
        if (!empty($page->meta['seo_title'])) {
            $seoTitle = e($page->meta['seo_title']);
            $sections[] = "    <x-slot:title>";
            $sections[] = "        {$seoTitle}";
            $sections[] = "    </x-slot>";
        }

        foreach ($this->renderSections($page, indent: '    ') as $block) {
            $sections[] = $block;
        }

        $sections[] = "";
        $sections[] = "</x-{$layout}>";

        return $this->withGeneratedHeader($page, implode("\n", $sections));
    }

    protected function buildStandaloneBlade(PageData $page): string
    {
        $meta = $page->meta;
        $seoTitle = e($meta['seo_title'] ?? $page->title);
        $seoDescription = e($meta['seo_description'] ?? $page->description ?? '');

        $head = [
            '<!DOCTYPE html>',
            '<html lang="{{ str_replace(\'_\', \'-\', app()->getLocale()) }}">',
            '<head>',
            '    <meta charset="utf-8">',
            '    <meta name="viewport" content="width=device-width, initial-scale=1">',
            "    <title>{$seoTitle}</title>",
        ];

        if ($seoDescription !== '') {
            $head[] = "    <meta name=\"description\" content=\"{$seoDescription}\">";
        }

        foreach ($this->headTags($page) as $tag) {
            $head[] = '    ' . $tag;
        }

        if (config('studio.iframe.tailwind_cdn', true)) {
            $head[] = '    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';
        }

        if (config('studio.iframe.alpine_cdn', true)) {
            $head[] = '    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>';
        }

        $head[] = '    <style>[x-cloak] { display: none !important; }</style>';
        $head[] = '</head>';
        $head[] = '<body class="' . e(config('studio.iframe.body_class', 'min-h-screen w-full')) . '">';

        $sections = $head;

        foreach ($this->renderSections($page, indent: '    ') as $block) {
            $sections[] = $block;
        }

        $sections[] = '';
        $sections[] = '</body>';
        $sections[] = '</html>';

        return $this->withGeneratedHeader($page, implode("\n", $sections));
    }

    /**
     * The page's section instances with its layout's header/footer
     * sections merged around them.
     */
    protected function mergedInstances(PageData $page): array
    {
        $regions = app(\Designer\Studio\Services\Storage\LayoutRepository::class)
            ->regions($page->layout_ref);

        return app(\Designer\Studio\Services\Storage\BlockRepository::class)->hydrate([
            ...$regions['before'],
            ...collect($page->components)->sortBy('order')->values()->all(),
            ...$regions['after'],
        ]);
    }

    /**
     * Render every visible section of a page (including its layout's
     * sections) as indented Blade blocks.
     *
     * @return array<int, string>
     */
    protected function renderSections(PageData $page, string $indent = ''): array
    {
        $blocks = [];

        $sortedComponents = $this->mergedInstances($page);

        foreach ($sortedComponents as $instance) {
            if (!empty($instance['hidden'])) {
                continue;
            }

            $component = $this->components->find($instance['component_ref']);

            if (!$component) {
                $blocks[] = "{$indent}{{-- Component not found: {$instance['component_ref']} --}}";
                continue;
            }

            $blocks[] = "";
            $blocks[] = "{$indent}{{-- {$component->title} --}}";

            // Render the component HTML with the fully resolved variable set
            $rendered = $this->renderComponentWithVariables(
                $component->html,
                $component->resolveVariables($instance['variables'] ?? [])
            );

            $blocks[] = collect(explode("\n", $rendered))
                ->map(fn($line) => $indent . $line)
                ->implode("\n");
        }

        return $blocks;
    }

    /**
     * The SEO/social/branding head tags for exports — mirrors what
     * resources/views/page.blade.php renders for live pages.
     *
     * @return string[]
     */
    protected function headTags(PageData $page): array
    {
        $meta = $page->meta;
        $seoTitle = e($meta['seo_title'] ?? $page->title);
        $seoDescription = e($meta['seo_description'] ?? $page->description ?? '');
        $canonical = e($meta['canonical_url'] ?? '');
        $ogImage = e($meta['og_image'] ?? '');

        $tags = [];

        $tags[] = $canonical !== ''
            ? "<link rel=\"canonical\" href=\"{$canonical}\">"
            : '<link rel="canonical" href="{{ url()->current() }}">';

        if (!empty($meta['seo_keywords'])) {
            $tags[] = '<meta name="keywords" content="' . e($meta['seo_keywords']) . '">';
        }

        if (!empty($meta['noindex']) || !empty($meta['nofollow'])) {
            $robots = (!empty($meta['noindex']) ? 'noindex' : 'index')
                . ', '
                . (!empty($meta['nofollow']) ? 'nofollow' : 'follow');
            $tags[] = "<meta name=\"robots\" content=\"{$robots}\">";
        }

        $tags[] = "<meta property=\"og:title\" content=\"{$seoTitle}\">";

        if ($seoDescription !== '') {
            $tags[] = "<meta property=\"og:description\" content=\"{$seoDescription}\">";
        }

        $tags[] = '<meta property="og:type" content="' . e($meta['og_type'] ?? 'website') . '">';
        $tags[] = $canonical !== ''
            ? "<meta property=\"og:url\" content=\"{$canonical}\">"
            : '<meta property="og:url" content="{{ url()->current() }}">';
        $tags[] = !empty($meta['og_site_name'])
            ? '<meta property="og:site_name" content="' . e($meta['og_site_name']) . '">'
            : '<meta property="og:site_name" content="{{ config(\'app.name\') }}">';
        $tags[] = !empty($meta['og_locale'])
            ? '<meta property="og:locale" content="' . e($meta['og_locale']) . '">'
            : '<meta property="og:locale" content="{{ str_replace(\'-\', \'_\', app()->getLocale()) }}">';

        if ($ogImage !== '') {
            $tags[] = "<meta property=\"og:image\" content=\"{$ogImage}\">";

            if (!empty($meta['og_image_alt'])) {
                $tags[] = '<meta property="og:image:alt" content="' . e($meta['og_image_alt']) . '">';
            }
        }

        $twitterCard = $meta['twitter_card'] ?? ($ogImage !== '' ? 'summary_large_image' : 'summary');
        $tags[] = '<meta name="twitter:card" content="' . e($twitterCard) . '">';

        if (!empty($meta['twitter_site'])) {
            $tags[] = '<meta name="twitter:site" content="' . e($meta['twitter_site']) . '">';
        }

        if (!empty($meta['twitter_creator'])) {
            $tags[] = '<meta name="twitter:creator" content="' . e($meta['twitter_creator']) . '">';
        }

        if (!empty($meta['favicon'])) {
            $tags[] = '<link rel="icon" href="' . e($meta['favicon']) . '">';
        }

        if (!empty($meta['theme_color'])) {
            $tags[] = '<meta name="theme-color" content="' . e($meta['theme_color']) . '">';
        }

        if (!empty($meta['json_ld']) && ($decoded = json_decode($meta['json_ld'])) !== null) {
            $tags[] = '<script type="application/ld+json">'
                . json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . '</script>';
        }

        if (!empty($meta['head_html'])) {
            $tags[] = $meta['head_html'];
        }

        return $tags;
    }

    protected function withGeneratedHeader(PageData $page, string $content): string
    {
        $header = [
            "{{--",
            "    Generated by Designer Studio",
            "    Page: {$page->title}",
            "    Generated at: " . now()->toIso8601String(),
            "    ",
            "    WARNING: Manual edits will be overwritten when regenerated.",
            "--}}",
            "",
        ];

        return implode("\n", $header) . $content;
    }

    /**
     * Render component HTML, converting {{ $var ?? 'default' }} to static values.
     * For array variables (repeaters), injects @php preamble and preserves @foreach directives.
     */
    protected function renderComponentWithVariables(string $html, array $variables): string
    {
        // Statically evaluate @if blocks over known variables first, so
        // toggle fields collapse into clean markup instead of leaving
        // conditions over undefined variables in the generated file.
        $html = $this->evaluateConditionals($html, $variables);

        $preamble = '';
        $arrayVars = [];

        // Identify array variables and build @php preamble
        foreach ($variables as $varName => $value) {
            if (is_array($value)) {
                $arrayVars[$varName] = true;
                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $preamble .= "@php \${$varName} = json_decode('" . addcslashes($json, "'") . "', true); @endphp\n";
            }
        }

        // Replace scalar Blade variable syntax with actual values.
        // Two passes so 'single' and "double" quoted defaults can each
        // contain the other quote character (e.g. "What we've built").
        $result = $html;
        foreach (['/{{\s*\$(\w+)\s*\?\?\s*\'([^\']*)\'\s*}}/', '/{{\s*\$(\w+)\s*\?\?\s*"([^"]*)"\s*}}/'] as $pattern) {
            $result = preg_replace_callback($pattern, function ($matches) use ($variables, $arrayVars) {
                $varName = $matches[1];
                $default = $matches[2];

                // Skip array variables — they're handled by @foreach in the template
                if (isset($arrayVars[$varName])) {
                    return $matches[0];
                }

                $value = $variables[$varName] ?? $default;

                // Escape for HTML output
                return e($value);
            }, $result);
        }

        // Also handle {{ $varName }} without defaults for scalar vars
        $result = preg_replace_callback('/{{\s*\$(\w+)\s*}}/', function ($matches) use ($variables, $arrayVars) {
            $varName = $matches[1];

            if (isset($arrayVars[$varName])) {
                return $matches[0];
            }

            $value = $variables[$varName] ?? '';
            return e($value);
        }, $result);

        // Handle {!! $varName !!} and {!! $varName ?? 'default' !!} for scalar vars
        $result = preg_replace_callback('/{!!\s*\$(\w+)\s*(?:\?\?\s*[\'"]([^\'"]*)[\'"])?\s*!!}/', function ($matches) use ($variables, $arrayVars) {
            $varName = $matches[1];

            if (isset($arrayVars[$varName])) {
                return $matches[0];
            }

            $default = $matches[2] ?? '';
            return $variables[$varName] ?? $default;
        }, $result);

        if ($preamble) {
            return $preamble . $result;
        }

        return $result;
    }

    /**
     * Statically evaluate simple @if / @else / @endif blocks against the
     * known variable set. Only handles conditions of the form
     * `@if($var)` or `@if($var ?? false)` where $var is a page-level
     * variable — conditions referencing loop items (e.g. `$item['x']`)
     * are left untouched for runtime evaluation.
     */
    protected function evaluateConditionals(string $html, array $variables): string
    {
        // Loop item names must never be statically evaluated
        $reserved = ['loop' => true];
        if (preg_match_all('/@foreach\s*\(\s*\$\w+\s+as\s+\$(\w+)\s*\)/', $html, $matches)) {
            foreach ($matches[1] as $itemName) {
                $reserved[$itemName] = true;
            }
        }

        $pattern = '/@if\s*\(\s*\$(\w+)(?:\s*\?\?\s*(false|true|\'[^\']*\'|"[^"]*"))?\s*\)([\s\S]*?)@endif/';

        return preg_replace_callback($pattern, function ($matches) use ($variables, $reserved) {
            $varName = $matches[1];

            if (isset($reserved[$varName])) {
                return $matches[0];
            }

            $fallback = false;
            if (isset($matches[2]) && $matches[2] !== '') {
                $raw = $matches[2];
                $fallback = match (true) {
                    $raw === 'false' => false,
                    $raw === 'true' => true,
                    default => trim($raw, '\'"'),
                };
            }

            $value = array_key_exists($varName, $variables) ? $variables[$varName] : $fallback;

            $truthy = is_array($value)
                ? count($value) > 0
                : !($value === false || $value === null || $value === '' || $value === '0' || $value === 'false' || $value === 0);

            $parts = explode('@else', $matches[3], 2);
            $ifContent = $parts[0];
            $elseContent = $parts[1] ?? '';

            return $truthy ? $ifContent : $elseContent;
        }, $html) ?? $html;
    }

    /**
     * Generate component partials (optional, for reusable includes)
     */
    public function generateComponentPartials(): array
    {
        $generated = [];
        $partialsPath = $this->getOutputPath() . '/partials';

        if (!File::isDirectory($partialsPath)) {
            File::makeDirectory($partialsPath, 0755, true);
        }

        foreach ($this->components->all() as $component) {
            $filename = "_{$component->name}.blade.php";
            $filepath = "{$partialsPath}/{$filename}";

            $content = $this->buildComponentPartial($component);
            File::put($filepath, $content);

            $generated[] = $filepath;
        }

        return $generated;
    }

    protected function buildComponentPartial($component): string
    {
        $header = [
            "{{--",
            "    Component: {$component->title}",
            "    Usage: @include('designer.partials._{$component->name}', ['var' => 'value'])",
            "--}}",
            "",
        ];

        return implode("\n", $header) . $component->html;
    }

    /**
     * Remove generated Blade files (for clean uninstall).
     *
     * Only exported pages (root-level *.blade.php) and generated partials
     * are removed — section design sources living in category
     * subdirectories are never touched.
     */
    public function purge(): bool
    {
        $outputPath = $this->getOutputPath();

        if (!File::isDirectory($outputPath)) {
            return true;
        }

        foreach (File::files($outputPath) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                File::delete($file->getPathname());
            }
        }

        if (File::isDirectory($outputPath . '/partials')) {
            File::deleteDirectory($outputPath . '/partials');
        }

        return true;
    }
}
