<?php

namespace Designer\Studio\Services\Storage;

/**
 * Site-wide settings: the one document that isn't a page, a layout, or a
 * block.
 *
 * An imported template brings more than sections with it — a palette, a
 * font pairing, the scripts its motion depends on, and a bag of site-level
 * content (company name, nav links, social handles) that its sections read
 * as `$site`. All of that lands here, and every render path reads it back:
 * the canvas iframe, the public page, the draft preview, and Blade exports.
 *
 * Stored at `site/data.json`, so it publishes and discards alongside pages.
 */
class SiteRepository
{
    protected const PATH = 'site/data.json';

    public function __construct(
        protected StudioStorage $storage
    ) {}

    public function get(): array
    {
        return $this->storage->read(self::PATH) ?? $this->blank();
    }

    public function save(array $data): array
    {
        $merged = array_merge($this->get(), $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->storage->write(self::PATH, $merged);

        return $merged;
    }

    /** Wipe the site doc back to defaults (a fresh import starts clean). */
    public function reset(): void
    {
        $this->storage->delete(self::PATH);
    }

    /**
     * The `$site` bag sections read — company name, nav links, socials.
     * Kept separate from the chrome keys so a template's own shape passes
     * through untouched.
     */
    public function data(): array
    {
        return $this->get()['data'] ?? [];
    }

    /** Tailwind-compatible CSS injected into every render of this site. */
    public function themeCss(): string
    {
        return (string) ($this->get()['theme_css'] ?? '');
    }

    /**
     * Font links, preconnects, and anything else the template's own layout
     * carried in its <head>. Trusted markup — it only ever arrives from a
     * template import or an explicit edit, never from page content.
     */
    public function headHtml(): string
    {
        return (string) ($this->get()['head_html'] ?? '');
    }

    /** <script src> URLs the template's sections rely on, in order. */
    public function scripts(): array
    {
        return array_values((array) ($this->get()['scripts'] ?? []));
    }

    /** Classes the template puts on <body>, so type and colour inherit. */
    public function bodyClass(): string
    {
        return (string) ($this->get()['body_class'] ?? '');
    }

    /** Classes the template puts on <html> (dark-mode switches live here). */
    public function htmlClass(): string
    {
        return (string) ($this->get()['html_class'] ?? '');
    }

    /** Slug of the template this site was imported from, if any. */
    public function template(): ?string
    {
        return $this->get()['template'] ?? null;
    }

    protected function blank(): array
    {
        return [
            'template' => null,
            'data' => [],
            'theme_css' => '',
            'head_html' => '',
            'scripts' => [],
            'body_class' => '',
            'html_class' => '',
        ];
    }
}
