<?php

namespace Designer\Studio\Services\Storage;

/**
 * The site document: site-wide settings that belong to no one page.
 *
 *   data       the `$site` bag every section reads — company name, menus,
 *              social links (resources/designer/data/site.json)
 *   home_slug  which page is served at "/" (designer.json `home`)
 *   template   the template the site was installed from
 *
 * Stored at `site/data.json`, so it is drafted, published, and discarded
 * alongside pages. Fonts, stylesheets, and scripts are not settings here:
 * they live in the site's own layout and CSS files (see SiteChrome).
 */
class SiteRepository
{
    protected const PATH = 'site/data.json';

    public function __construct(
        protected StudioStorage $storage
    ) {}

    public function get(): array
    {
        return ($this->storage->read(self::PATH) ?? []) + $this->blank();
    }

    public function save(array $data): array
    {
        $merged = array_merge($this->get(), $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->storage->write(self::PATH, $merged);

        return $merged;
    }

    /** Wipe the site doc back to defaults. */
    public function reset(): void
    {
        $this->storage->delete(self::PATH);
    }

    /** The `$site` bag sections read. */
    public function data(): array
    {
        return $this->get()['data'] ?? [];
    }

    /**
     * Set one key of the `$site` bag by dot path (`menu_primary`,
     * `theme.fonts_url`) — how a section field bound to site data saves.
     */
    public function setData(string $path, mixed $value): array
    {
        $data = $this->data();
        data_set($data, $path, $value);

        return $this->save(['data' => $data]);
    }

    /** Slug of the template this site was installed from, if any. */
    public function template(): ?string
    {
        return $this->get()['template'] ?? null;
    }

    protected function blank(): array
    {
        return [
            'template' => null,
            'data' => [],
        ];
    }
}
