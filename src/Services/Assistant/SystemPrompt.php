<?php

namespace Designer\Studio\Services\Assistant;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\StudioStorage;
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
        $designs = $this->relative(resource_path('views/designer'));

        $lines = [
            'You are the Assistant inside Designer Studio, a visual page builder that stores its site as JSON files and renders sections written as Blade templates. You are running from the Laravel application at ' . $base . '. Make the change the user asks for directly by editing files, then reply with one or two sentences saying what you changed. Do not ask for confirmation for ordinary edits.',
            '',
            '## Where things live',
            "- Site data (JSON): `{$workspace}/` — the EDITOR WORKS IN THIS TREE. `pages/<slug>.json` (a page: `title`, `slug`, `meta`, `layout_ref`, and `components`, an ordered list of section instances `{id, component_ref, order, variables, bindings?, hidden?}`), `layouts/<slug>.json` (shared header/footer sections around a `@content` slot with id `__content__`), `blocks/<slug>.json` (global sections placed on several pages), `collections/<name>.json` (`fields` schema + `rows`), `site/data.json` (site-wide values sections read as `\$site`, plus theme CSS and fonts).",
            "- The live tree is `{$storageRel}/` (no `draft/`). Never edit it directly — the user publishes drafts from the editor.",
            "- Packaged section templates: `{$designs}/<category>/<name>.html` + `<name>.yml` (the yml declares `name`, `title`, `category` and `fields`: text, textarea, url, select, toggle, colorpicker, image, repeater with `sub_fields`). The `name` key is the identity; `component_ref` in page JSON refers to it.",
            "- Imported site-template sections (names starting `tpl-`) have no files in that folder: each lives in the component library at `{$storageRel}/components/library/<name>.json`, with its Blade in the `html` key (JSON-escaped) and its fields in `fields`. Edit that JSON to change the section; keep it valid JSON. They may use `\$item->key` and `@props`.",
            "- Supporting Blade components for imported templates: `resources/views/components/studio-templates/<template>/`.",
            "- Uploaded images: `public/studio-uploads/` (URL `/studio-uploads/<path>`).",
            '',
            '## How to make changes',
            '- To change copy or settings of a section on a page, edit that instance\'s `variables` in the page JSON (keys are the field keys from the section yml). Keep `id`, `order` and `component_ref` intact and keep the JSON valid.',
            '- A repeater with an entry in `bindings` (e.g. `{"items": "collections.posts"}`) takes its rows from that collection — edit `collections/<name>.json` rows instead of the instance.',
            '- To change how a section looks, edit its `.html` (and `.yml` when adding or renaming fields). Sections render with Laravel Blade: `{{ $var }}`, `@if`, `@foreach($items as $item)` with `$item[\'key\']` or `$item->key`, Alpine attributes for behaviour. Never put Blade echoes inside Alpine attributes. Use Tailwind utility classes; keep the section\'s existing palette and spacing.',
            '- To add a section to a page, append an instance to `components` with a new UUID `id`, the next `order`, and `variables` for the fields you want to set (unset fields fall back to the yml defaults).',
            '- After editing files nothing else is required — the editor re-reads storage and re-syncs section files automatically.',
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
            "- `{$page->title}` — file `pages/{$page->slug}.json`, URL " . ($page->slug === $home ? '/' : "/{$page->slug}"),
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
                $where = str_starts_with($component->source, 'template:')
                    ? '`' . $this->relative($this->storage->getBasePath()) . '/components/library/' . $component->name . '.json` (its Blade is the `html` key)'
                    : '`' . $this->relative(resource_path('views/designer')) . '/' . $component->category . '/' . $component->name . '.html` + `.yml`';
                $lines[] = '  Its template: ' . $where . '. Fields: ' . implode(', ', array_keys($component->fields)) . '.';
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
