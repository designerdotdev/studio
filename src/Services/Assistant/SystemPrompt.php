<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\SiteUrls;

/**
 * What the CLI needs to know to edit this site well: where Studio keeps
 * things, what the current page is made of, which section is selected,
 * and the authoring rules for section files. Rebuilt for every turn so the
 * page/selection context is current.
 */
class SystemPrompt
{
    public function __construct(
        protected StudioStorage $storage,
        protected PageRepository $pages,
        protected LayoutRepository $layouts,
        protected ComponentRepository $components,
        protected CollectionRepository $collections,
    ) {}

    /**
     * @param  array{page?: ?string, section?: ?array, element?: ?array}  $context
     *         section: {id, ref, title, scope}; element: {path, tag, text}
     */
    public function build(array $context = []): string
    {
        $base = base_path();
        $storageRel = $this->relative($this->storage->getBasePath());
        $workspace = $this->storage->workspace() === 'draft' ? $storageRel . '/draft' : $storageRel;
        $site = $this->relative(SitePaths::resources());
        $public = $this->relative(SitePaths::public());

        $lines = [
            'You are the Assistant inside Designer Studio, a visual page builder for the Blade site installed in this Laravel application at ' . $base . '. Make the change the user asks for directly by editing files, then reply with one or two sentences saying what you changed. Do not ask for confirmation for ordinary edits.',
            '',
            '## Where things live',
            "- The site's source: `{$site}/` — `views/pages/*.blade.php` (one page per URL, `index.blade.php` is `/`; a page is `<x-layouts.main title=\"…\">` wrapping section tags like `<x-sections.hero heading=\"…\" :items=\"\$posts\"/>`), `views/components/` (anonymous Blade components: `sections/<name>.blade.php` + a `<name>.yml` declaring the section's editable `fields`, `layouts/*.blade.php` document shells with `{{ \$slot }}`, supporting components like `nav`/`footer`), `data/site.json` (read everywhere as `\$site`), `data/collections/<name>.json` (read everywhere as `\$<name>`), `css/*.css` (Tailwind v4 with `@theme` tokens), `designer.json` (page titles, SEO, order).",
            "- The site's public files: `{$public}/` (URL `/designer/<path>`) — images, scripts, uploads.",
            "- These files ARE the live site: `app/Providers/DesignerServiceProvider.php` serves them. Sections, layouts, CSS, and data edited here are live immediately.",
            "- The editor's unpublished draft of the pages: `{$workspace}/` — `pages/<slug>.json` (`components` is the ordered list of section instances `{id, component_ref, order, variables, bindings?, hidden?}`), `layouts/`, `blocks/`, `collections/`, `site/data.json`. The user edits these in the canvas and publishes them into the page files.",
            '',
            '## How to make changes',
            '- To change copy or settings of a section on the page the user is looking at, edit that instance\'s `variables` in the DRAFT page JSON (keys are the field keys from the section\'s `.yml`), keeping `id`, `order`, and `component_ref` intact and the JSON valid — the canvas shows it at once and it goes live when the user publishes. Never edit the live tree `' . $storageRel . '/pages/` directly.',
            '- A field with an entry in `bindings` takes its value from elsewhere: `collections.<name>` → edit the draft `collections/<name>.json` rows; `site.<key>` → edit the draft `site/data.json` `data`.',
            '- To change how a section looks, edit its `.blade.php` (and its `.yml` when adding or renaming fields; keep each field\'s key equal to the `@props` name). Sections render with Laravel Blade: `@props`, `{{ $var }}`, `@foreach($items as $item)` with `$item->key`, Alpine attributes for behaviour. Never put Blade echoes inside Alpine attributes. Use Tailwind utility classes and the site\'s own theme tokens.',
            '- To add a section to a page, append an instance to the draft page\'s `components` with a new UUID `id`, the next `order`, the section\'s `component_ref` (its library name, e.g. `sections-hero` for `sections/hero`), and `variables` for the fields you want to set (unset fields fall back to the yml defaults).',
            '- After editing files nothing else is required — the editor re-reads the site and re-syncs sections automatically.',
            '- Do not run the dev server, build tools, or git commands unless asked. Do not touch `vendor/` or `node_modules/`.',
            '',
        ];

        $lines = [...$lines, ...$this->pageContext($context), ...$this->selectionContext($context)];

        return implode("\n", $lines);
    }

