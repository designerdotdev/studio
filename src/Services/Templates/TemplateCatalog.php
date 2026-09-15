<?php

namespace Designer\Studio\Services\Templates;

use Illuminate\Support\Str;

/**
 * The templates a site can start from.
 *
 * Normally the entries of `studio.templates.catalog`, each a whole site in
 * its own git repository (the DevDojo `site-templates` format). A template
 * does not have to be downloaded to be offered — the picker shows the
 * catalog's own name and description, with the thumbnail served from the
 * repository — and it is cloned the moment someone picks it.
 *
 * When the local template previewer is on (`studio.template_preview`, the
 * folder of template repositories being worked on), that folder is the
 * catalog instead: every template in it is offered, straight from its
 * working tree, and installing one copies from there — no clone.
 */
class TemplateCatalog
{
    public function __construct(
        protected TemplateSync $sync,
    ) {}

    /** True when the picker is reading the local template folder. */
    public function local(): bool
    {
        return TemplatePreview::enabled();
    }

    /**
     * Every offered template, in catalog order (alphabetical for the local
     * folder).
     *
     * @return array<string, array{name: string, title: string, description: string, category: string, theme: string, pages: int, preview: string, preview_url: ?string}>
     */
    public function all(): array
    {
        return $this->local() ? $this->fromFolder() : $this->fromConfig();
    }

    /** @return array<string, array> */
    protected function fromConfig(): array
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
                'theme' => (string) ($manifest['theme'] ?? ''),
                'pages' => count($manifest['pages'] ?? $declared['pages'] ?? []),
                'preview' => 'thumbnail',
                'preview_url' => null,
            ];
        }

        return $entries;
    }

    /** @return array<string, array> */
    protected function fromFolder(): array
    {
        $preview = TemplatePreview::make();
        $entries = [];

        foreach ($preview->catalog() as $row) {
            $entries[$row['slug']] = [
                'name' => $row['slug'],
                'title' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category'],
                'theme' => $row['theme'],
                'pages' => count($row['pages']),
                'preview' => 'thumbnail',
                'preview_url' => '/' . $preview->prefix() . '/' . $row['slug'],
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

    /**
     * The themes the offered templates declare (`light`, `dark`), in
     * first-seen order — the picker only shows a theme filter when there is
     * more than one.
     *
     * @return list<string>
     */
    public function themes(): array
    {
        $themes = [];

        foreach ($this->all() as $entry) {
            if ($entry['theme'] !== '' && !in_array($entry['theme'], $themes, true)) {
                $themes[] = $entry['theme'];
            }
        }

        return $themes;
    }

    /**
     * The folder a template installs from, downloading it first when it is
     * a catalogued repository. Throws when it cannot be had.
     */
    public function directory(string $name): string
    {
        if ($this->local()) {
            $dir = TemplatePreview::make()->directory($name);

            if ($dir === null) {
                throw new \RuntimeException("Template [{$name}] is not in the template folder.");
            }

            return $dir;
        }

        return $this->sync->ensure($name);
    }

    /** Absolute path to a template's thumbnail, if it has one on disk. */
    public function thumbnailPath(string $name): ?string
    {
        if ($this->local()) {
            return TemplatePreview::make()->thumbnail($name);
        }

        $dir = $this->sync->directory($name);

        return $dir && is_file($dir . '/thumbnail.png') ? $dir . '/thumbnail.png' : null;
    }

    /**
     * The thumbnail straight from a GitHub repository, for a template that
     * has not been downloaded yet. Null for repositories hosted elsewhere
     * and for the local folder (which has nothing to download).
     */
    public function remoteThumbnail(string $name): ?string
    {
        if ($this->local()) {
            return null;
        }

        $url = $this->sync->catalog()[$name] ?? null;

        if (!$url || !preg_match('#^https://github\.com/([^/]+)/([^/.]+)(\.git)?/?$#', $url, $m)) {
            return null;
        }

        return "https://raw.githubusercontent.com/{$m[1]}/{$m[2]}/HEAD/thumbnail.png";
    }

    public function has(string $name): bool
    {
        return $this->local()
            ? TemplatePreview::make()->directory($name) !== null
            : array_key_exists($name, $this->sync->catalog());
    }
}
