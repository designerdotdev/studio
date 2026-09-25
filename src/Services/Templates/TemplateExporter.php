<?php

namespace Designer\Studio\Services\Templates;

use Designer\Studio\Services\Site\SiteInstaller;
use Designer\Studio\Services\Site\SiteManifest;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\TemplateLink;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Writes the installed site back into the linked template folder — the
 * inverse of SiteInstaller.
 *
 *   resources/designer/**  →  files/resources/**
 *   public/designer/**     →  files/public/**
 *
 * Every transformation the installer made is undone on the way: public
 * URLs move from `/designer/images/…` back to `/images/…`, `@vite` entries
 * back to `resources/…`, and `designer.json` (Studio's own settings) stays
 * behind. Files the site no longer has are removed from `files/`, and
 * nothing outside `files/` is touched except the `pages` list in
 * template.json, so a page added in Studio keeps its title on the next
 * import.
 *
 * An export refuses to run when `files/` changed since the last export —
 * an edit made in the template folder by hand would otherwise be lost.
 */
class TemplateExporter
{
    public function __construct(protected TemplateLink $link) {}

    /**
     * @param  bool  $force   Overwrite a files/ tree that changed since the last export
     * @param  bool  $dryRun  Count what would change without writing anything
     * @return array{dir: string, written: int, deleted: int, pages: bool}
     */
    public function export(bool $force = false, bool $dryRun = false): array
    {
        $dir = $this->link->directory();

        if ($dir === null) {
            throw new RuntimeException('No template is linked. Run studio:templates:link first.');
        }

        if (!SitePaths::installed()) {
            throw new RuntimeException('No site is installed in ' . SitePaths::relative(SitePaths::resources()) . ' — nothing to export.');
        }

        $current = $this->fingerprint($dir);
        $baseline = $this->link->exported();

        if (!$force && !$dryRun && $baseline !== null && $baseline !== $current) {
            $message = basename($dir) . '/files changed outside Studio since the last export, so the export was skipped. '
                . 'Re-import the template (studio:templates:import ' . basename($dir) . ' --force) or run studio:templates:export --force to overwrite it.';

            $this->link->block($message);

            throw new RuntimeException($message);
        }

        $assets = new TemplateAssets(SitePaths::url());
        $assets->scan(SitePaths::public());

        $kept = ['resources' => [], 'public' => []];

        $write = !$dryRun;

        $written = $this->copy(SitePaths::resources(), $dir . '/files/resources', $assets, restoreVite: true, kept: $kept['resources'], write: $write)
            + $this->copy(SitePaths::public(), $dir . '/files/public', $assets, restoreVite: false, kept: $kept['public'], write: $write);

        $deleted = $this->prune($dir . '/files/resources', $kept['resources'], $write)
            + $this->prune($dir . '/files/public', $kept['public'], $write);

        $pages = $this->refreshPages($dir, $write);

        if ($write) {
            $this->link->baseline($this->fingerprint($dir));
        }

        return ['dir' => $dir, 'written' => $written, 'deleted' => $deleted, 'pages' => $pages];
    }

    /**
     * One hash for everything under a template's files/ (dotfiles aside),
     * so an export can tell whether the folder changed behind its back.
     */
    public function fingerprint(string $dir): string
    {
        $entries = [];

        foreach ($this->files($dir . '/files') as $relative => $absolute) {
            $entries[] = $relative . ':' . md5_file($absolute);
        }

        sort($entries);

        return md5(implode("\n", $entries));
    }

    /**
     * Copy a tree, restoring the template's own URLs. Returns the number of
     * files whose content changed; untouched files keep their mtime.
     *
     * @param  string[]  $kept  filled with every relative path that belongs in the target
     */
    protected function copy(string $from, string $to, TemplateAssets $assets, bool $restoreVite, array &$kept, bool $write = true): int
    {
        $count = 0;

        foreach ($this->files($from) as $relative => $absolute) {
            // Studio's own settings never enter the template
            if ($relative === SitePaths::MANIFEST) {
                continue;
            }

            $kept[] = $relative;
            $target = $to . '/' . $relative;
            $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

            if (in_array($extension, SiteInstaller::TEXT_EXTENSIONS, true)) {
                $contents = $assets->restore((string) file_get_contents($absolute));

                if ($restoreVite && str_ends_with($relative, '.blade.php')) {
                    $contents = $this->restoreVite($contents);
                }

                if (is_file($target) && file_get_contents($target) === $contents) {
                    continue;
                }

                if ($write) {
                    File::ensureDirectoryExists(dirname($target));
                    File::put($target, $contents);
                }
            } else {
                if (is_file($target) && filesize($target) === filesize($absolute) && md5_file($target) === md5_file($absolute)) {
                    continue;
                }

                if ($write) {
                    File::ensureDirectoryExists(dirname($target));
                    File::copy($absolute, $target);
                }
            }

            $count++;
        }

        return $count;
    }

    /** Remove files under a target tree that the site no longer has, then any folders left empty. */
    protected function prune(string $dir, array $kept, bool $write = true): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $kept = array_flip($kept);
        $count = 0;

        foreach ($this->files($dir) as $relative => $absolute) {
            if (!isset($kept[$relative])) {
                if ($write) {
                    File::delete($absolute);
                }

                $count++;
            }
        }

        if (!$write) {
            return $count;
        }

        $directories = iterator_to_array(new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ));

        foreach ($directories as $entry) {
            if ($entry->isDir() && (scandir($entry->getPathname()) ?: []) === ['.', '..']) {
                @rmdir($entry->getPathname());
            }
        }

        return $count;
    }

    /**
     * `@vite(['resources/designer/css/site.css'])` → `@vite(['resources/css/site.css'])`
     */
    protected function restoreVite(string $contents): string
    {
        return preg_replace_callback(
            '/@vite\s*\((.*?)\)/s',
            fn (array $m) => '@vite(' . preg_replace('/([\'"])resources\/' . SitePaths::FOLDER . '\//', '$1resources/', $m[1]) . ')',
            $contents
        ) ?? $contents;
    }

    /**
     * Keep template.json's `pages` (the titles the installer seeds
     * designer.json from) in step with the pages the site has now.
     * Every other key is left as it is. Returns whether the file changed.
     */
    protected function refreshPages(string $dir, bool $write = true): bool
    {
        $path = $dir . '/template.json';
        $template = json_decode((string) file_get_contents($path), true);

        if (!is_array($template)) {
            return false;
        }

        $pages = collect(SiteManifest::read()['pages'])
            ->filter(fn ($page) => isset($page['title']) && is_string($page['title']) && $page['title'] !== '')
            ->sortBy(fn ($page) => [$page['order'] ?? PHP_INT_MAX, mb_strtolower($page['title'])])
            ->pluck('title')
            ->values()
            ->all();

        if ($pages === [] || $pages === ($template['pages'] ?? null)) {
            return false;
        }

        if (!$write) {
            return true;
        }

        $template['pages'] = $pages;

        File::put($path, json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return true;
    }

    /**
     * Every file under a folder, keyed by its relative path, dotfiles left
     * out (they never install, so they never export or prune).
     *
     * @return array<string, string>
     */
    protected function files(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1);

            if (preg_match('#(^|/)\.#', $relative)) {
                continue;
            }

            $files[$relative] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }
}