    protected function pageContext(array $context): array
    {
        $slug = $context['page'] ?? null;
        $page = $slug ? $this->pages->find($slug) : null;

        if (!$page) {
            return [];
        }

        $home = SiteUrls::homeSlug();
        $lines = [
            '## The page open in the editor',
            "- `{$page->title}` — draft `pages/{$page->slug}.json`, published as `views/pages/" . ($page->slug === $home ? 'index' : $page->slug) . ".blade.php`, URL " . ($page->slug === $home ? '/' : "/{$page->slug}"),
        ];

        $instances = collect($page->components)->sortBy('order')->values();

        foreach ($instances as $i => $instance) {
            $ref = $instance['component_ref'] ?? ($instance['block_ref'] ? 'block:' . $instance['block_ref'] : '?');
            $component = $instance['component_ref'] ?? null ? $this->components->find($instance['component_ref']) : null;
            $label = $component ? "{$component->title} ({$ref})" : $ref;
            $hidden = !empty($instance['hidden']) ? ' [hidden]' : '';
            $bound = !empty($instance['bindings']) ? ' bound: ' . json_encode($instance['bindings']) : '';
            $lines[] = "  {$i}. {$label} — instance id `{$instance['id']}`{$hidden}{$bound}";
        }

        if ($page->layout_ref && ($layout = $this->layouts->find($page->layout_ref))) {
            $refs = collect($layout['components'] ?? [])
                ->sortBy('order')
                ->map(fn ($c) => $c['id'] === LayoutRepository::CONTENT_ID ? '@content' : ($c['component_ref'] ?? 'block:' . ($c['block_ref'] ?? '?')))
                ->implode(', ');
            $lines[] = "- Layout `{$layout['slug']}` (file `layouts/{$layout['slug']}.json`): {$refs}";
        }

        $collections = array_keys($this->collections->all());

        if ($collections) {
            $lines[] = '- Collections: ' . implode(', ', $collections);
        }

        $lines[] = '';

        return $lines;
    }

    protected function selectionContext(array $context): array
    {
        $section = $context['section'] ?? null;
        $element = $context['element'] ?? null;

        if (!$section && !$element) {
            return [];
        }

        $lines = ['## What the user has selected'];

        if ($section) {
            $component = !empty($section['ref']) ? $this->components->find($section['ref']) : null;
            $where = ($section['scope'] ?? 'page') === 'layout' ? 'the shared layout' : 'this page';
            $lines[] = '- Section `' . ($section['ref'] ?? '?') . '`' . ($component ? " ({$component->title})" : '') . ' on ' . $where . ', instance id `' . ($section['id'] ?? '?') . '`.';

            if ($component) {
                $where = '`' . $this->relative(SitePaths::components($component->path)) . '.blade.php` + `.yml`';
                $lines[] = '  Its source: ' . $where . ' (tag `<x-' . $component->tag . '>`). Fields: ' . implode(', ', array_keys($component->fields)) . '.';
            }
        }

        if ($element) {
            $lines[] = '- Element inside it: `' . ($element['path'] ?? '') . '`' . (!empty($element['text']) ? ' with text "' . mb_substr($element['text'], 0, 120) . '"' : '') . '. "This"/"it" in the request refers to this element.';
        }

        $lines[] = '';

        return $lines;
    }

    protected function relative(string $absolute): string
    {
        $base = rtrim(base_path(), '/') . '/';

        return str_starts_with($absolute, $base) ? substr($absolute, strlen($base)) : $absolute;
    }
}
