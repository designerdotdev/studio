<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\CollectionBinder;
use Designer\Studio\Services\SectionRenderer;
use Designer\Studio\Services\Storage\BlockRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A picture of one section as the page shows it: the section alone, with
 * the values it has now, at desktop width.
 *
 * The section is rendered here, to a string — the same document the picker
 * thumbnails use — and handed to a headless browser (`bin/section-snapshot.mjs`,
 * Playwright from the app's own node_modules), which answers the one request
 * for the document itself and lets everything the section asks for (styles,
 * fonts, images) come from the running site. So the browser needs no session
 * and no route of its own.
 *
 * Playwright is the app's dependency, not the package's: without it (or
 * without Node) the feature is off.
 */
class SectionSnapshot
{
    public const WIDTH = 1440;

    protected string|false|null $node = null;

    public function __construct(
        protected PageRepository $pages,
        protected LayoutRepository $layouts,
        protected BlockRepository $blocks,
        protected ComponentRepository $components,
        protected CollectionBinder $binder,
        protected SectionRenderer $renderer,
    ) {}

    public function available(): bool
    {
        return $this->node() !== null && is_file(base_path('node_modules/playwright/package.json'));
    }

    /**
     * The section instance on a page (or in that page's layout), as a
     * standalone document.
     *
     * @return ?array{html: string, title: string, ref: string}
     */
    public function document(string $pageSlug, string $sectionId): ?array
    {
        if (!$page = $this->pages->find($pageSlug)) {
            return null;
        }

        $regions = $this->layouts->regions($page->layout_ref);

        foreach ([...$regions['before'], ...$page->components, ...$regions['after']] as $instance) {
            if (($instance['id'] ?? null) !== $sectionId) {
                continue;
            }

            if (!empty($instance['block_ref'])) {
                $instance = $this->blocks->hydrate([$instance])[0] ?? null;
            }

            $component = $instance ? $this->components->find($instance['component_ref'] ?? '') : null;

            if (!$component) {
                return null;
            }

            $variables = $this->binder->apply(
                $component->resolveVariables($instance['variables'] ?? []),
                $instance['bindings'] ?? []
            );

            return [
                'html' => view('studio::preview', [
                    'title' => $component->title,
                    'sections' => [$this->renderer->renderHtml($component->html, $variables, $component->name)],
                ])->render(),
                'title' => $component->title,
                'ref' => $component->name,
            ];
        }

        return null;
    }

    /**
     * Draw a document to a PNG.
     *
     * @param  string  $origin  the running site (scheme and host), where the document's own URLs resolve
     *
     * @throws \RuntimeException with a message worth showing
     */
    public function capture(string $html, string $origin, string $output): void
    {
        if (!$this->available()) {
            throw new \RuntimeException('A snapshot needs Node and Playwright in this app (`npm i -D playwright`).');
        }

        $input = $output . '.html';
        file_put_contents($input, $html);

        try {
            $process = new Process(
                [$this->node(), dirname(__DIR__, 3) . '/bin/section-snapshot.mjs', $input, rtrim($origin, '/'), $output, (string) self::WIDTH],
                base_path(),
                ['PATH' => dirname($this->node()) . ':' . (getenv('PATH') ?: '') . ':/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin'],
            );
            $process->setTimeout(60);
            $process->run();
        } catch (\Throwable $e) {
            throw new \RuntimeException('The snapshot took too long. Try again.');
        } finally {
            @unlink($input);
        }

        if (!$process->isSuccessful() || !@getimagesize($output)) {
            throw new \RuntimeException($this->failure($process->getErrorOutput()));
        }
    }

    protected function failure(string $stderr): string
    {
        if (str_contains($stderr, "Executable doesn't exist") || str_contains($stderr, 'playwright install')) {
            return 'Playwright has no browser yet. Run `npx playwright install chromium` in this app, then try again.';
        }

        if (str_contains($stderr, 'EMPTY')) {
            return 'This section draws nothing on its own, so there is no picture to make variations of.';
        }

        $stderr = trim(preg_replace('/\s+/', ' ', $stderr));

        return 'Could not take a picture of this section' . ($stderr !== '' ? ': ' . \Illuminate\Support\Str::limit($stderr, 200) : '.');
    }

    /** PHP-FPM's PATH rarely has Node on it; see Engines::nodeBins() */
    protected function node(): ?string
    {
        if ($this->node !== null) {
            return $this->node ?: null;
        }

        $configured = config('studio.assistant.node');

        if (is_string($configured) && $configured !== '') {
            return ($this->node = is_executable($configured) ? $configured : false) ?: null;
        }

        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['dir'] ?? '') : '');
        $managed = [];

        foreach (['/Library/Application Support/Herd/config/nvm/versions/node', '/.nvm/versions/node', '/.local/share/fnm/node-versions'] as $root) {
            $found = glob($home . $root . '/*/{bin,installation/bin}/node', GLOB_BRACE) ?: [];
            usort($found, fn ($a, $b) => version_compare(basename(dirname($b, str_contains($b, '/installation/') ? 3 : 2)), basename(dirname($a, str_contains($a, '/installation/') ? 3 : 2))));
            $managed = [...$managed, ...$found];
        }

        $found = (new ExecutableFinder)->find('node', null, ['/opt/homebrew/bin', '/usr/local/bin', $home . '/.volta/bin']);

        foreach ([$found, ...$managed] as $path) {
            if ($path && is_executable($path)) {
                return $this->node = $path;
            }
        }

        $this->node = false;

        return null;
    }
}
