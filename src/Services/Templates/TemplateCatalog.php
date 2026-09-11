<?php

namespace Designer\Studio\Services\Templates;

use Illuminate\Support\Str;

/**
 * The templates a site can start from: the entries of
 * `studio.templates.catalog`, each a whole site in its own git repository
 * (the DevDojo `site-templates` format).
 *
 * A template does not have to be downloaded to be offered — the picker
 * shows the catalog's own name and description, with the thumbnail served
 * from the repository — and it is cloned the moment someone picks it.
 */
class TemplateCatalog
{
    public function __construct(
        protected TemplateSync $sync,
    ) {}

    /**
     * Every catalogued template, in catalog order.
     *
     * @return array<string, array{name: string, title: string, description: string, category: string, pages: int, preview: string}>
     */
    public function all(): array
    {
        $entries = [];

        foreach ($this->sync->catalog() as $slug => $url) {
            $manifest = $this->sync->manifest($slug) ?? [];
            $declared = $this->sync->catalogEntry($slug);

            $entries[$slug] = [
                'name' => $slug,
                'title' => $manifest['name'] ?? $declared['name'] ?? Str::headline($slug),
                'description' => $manifest['description'] ?? $declared['description'] ?? '',
                // The catalog's grouping wins: it is what the picker's filters are built from
                'category' => (string) ($declared['category'] ?? $manifest['category'] ?? ''),
                'pages' => count($manifest['pages'] ?? $declared['pages'] ?? []),
                'preview' => 'thumbnail',
            ];
        }

        return $entries;
    }

    /**
     * The picker's filters: each category the catalog uses, in first-seen
     * order, with its label.
     *
     * @return array<string, string>
     */
    public function categories(): array
    {
        $labels = ['landing' => 'Landing pages', 'business' => 'Business'];
        $categories = [];

        foreach ($this->all() as $entry) {
            if ($entry['category'] !== '' && !isset($categories[$entry['category']])) {
                $categories[$entry['category']] = $labels[$entry['category']] ?? Str::headline($entry['category']);
            }
        }

        return $categories;
    }

    /** Absolute path to a downloaded template's thumbnail, if it ships one. */
    public function thumbnailPath(string $name): ?string
    {
        $dir = $this->sync->directory($name);

        return $dir && is_file($dir . '/thumbnail.png') ? $dir . '/thumbnail.png' : null;
    }

    /**
     * The thumbnail straight from a GitHub repository, for a template that
     * has not been downloaded yet. Null for repositories hosted elsewhere.
     */
    public function remoteThumbnail(string $name): ?string
    {
        $url = $this->sync->catalog()[$name] ?? null;

        if (!$url || !preg_match('#^https://github\.com/([^/]+)/([^/.]+)(\.git)?/?$#', $url, $m)) {
            return null;
        }

        return "https://raw.githubusercontent.com/{$m[1]}/{$m[2]}/HEAD/thumbnail.png";
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->sync->catalog());
    }
}
