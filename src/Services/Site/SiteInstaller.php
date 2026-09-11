<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Templates\TemplateAssets;
use Designer\Studio\Services\Templates\TemplateSync;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\WelcomeRoutePruner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Installs a site template into the application.
 *
 * A template repository holds a whole site under `files/`: a `resources`
 * tree (pages, components, data, CSS) and a `public` tree (images, scripts,
 * favicon). Installing copies exactly those two trees and nothing else:
 *
 *   files/resources/**  →  resources/designer/**
 *   files/public/**     →  public/designer/**
 *
 * Because the public files move under /designer, every URL that points at
 * one (`/images/hero.jpg`) is moved with it, and the layout's
 * `@vite('resources/css/…')` entries are pointed at resources/designer.
 * Then the runtime provider is installed so the app serves the site, and
 * Studio reads the new files into its documents.
 */
class SiteInstaller
{
    /** Files whose text may carry URLs to the template's public files. */
    protected const TEXT_EXTENSIONS = ['php', 'html', 'css', 'js', 'mjs', 'json', 'yml', 'yaml', 'md', 'txt', 'svg', 'xml', 'webmanifest'];

    public function __construct(
        protected TemplateSync $sync,
        protected SiteMirror $mirror,
        protected RuntimeInstaller $runtime,
        protected ComponentRepository $components,
    ) {}

    /**
     * @param  bool  $replace  Delete an already-installed site first
     * @return array{template: string, pages: string[], code_pages: string[], sections: int, files: int, runtime: array, home_claimed: bool}
     */
    public function install(string $slug, bool $replace = false): array
    {
        if (SitePaths::installed() && !$replace) {
            throw new RuntimeException('A site is already installed in ' . SitePaths::relative(SitePaths::resources()) . '. Remove it first, or install with --force to replace it.');
        }

        $dir = $this->sync->ensure($slug);

        if ($problem = $this->sync->validate($dir)) {
            throw new RuntimeException("Template [{$slug}] can't be installed: {$problem}.");
        }

        if ($replace) {
            File::deleteDirectory(SitePaths::resources());
            File::deleteDirectory(SitePaths::public());
        }

        $assets = new TemplateAssets(SitePaths::url());
        $assets->scan($dir . '/files/public');

        $files = $this->copy($dir . '/files/resources', SitePaths::resources(), $assets, rewriteVite: true)
            + $this->copy($dir . '/files/public', SitePaths::public(), $assets, rewriteVite: false);

        SiteManifest::write($this->manifest($slug, $dir));

        $runtime = $this->runtime->install();

        $this->mirror->rebuild();

        $state = $this->mirror->state();

        return [
            'template' => $slug,
            'pages' => array_keys($state['pages'] ?? []),
            'code_pages' => $state['code_pages'] ?? [],
            'sections' => $this->components->all()->count(),
            'files' => $files,
            'runtime' => $runtime,
            'home_claimed' => app(WelcomeRoutePruner::class)->claimHome(),
        ];
    }

    /**
     * Copy a tree, moving URLs as it goes. Returns the number of files.
     */
    protected function copy(string $from, string $to, TemplateAssets $assets, bool $rewriteVite): int
    {
        if (!is_dir($from)) {
            return 0;
        }

        $count = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($from) + 1);

            // Dotfiles (.DS_Store, .gitkeep, a stray .env) never come across
            if (preg_match('#(^|/)\.#', $relative)) {
                continue;
            }

            $target = $to . '/' . $relative;

            if ($file->isDir()) {
                File::ensureDirectoryExists($target);

                continue;
            }

            File::ensureDirectoryExists(dirname($target));

            if (in_array(strtolower($file->getExtension()), self::TEXT_EXTENSIONS, true)) {
                $contents = $assets->rewrite((string) file_get_contents($file->getPathname()));

                if ($rewriteVite && str_ends_with($relative, '.blade.php')) {
                    $contents = $this->rewriteVite($contents);
                }

                File::put($target, $contents);
            } else {
                File::copy($file->getPathname(), $target);
            }

            $count++;
        }

        return $count;
    }

    /**
     * `@vite(['resources/css/site.css'])` → `@vite(['resources/designer/css/site.css'])`:
     * the entry names a file relative to the app, and in the app the
     * template's resources live under resources/designer.
     */
    protected function rewriteVite(string $contents): string
    {
        return preg_replace_callback(
            '/@vite\s*\((.*?)\)/s',
            fn (array $m) => '@vite(' . preg_replace('/([\'"])resources\/(?!' . SitePaths::FOLDER . '\/)/', '$1resources/' . SitePaths::FOLDER . '/', $m[1]) . ')',
            $contents
        ) ?? $contents;
    }

    /**
     * The first designer.json: which template this is, and the page names
     * and order its template.json lists (the editor's Pages panel reads
     * them; a page file only knows its document title).
     */
    protected function manifest(string $slug, string $dir): array
    {
        $template = $this->sync->manifest($slug) ?? [];
        $names = array_values(array_filter((array) ($template['pages'] ?? []), 'is_string'));
        $home = is_file(SitePaths::pages('home.blade.php')) ? 'index' : 'home';
        $pages = [];

        foreach (glob(SitePaths::pages('*.blade.php')) ?: [] as $file) {
            $base = basename($file, '.blade.php');

            if ($base === '404' || !preg_match('/^[a-z0-9-]+$/', $base)) {
                continue;
            }

            $key = $base === 'index' ? $home : $base;
            $position = null;

            foreach ($names as $i => $name) {
                if (Str::slug($name) === $base || ($base === 'index' && in_array(Str::slug($name), ['home', 'index'], true))) {
                    $position = $i;
                    $pages[$key] = ['title' => $name, 'order' => $i];
                    break;
                }
            }

            if ($position === null) {
                $pages[$key] = ['title' => $base === 'index' ? 'Home' : Str::headline($base)];
            }
        }

        return [
            'template' => $slug,
            'home' => $home,
            'pages' => $pages,
            'layouts' => [],
            'blocks' => [],
        ];
    }
}
