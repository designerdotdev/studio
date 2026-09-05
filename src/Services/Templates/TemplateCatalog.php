<?php

namespace Designer\Studio\Services\Templates;

use Designer\Studio\Services\TemplateRegistry;
use Illuminate\Support\Str;

/**
 * Every template a site can start from, wherever it came from.
 *
 * Two sources feed this list. The templates built into the package are
 * always present, so a fresh install works with no network and no
 * configuration. Anything synced from the catalog of git repositories is
 * added alongside them, which is how one template gets maintained in one
 * place and used by every app.
 *
 * A synced template wins a name clash: if the catalog carries its own
 * "atlas", that is the one the site was meant to install.
 */
class TemplateCatalog
{
    public function __construct(
        protected TemplateRegistry $builtIn,
        protected TemplateSync $sync,
    ) {}

    /**
     * Every available template, in the order the picker should show them:
     * the built-ins first, then anything synced from a repository.
     *
     * @return array<string, array{name: string, title: string, description: string, source: string, pages: int, empty: bool, preview: string}>
     */
    public function all(): array
    {
        $entries = [];

        foreach ($this->builtIn->all() as $name => $template) {
            $entries[$name] = [
                'name' => $name,
                'title' => $template['title'] ?? Str::headline($name),
                'description' => $template['description'] ?? '',
                'source' => 'built-in',
                'pages' => count($template['pages'] ?? []),
                'empty' => ($template['pages'][0]['components'] ?? []) === [],
                // A built-in is composed of library sections, so it can be
                // rendered live; a repository ships a picture of itself.
                'preview' => ($template['pages'][0]['components'] ?? []) === [] ? 'none' : 'live',
            ];
        }

        foreach ($this->sync->synced() as $slug => $dir) {
            $manifest = $this->sync->manifest($slug) ?? [];

            $entries[$slug] = [
                'name' => $slug,
                'title' => $manifest['name'] ?? Str::headline($slug),
                'description' => $manifest['description'] ?? '',
                'source' => 'repository',
                'pages' => count($manifest['pages'] ?? []),
                'empty' => false,
                'preview' => is_file($dir . '/thumbnail.png') ? 'thumbnail' : 'none',
            ];
        }

        return $entries;
    }

    /** Absolute path to a synced template's thumbnail, if it ships one. */
    public function thumbnailPath(string $name): ?string
    {
        $dir = $this->sync->directory($name);

        return $dir && is_file($dir . '/thumbnail.png') ? $dir . '/thumbnail.png' : null;
    }

    /** Where a given template would be installed from, or null if unknown. */
    public function sourceOf(string $name): ?string
    {
        if ($this->sync->directory($name)) {
            return 'repository';
        }

        return $this->builtIn->find($name) ? 'built-in' : null;
    }

    public function has(string $name): bool
    {
        return $this->sourceOf($name) !== null;
    }
}
