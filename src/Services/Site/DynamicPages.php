<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\SiteUrls;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The pages a collection makes: every `[collection.field].blade.php` under
 * views/pages, with one entry per row that page answers for.
 *
 * `blog/[posts.slug].blade.php` is one file and as many pages as there are
 * posts. The page switcher and the Pages panel list those under the page
 * that shares the folder's address (`/blog`), and the editor shows one on
 * the canvas — rendered by CodePagePreview, the way the draft preview
 * renders it.
 */
class DynamicPages
{
    /** What a row is called in a list, first one it has */
    protected const TITLE_KEYS = ['title', 'name', 'headline', 'company', 'label'];

    public function __construct(
        protected CollectionRepository $collections,
        protected PageRepository $pages,
    ) {}

    /**
     * One group per dynamic page file, in address order.
     *
     * @return list<array{folder: string, path: string, parent: ?string, collection: string, name: string, label: string, field: string, file: string, entries: list<array{id: ?string, title: string, path: string}>}>
     */
    public function groups(): array
    {
        $root = SitePaths::pages();

        if (! is_dir($root)) {
            return [];
        }

        $collections = [];

        foreach ($this->collections->all() as $name => $doc) {
            if (is_string($doc['source'] ?? null)) {
                $collections[$doc['source']] = $doc + ['name' => $name];
            }
        }

        // A folder belongs to the page served at its address
        $home = SiteUrls::homeSlug();
        $parents = [];

        foreach ($this->pages->all() as $page) {
            if ($page->slug !== $home) {
                $parents[$page->slug] = $page->slug;
            }
        }

        $groups = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! preg_match('/^\[([A-Za-z_]\w*)\.([A-Za-z_]\w*)\]\.blade\.php$/', $file->getFilename(), $m)) {
                continue;
            }

            $doc = $collections[$m[1]] ?? null;

            if ($doc === null) {
                continue;
            }

            $folder = trim(str_replace('\\', '/', substr($file->getPath(), strlen($root))), '/');
            $entries = [];

            foreach ($doc['rows'] ?? [] as $row) {
                $value = $row[$m[2]] ?? null;

                // The preview route's own idea of a path segment
                if (! is_scalar($value) || ! preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]*$/', (string) $value)) {
                    continue;
                }

                $entries[] = [
                    'id' => isset($row['id']) && is_scalar($row['id']) ? (string) $row['id'] : null,
                    'title' => $this->title($row, (string) $value),
                    'path' => '/' . ($folder === '' ? '' : $folder . '/') . $value,
                ];
            }

            if ($entries === []) {
                continue;
            }

            $groups[] = [
                'folder' => $folder,
                'path' => '/' . $folder,
                'parent' => $parents[$folder] ?? null,
                'collection' => $m[1],
                'name' => $doc['name'],
                'label' => (string) ($doc['title'] ?? $doc['name']),
                'field' => $m[2],
                'file' => SitePaths::relative($file->getPathname()),
                'entries' => $entries,
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['folder'], $b['folder']));

        return $groups;
    }

    /**
     * The entry served at a path, with the group it came from.
     *
     * @return array{id: ?string, title: string, path: string, group: array}|null
     */
    public function find(string $path): ?array
    {
        $path = '/' . trim($path, '/');

        foreach ($this->groups() as $group) {
            foreach ($group['entries'] as $entry) {
                if ($entry['path'] === $path) {
                    unset($group['entries']);

                    return $entry + ['group' => $group];
                }
            }
        }

        return null;
    }

    protected function title(array $row, string $fallback): string
    {
        foreach (self::TITLE_KEYS as $key) {
            if (is_string($row[$key] ?? null) && trim($row[$key]) !== '') {
                return trim($row[$key]);
            }
        }

        return $fallback;
    }
}
