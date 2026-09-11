<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\DataTransferObjects\ComponentData;
use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes Studio's documents back into the installed site (the inverse of
 * {@see SiteReader}).
 *
 * Files are edited, not regenerated. A page file is re-read, each of its
 * section tags is matched to the instance it was read as (by the ids the
 * mirror recorded), and only what changed is rewritten — down to the single
 * attribute. Everything Studio does not model stays byte for byte: the
 * comments that describe a section travel with it when it moves, and the
 * markup around a layout's sections (`<main>`, `@if ($showFooter)`) stays
 * where it is.
 *
 * Nothing is written for a document that did not change, and a file Studio
 * does not own (a hand-written page at the same URL) is never overwritten.
 */
class SiteWriter
{
    /** @var array<string, ComponentData> name => component */
    protected array $library = [];

    protected AttributeCodec $codec;

    /** @var string[] problems worth telling the user about */
    protected array $notes = [];

    public function __construct(
        protected ComponentRepository $components,
        protected CollectionRepository $collections,
    ) {}

    /**
     * @param  array  $docs   live documents: pages, layouts, blocks,
     *                        collections (slug => doc) and site (doc)
     * @param  array  $state  the mirror state from the last sync
     * @return array{state: array, notes: string[]}
     */
    public function write(array $docs, array $state): array
    {
        $this->notes = [];
        $this->library = [];

        foreach ($this->components->all() as $component) {
            $this->library[$component->name] = $component;
        }

        $sources = [];
        foreach ($docs['collections'] ?? [] as $name => $doc) {
            $sources[$this->collectionSource($doc)] = $name;
        }

        $this->codec = new AttributeCodec($sources);

        $writes = [];
        $deletes = [];
        $next = ['pages' => [], 'layouts' => [], 'blocks' => [], 'collections' => []];

        $this->planCollections($docs['collections'] ?? [], $state, $writes, $deletes, $next);
        $this->planSite($docs['site'] ?? null, $writes);
        $this->planBlocks($docs['blocks'] ?? [], $state, $writes, $deletes, $next);
        $this->planLayouts($docs['layouts'] ?? [], $state, $writes, $deletes, $next);
        $this->planPages($docs['pages'] ?? [], $docs['site'] ?? [], $state, $writes, $deletes, $next);

        // Apply: deletions first (a page moving onto a freed URL), then
        // writes, each through a temp file so a reader never sees half.
        foreach (array_keys($deletes) as $relative) {
            if (!isset($writes[$relative]) && is_file(base_path($relative))) {
                File::delete(base_path($relative));
            }
        }

        foreach ($writes as $relative => $contents) {
            $this->put(base_path($relative), $contents);
        }

        SiteManifest::write($this->manifest($docs));

        return ['state' => $next, 'notes' => $this->notes];
    }

    /* ------------------------------------------------------------ */
    /*  Pages                                                        */
    /* ------------------------------------------------------------ */

    protected function planPages(array $pages, array $site, array $state, array &$writes, array &$deletes, array &$next): void
    {
        $home = (string) ($site['home_slug'] ?? 'home');
        $recorded = $state['pages'] ?? [];
        $owned = array_flip(array_column($recorded, 'path'));

        // Read every current source first: pages can trade files (a new
        // home page takes index.blade.php from the old one).
        $sources = [];
        foreach ($recorded as $slug => $entry) {
            if (is_file(base_path($entry['path']))) {
                $sources[$slug] = (string) file_get_contents(base_path($entry['path']));
            }
        }

        foreach ($recorded as $slug => $entry) {
            if (!isset($pages[$slug]) || $this->pagePath($pages[$slug], $home) !== $entry['path']) {
                $deletes[$entry['path']] = true;
            }
        }

        foreach ($pages as $slug => $doc) {
            $target = $this->pagePath($doc, $home);

            if (is_file(base_path($target)) && !isset($owned[$target])) {
                $this->notes[] = "Not published: {$target} is a hand-written page, so the “{$doc['title']}” page was not written over it. Rename one of them.";
                unset($deletes[$recorded[$slug]['path'] ?? '']);

                if (isset($recorded[$slug])) {
                    $next['pages'][$slug] = $recorded[$slug];
                }

                continue;
            }

            $entry = $recorded[$slug] ?? null;
            $source = $sources[$slug] ?? null;
            $ids = $entry && $source !== null && $this->unchanged($entry['path'], $state) ? ($entry['ids'] ?? []) : [];

            [$contents, $written] = $this->composePage($doc, $source, $ids);

            if ($source === null || $contents !== $source || $target !== ($entry['path'] ?? null)) {
                $writes[$target] = $contents;
            }

            $next['pages'][$slug] = ['path' => $target, 'ids' => $written];
        }
    }

    protected function pagePath(array $doc, string $home): string
    {
        return SitePaths::relative(SitePaths::pages(($doc['slug'] === $home ? 'index' : $doc['slug']) . '.blade.php'));
    }

    /** @return array{0: string, 1: string[]} [file contents, instance ids in file order] */
    protected function composePage(array $doc, ?string $source, array $ids): array
    {
        $layout = $doc['layout_ref'] ?? null;
        $instances = collect($doc['components'] ?? [])->sortBy('order')->values()->all();

        $structure = $source !== null ? SiteReader::pageStructure($source) : null;

        if ($structure === null) {
            // A brand-new page, or one that stopped being a composition
            // behind Studio's back: start from a clean file.
            $source = $layout ? "<x-layouts.{$layout}>\n\n</x-layouts.{$layout}>\n" : '';
            $structure = SiteReader::pageStructure($source);
            $ids = [];
        }

        [$region, $written] = $this->rebuild(
            $source,
            [[$structure['start'], $structure['end'], $instances]],
            $ids
        );

        $region = $region[0];
        $wrapper = $structure['wrapper'];

        if ($layout) {
            $attributes = [
                'title' => ($doc['meta']['seo_title'] ?? '') !== '' ? $doc['meta']['seo_title'] : $doc['title'],
                'description' => ($doc['meta']['seo_description'] ?? '') !== '' ? $doc['meta']['seo_description'] : ($doc['description'] ?? ''),
            ];

            // A layout tag written without a title leaves the <title> to the
            // layout (often just the site name, on a home page). The reader
            // takes the page's name from designer.json then, so the tag only
            // gains a title when an SEO title is set.
            if ($wrapper !== null && ($doc['meta']['seo_title'] ?? '') === '' && !$this->hasAttribute($wrapper, 'title')) {
                unset($attributes['title']);
            }

            if ($wrapper === null) {
                $open = $this->openTag(['head' => '<x-layouts.' . $layout, 'items' => [], 'trailing' => '', 'tail' => '>'], $attributes);
                $body = rtrim($region);
                $contents = $open . "\n\n" . ($body === '' ? '' : $this->indentBlock(ltrim($body, "\n"), '    ') . "\n\n") . "</x-layouts.{$layout}>\n";
            } else {
                $wrapper['head'] = '<x-layouts.' . $layout;
                $wrapper['closing'] = '</x-layouts.' . $layout . '>';

                $contents = substr($source, 0, $wrapper['start'])
                    . $this->openTag($wrapper, $attributes)
                    . $region
                    . $wrapper['closing']
                    . substr($source, $wrapper['end']);
            }
        } elseif ($wrapper !== null) {
            $contents = substr($source, 0, $wrapper['start']) . $this->dedent($region) . substr($source, $wrapper['end']);
        } else {
            $contents = $region;
        }

        if (trim($contents) !== '' && !str_ends_with($contents, "\n")) {
            $contents .= "\n";
        }

        return [$contents, $written];
    }

    protected function hasAttribute(array $tag, string $key): bool
    {
        foreach ($tag['items'] as $item) {
            if (($this->codec->read($item)['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /** A layout tag's opening part with title/description set. */
    protected function openTag(array $tag, array $attributes): string
    {
        $items = [];
        $seen = [];

        foreach ($tag['items'] as $item) {
            $read = $this->codec->read($item);
            $key = $read['key'] ?? null;

            if ($key !== null && array_key_exists($key, $attributes)) {
                $seen[$key] = true;

                if (!($read['kind'] === 'value' && $read['value'] === $attributes[$key])) {
                    $item['raw'] = $this->codec->writeValue($item['name'], $attributes[$key]);
                }
            }

            $items[] = $item;
        }

        foreach ($attributes as $key => $value) {
            if (!isset($seen[$key]) && ($value !== '' || $key === 'title')) {
                $items[] = ['name' => $key, 'space' => ' ', 'raw' => $this->codec->writeValue($key, $value)];
            }
        }

        $open = $tag['head'];

        foreach ($items as $item) {
            $open .= $item['space'] . $item['raw'];
        }

        return $open . $tag['trailing'] . $tag['tail'];
    }

    /* ------------------------------------------------------------ */
    /*  Layouts                                                      */
    /* ------------------------------------------------------------ */

    protected function planLayouts(array $layouts, array $state, array &$writes, array &$deletes, array &$next): void
    {
        $recorded = $state['layouts'] ?? [];

        foreach ($recorded as $slug => $entry) {
            if (isset($layouts[$slug])) {
                continue;
            }

            if ($this->referencedByCode('layouts.' . $slug)) {
                $this->notes[] = "Kept {$entry['path']}: hand-written pages still use it.";

                continue;
            }

            $deletes[$entry['path']] = true;
        }

        foreach ($layouts as $slug => $doc) {
            $path = SitePaths::relative(SitePaths::components(SitePaths::LAYOUTS . '/' . $slug . '.blade.php'));
            $entry = $recorded[$slug] ?? null;
            $source = is_file(base_path($path)) ? (string) file_get_contents(base_path($path)) : null;

            if ($source !== null && $entry === null) {
                $this->notes[] = "Not published: {$path} already exists, so the new “{$doc['name']}” layout was not written over it.";

                continue;
            }

            $valid = $entry && $source !== null && $this->unchanged($path, $state);
            $source ??= $this->newLayoutSource($layouts);
            $regions = SiteReader::layoutRegions($source);

            if ($regions === null) {
                $this->notes[] = "Not published: {$path} has no {{ \$slot }} any more, so its sections could not be placed.";

                continue;
            }

            $split = app(LayoutRepository::class)->splitComponents($doc['components'] ?? []);
            $ids = $valid ? [...($entry['before'] ?? []), ...($entry['after'] ?? [])] : [];

            [$texts, $written] = $this->rebuild($source, [
                [$regions['before'][0], $regions['before'][1], $split['before']],
                [$regions['after'][0], $regions['after'][1], $split['after']],
            ], $ids);

            $contents = substr($source, 0, $regions['before'][0])
                . $texts[0]
                . substr($source, $regions['slot'][0], $regions['slot'][1] - $regions['slot'][0])
                . $texts[1]
                . substr($source, $regions['after'][1]);

            if ($contents !== ($entry && is_file(base_path($path)) ? file_get_contents(base_path($path)) : null)) {
                $writes[$path] = $contents;
            }

            $before = count($split['before']);
            $next['layouts'][$slug] = [
                'path' => $path,
                'before' => array_slice($written, 0, $before),
                'after' => array_slice($written, $before),
            ];
        }
    }

    /**
     * The file a new layout starts from: the site's main layout with its
     * shared sections taken out, so it keeps the same <head>, fonts, and
     * page wrapper. A site with no layout at all gets a minimal document.
     */
    protected function newLayoutSource(array $layouts): string
    {
        foreach (['main', ...array_map(fn ($f) => basename($f, '.blade.php'), glob(SitePaths::components(SitePaths::LAYOUTS . '/*.blade.php')) ?: [])] as $slug) {
            $file = SitePaths::components(SitePaths::LAYOUTS . '/' . $slug . '.blade.php');

            if (!is_file($file)) {
                continue;
            }

            $source = (string) file_get_contents($file);
            $regions = SiteReader::layoutRegions($source);

            if ($regions === null) {
                continue;
            }

            [$texts] = $this->rebuild($source, [
                [$regions['before'][0], $regions['before'][1], []],
                [$regions['after'][0], $regions['after'][1], []],
            ], []);

            $shell = substr($source, 0, $regions['before'][0]) . $texts[0]
                . substr($source, $regions['slot'][0], $regions['slot'][1] - $regions['slot'][0])
                . $texts[1] . substr($source, $regions['after'][1]);

            // Conditionals that only wrapped a section now wrap nothing
            $shell = preg_replace('/\n[ \t]*@if\s*\((?:[^()]|\([^()]*\))*\)\s*@endif[ \t]*(?=\n)/', '', $shell) ?? $shell;

            return preg_replace("/\n{3,}/", "\n\n", $shell) ?? $shell;
        }

        $styles = collect(glob(SitePaths::resources('css/*.css')) ?: [])
            ->map(fn ($file) => "'" . SitePaths::relative($file) . "'")
            ->implode(', ');

        return "@props(['title' => '', 'description' => ''])\n"
            . "<!doctype html>\n<html lang=\"en\">\n<head>\n"
            . "    <meta charset=\"utf-8\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . "    <title>{{ \$title }}</title>\n"
            . "    <meta name=\"description\" content=\"{{ \$description }}\">\n"
            . ($styles !== '' ? "    @vite([{$styles}])\n" : '')
            . "</head>\n<body>\n\n    {{ \$slot }}\n\n</body>\n</html>\n";
    }

    /* ------------------------------------------------------------ */
    /*  Blocks                                                       */
    /* ------------------------------------------------------------ */

    protected function planBlocks(array $blocks, array $state, array &$writes, array &$deletes, array &$next): void
    {
        $recorded = $state['blocks'] ?? [];

        foreach ($recorded as $slug => $entry) {
            if (!isset($blocks[$slug]) && !$this->referencedByCode('blocks.' . $slug)) {
                $deletes[$entry['path']] = true;
            }
        }

        foreach ($blocks as $slug => $doc) {
            $path = SitePaths::relative(SitePaths::components(SitePaths::BLOCKS . '/' . $slug . '.blade.php'));
            $existing = is_file(base_path($path)) ? (string) file_get_contents(base_path($path)) : null;

            if ($existing !== null && !isset($recorded[$slug])) {
                $this->notes[] = "Not published: {$path} already exists, so the “{$doc['name']}” block was not written over it.";

                continue;
            }

            $instance = array_filter([
                'id' => 'block',
                'component_ref' => $doc['component_ref'] ?? null,
                'variables' => $doc['variables'] ?? [],
                'bindings' => $doc['bindings'] ?? null,
                'slot' => $doc['slot'] ?? null,
            ], fn ($v) => $v !== null);

            if ($existing !== null) {
                $source = $existing;
                $stretch = [0, strlen($source)];
                // The one tag in the file is the block's section
                $ids = count(BladeTags::scan($source)) === 1 && $this->unchanged($path, $state) ? ['block'] : [];
            } else {
                $source = '{{-- Global block “' . str_replace('--', '—', $doc['name']) . '”: one section shared by every page that places <x-blocks.' . $slug . " /> --}}\n";
                $stretch = [strlen($source), strlen($source)];
                $ids = [];
            }

            [$texts] = $this->rebuild($source, [[$stretch[0], $stretch[1], [$instance]]], $ids, "\n");

            $contents = rtrim(substr($source, 0, $stretch[0]) . $texts[0]) . "\n";

            if ($contents !== $existing) {
                $writes[$path] = $contents;
            }

            $next['blocks'][$slug] = ['path' => $path];
        }
    }

    /* ------------------------------------------------------------ */
    /*  Regions — the core of the in-place edit                      */
    /* ------------------------------------------------------------ */

    /**
     * Rewrite stretches of a file to hold the given instances.
     *
     * Each stretch is `[start, end, instances]`. The tags already in the
     * file are matched to instances by `$ids` (the instance ids of the
     * file's managed tags, in file order, across all stretches). A matched
     * tag keeps its text, edited attribute by attribute; an unmatched
     * instance gets a new tag; a tag with no instance any more is dropped.
     *
     * @return array{0: string[], 1: string[]} [new text per stretch, ids written in order]
     */
    protected function rebuild(string $source, array $stretches, array $ids, ?string $defaultGap = null): array
    {
        // Every managed tag in the file, in order, across the stretches
        $pool = [];
        $layout = [];

        foreach ($stretches as $s => [$start, $end]) {
            $cursor = $start;
            $items = [];

            foreach (BladeTags::scan($source, $start, $end) as $entry) {
                if (!$this->managed($entry['tag']['ref'])) {
                    continue; // unmanaged tags are part of the surrounding markup
                }

                [$anchor, $prelude] = BladeTags::splitGap(substr($source, $cursor, $entry['start'] - $cursor));
                $items[] = ['anchor' => $anchor, 'prelude' => $prelude, 'entry' => $entry];
                $pool[] = ['prelude' => $prelude, 'entry' => $entry, 'raw' => substr($source, $entry['start'], $entry['end'] - $entry['start'])];
                $cursor = $entry['end'];
            }

            $layout[$s] = ['items' => $items, 'trailer' => substr($source, $cursor, $end - $cursor)];
        }

        // Ids only mean something if they line up with the tags one to one
        $byId = count($ids) === count($pool) ? array_flip($ids) : [];
        $used = [];
        $written = [];
        $texts = [];

        foreach ($stretches as $s => [$start, $end, $instances]) {
            $items = $layout[$s]['items'];
            $indent = $this->indentOf($items, $layout[$s]['trailer']);
            $gap = $defaultGap ?? ("\n\n" . $indent);
            $out = '';

            foreach (array_values($instances) as $i => $instance) {
                $out .= $items[$i]['anchor'] ?? '';
                $k = $byId[$instance['id'] ?? ''] ?? null;

                if ($k !== null && !isset($used[$k])) {
                    $used[$k] = true;
                    $out .= $pool[$k]['prelude'] . $this->patchTag($pool[$k], $instance, $indent);
                } else {
                    $out .= ($i === 0 && $items === [] && $layout[$s]['trailer'] === '' ? '' : $gap) . $this->newTag($instance, $indent);
                }

                $written[] = $instance['id'] ?? null;
            }

            // Structure that sat between tags no longer there stays put
            for ($i = count($instances); $i < count($items); $i++) {
                $out .= $items[$i]['anchor'];
            }

            $texts[$s] = $out . $layout[$s]['trailer'];
        }

        return [$texts, $written];
    }

    protected function managed(string $ref): bool
    {
        if (str_starts_with($ref, 'blocks.')) {
            return true;
        }

        foreach ($this->library as $component) {
            if ($component->tag === $ref) {
                return true;
            }
        }

        return false;
    }

    /** The indentation sections in a stretch are written at. */
    protected function indentOf(array $items, string $trailer): string
    {
        foreach ($items as $item) {
            if (preg_match('/\n([ \t]*)$/', $item['prelude'], $m)) {
                return $m[1];
            }
        }

        if (preg_match('/\n([ \t]*)\S/', $trailer, $m)) {
            return $m[1];
        }

        return '    ';
    }

    /** An existing tag brought in line with its instance. */
    protected function patchTag(array $old, array $instance, string $indent): string
    {
        $entry = $old['entry'];
        $tag = $entry['tag'];
        $wasHidden = $entry['type'] === 'hidden';
        $hidden = !empty($instance['hidden']);

        $text = $this->tagMatches($tag, $instance)
            ? $this->editedTag($tag, $instance, $indent)
            : $this->newTagText($instance, $indent);

        if ($hidden && $wasHidden && $text === $tag['raw']) {
            return $old['raw']; // untouched hidden section, comment and all
        }

        return $hidden ? '{{-- ' . $text . ' --}}' : $text;
    }

    protected function tagMatches(array $tag, array $instance): bool
    {
        if (!empty($instance['block_ref'])) {
            return $tag['ref'] === 'blocks.' . $instance['block_ref'];
        }

        $component = $this->library[$instance['component_ref'] ?? ''] ?? null;

        return $component !== null && $tag['ref'] === $component->tag;
    }

    /** Edit a tag attribute by attribute; untouched attributes keep their text. */
    protected function editedTag(array $tag, array $instance, string $indent): string
    {
        if (!empty($instance['block_ref'])) {
            return $tag['raw'];
        }

        $component = $this->library[$instance['component_ref']];
        $wanted = $this->wantedAttributes($instance, $component);
        $items = [];
        $seen = [];
        $separator = ' ';

        foreach ($tag['items'] as $item) {
            $separator = $item['space'] !== '' ? $item['space'] : $separator;
            $read = $this->codec->read($item, $component->fields);

            if ($read['kind'] === 'opaque') {
                $items[] = $item;

                continue;
            }

            $key = $read['key'];

            if (isset($seen[$key]) || !array_key_exists($key, $wanted)) {
                continue; // a duplicate, or an attribute the instance dropped
            }

            $seen[$key] = true;
            $want = $wanted[$key];

            if (!$this->equivalent($read, $want, $this->fieldType($component, $key))) {
                $item['raw'] = $this->encode($item['name'], $want, $component, $key, $this->attributeIndent($item['space'], $indent));
            }

            $items[] = $item;
        }

        foreach ($wanted as $key => $want) {
            if (isset($seen[$key]) || !$this->worthWriting($want, $component, $key)) {
                continue;
            }

            $items[] = [
                'name' => $key,
                'space' => $separator,
                'raw' => $this->encode($key, $want, $component, $key, $this->attributeIndent($separator, $indent)),
            ];
        }

        $tag['items'] = $items;

        if (($instance['slot'] ?? null) !== null && !$tag['selfClosing']) {
            $tag['slot'] = $instance['slot'];
        }

        return BladeTags::render($tag);
    }

    /** A fresh tag for an instance (no existing text to keep). */
    protected function newTag(array $instance, string $indent): string
    {
        $text = $this->newTagText($instance, $indent);

        return !empty($instance['hidden']) ? '{{-- ' . $text . ' --}}' : $text;
    }

    protected function newTagText(array $instance, string $indent): string
    {
        if (!empty($instance['block_ref'])) {
            return '<x-blocks.' . $instance['block_ref'] . ' />';
        }

        $component = $this->library[$instance['component_ref'] ?? ''] ?? null;

        if ($component === null) {
            return '';
        }

        $attributes = [];

        foreach ($this->wantedAttributes($instance, $component) as $key => $want) {
            if ($this->worthWriting($want, $component, $key)) {
                $attributes[] = $this->encode($key, $want, $component, $key, $indent . '    ');
            }
        }

        $slot = $instance['slot'] ?? null;
        $close = $slot !== null && trim($slot) !== '' ? '>' . $slot . '</x-' . $component->tag . '>' : ' />';

        $inline = '<x-' . $component->tag . ($attributes === [] ? '' : ' ' . implode(' ', $attributes));

        if (count($attributes) <= 3 && strlen($inline) <= 100 && !str_contains($inline, "\n")) {
            return $inline . $close;
        }

        return '<x-' . $component->tag . "\n" . $indent . '    ' . implode("\n" . $indent . '    ', $attributes) . $close;
    }

    /**
     * What each attribute should say: a binding wins over a stored value.
     * Keys are in field order, then anything else the instance carries.
     *
     * @return array<string, array{binding?: string, value?: mixed}>
     */
    protected function wantedAttributes(array $instance, ComponentData $component): array
    {
        $variables = $instance['variables'] ?? [];
        $bindings = $instance['bindings'] ?? [];
        $keys = array_unique([...array_keys($component->fields), ...array_keys($bindings), ...array_keys($variables)]);
        $wanted = [];

        foreach ($keys as $key) {
            if (isset($bindings[$key]) && is_string($bindings[$key])) {
                $wanted[$key] = ['binding' => $bindings[$key]];
            } elseif (array_key_exists($key, $variables)) {
                $wanted[$key] = ['value' => $variables[$key]];
            }
        }

        return $wanted;
    }

    /** New attributes are written only when they say something. */
    protected function worthWriting(array $want, ComponentData $component, string $key): bool
    {
        if (isset($want['binding'])) {
            return true;
        }

        $value = $want['value'];

        if (!isset($component->fields[$key])) {
            return $value !== null && $value !== '' && $value !== [];
        }

        return !AttributeCodec::same($value, $this->fieldDefault($component, $key), $this->fieldType($component, $key));
    }

    protected function equivalent(array $read, array $want, string $type): bool
    {
        if ($read['kind'] === 'binding') {
            return ($want['binding'] ?? null) === $read['binding'];
        }

        return !isset($want['binding']) && AttributeCodec::same($read['value'], $want['value'], $type);
    }

    protected function encode(string $name, array $want, ComponentData $component, string $key, string $indent): string
    {
        return isset($want['binding'])
            ? $this->codec->writeBinding($name, $want['binding'])
            : $this->codec->writeValue($name, $want['value'], $this->fieldType($component, $key), $indent);
    }

    /** Indentation for a multi-line value, from the whitespace before it. */
    protected function attributeIndent(string $space, string $fallback): string
    {
        return preg_match('/\n([ \t]*)$/', $space, $m) ? $m[1] : $fallback . '    ';
    }

    protected function fieldType(ComponentData $component, string $key): string
    {
        return (string) ($component->fields[$key]['type'] ?? 'text');
    }

    protected function fieldDefault(ComponentData $component, string $key): mixed
    {
        $config = $component->fields[$key] ?? [];

        return $config['default'] ?? (($config['type'] ?? 'text') === 'repeater' ? [] : '');
    }

    /* ------------------------------------------------------------ */
    /*  Collections and site data                                    */
    /* ------------------------------------------------------------ */

    protected function planCollections(array $collections, array $state, array &$writes, array &$deletes, array &$next): void
    {
        $recorded = $state['collections'] ?? [];

        foreach ($recorded as $name => $entry) {
            if (!isset($collections[$name])) {
                $base = SitePaths::relative(SitePaths::data('collections/' . $entry['source']));
                $deletes[$base . '.json'] = true;
                $deletes[$base . '.yml'] = true;
            }
        }

        foreach ($collections as $name => $doc) {
            $source = $this->collectionSource($doc);
            $json = SitePaths::relative(SitePaths::data('collections/' . $source . '.json'));
            $yml = SitePaths::relative(SitePaths::data('collections/' . $source . '.yml'));

            $rows = array_map(function (array $row) use ($doc) {
                if (!empty($doc['studio_ids'])) {
                    unset($row['id']);
                } elseif (!empty($doc['int_ids']) && is_numeric($row['id'] ?? null)) {
                    $row['id'] = (int) $row['id'];
                }

                return $row;
            }, array_values($doc['rows'] ?? []));

            $text = is_file(base_path($json)) ? (string) file_get_contents(base_path($json)) : null;

            if ($text === null || json_decode($text, true) !== $rows) {
                $writes[$json] = JsonDocument::update($text, $rows);
            }

            $schema = $this->schemaYaml($doc, $source, $rows, base_path($yml));

            if ($schema !== null) {
                $writes[$yml] = $schema;
            }

            $next['collections'][$name] = ['source' => $source, 'path' => $json];
        }
    }

    /**
     * The collection's `.yml`, or null when the file on disk already says
     * the same thing (or none is needed because the schema is inferable).
     */
    protected function schemaYaml(array $doc, string $source, array $rows, string $path): ?string
    {
        $fields = $this->collections->normaliseFields($doc['fields'] ?? []);
        $existing = is_file($path) ? $this->readYaml($path) : null;
        $defaultTitle = Str::headline($source);
        $title = ($doc['title'] ?? '') !== '' ? $doc['title'] : $defaultTitle;

        if ($existing !== null) {
            $declared = $this->collections->normaliseFields(is_array($existing['fields'] ?? null) ? $existing['fields'] : []);
            $existingTitle = is_string($existing['title'] ?? null) && $existing['title'] !== '' ? $existing['title'] : $defaultTitle;

            if ($declared == $fields && $existingTitle === $title) {
                return null;
            }
        } elseif ($title === $defaultTitle && $fields == $this->collections->normaliseFields(SiteReader::inferFields($rows))) {
            return null;
        }

        $out = [];

        if ($title !== $defaultTitle) {
            $out['title'] = $title;
        }

        $out['fields'] = [];

        foreach ($fields as $key => $config) {
            $simple = ($config['label'] ?? Str::headline($key)) === Str::headline($key) && !isset($config['options']) && !isset($config['description']);
            $out['fields'][$key] = $simple ? $config['type'] : array_filter($config, fn ($v) => $v !== null);
        }

        return "# Field types for the Content panel — which control edits each column.\n"
            . Yaml::dump($out, 4, 4);
    }

    protected function collectionSource(array $doc): string
    {
        $source = $doc['source'] ?? null;

        if (is_string($source) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $source)) {
            return $source;
        }

        return Str::camel($doc['name'] ?? 'collection');
    }

    protected function planSite(?array $site, array &$writes): void
    {
        if ($site === null) {
            return;
        }

        $path = SitePaths::relative(SitePaths::data('site.json'));
        $data = $site['data'] ?? [];
        $text = is_file(base_path($path)) ? (string) file_get_contents(base_path($path)) : null;
        $current = $text !== null ? json_decode($text, true) : null;

        if ($current !== $data && !($current === null && $data === [])) {
            $writes[$path] = $data === [] ? "{}\n" : JsonDocument::update($text, $data);
        }
    }

    /* ------------------------------------------------------------ */
    /*  Manifest                                                     */
    /* ------------------------------------------------------------ */

    protected function manifest(array $docs): array
    {
        $manifest = SiteManifest::read();
        $site = $docs['site'] ?? [];

        $manifest['template'] = $site['template'] ?? $manifest['template'];
        $manifest['home'] = $site['home_slug'] ?? $manifest['home'];

        // Settings for a hand-written page (its SEO tags, say) belong to the
        // developer: kept for as long as the page file exists — the home
        // page's file being index.blade.php.
        $manifest['pages'] = array_filter(
            $manifest['pages'],
            fn ($settings, $slug) => !isset($docs['pages'][$slug])
                && is_file(SitePaths::pages(($slug === $manifest['home'] ? 'index' : $slug) . '.blade.php')),
            ARRAY_FILTER_USE_BOTH
        );
        $manifest['layouts'] = [];
        $manifest['blocks'] = [];

        foreach ($docs['pages'] ?? [] as $slug => $doc) {
            $meta = $doc['meta'] ?? [];
            $seoDescription = (string) ($meta['seo_description'] ?? '');
            unset($meta['seo_title'], $meta['seo_description']);

            // The layout tag carries the description, unless a separate SEO
            // description took its place — then the page's own is kept here.
            $manifest['pages'][$slug] = array_filter([
                'title' => $doc['title'] ?? null,
                'description' => $seoDescription !== '' ? (string) ($doc['description'] ?? '') : null,
                'order' => $doc['order'] ?? null,
                'meta' => $meta !== [] ? $meta : null,
                'previous_slugs' => !empty($doc['previous_slugs']) ? array_values($doc['previous_slugs']) : null,
            ], fn ($v) => $v !== null);
        }

        foreach ($docs['layouts'] ?? [] as $slug => $doc) {
            if (($doc['name'] ?? '') !== '' && $doc['name'] !== Str::headline($slug)) {
                $manifest['layouts'][$slug] = ['name' => $doc['name']];
            }
        }

        foreach ($docs['blocks'] ?? [] as $slug => $doc) {
            if (($doc['name'] ?? '') !== '' && $doc['name'] !== Str::headline($slug)) {
                $manifest['blocks'][$slug] = ['name' => $doc['name']];
            }
        }

        ksort($manifest['pages']);

        return $manifest;
    }

    /* ------------------------------------------------------------ */
    /*  Helpers                                                      */
    /* ------------------------------------------------------------ */

    /** Is a file still exactly what the mirror last recorded? */
    protected function unchanged(string $relative, array $state): bool
    {
        $recorded = $state['hashes'][$relative] ?? null;

        return $recorded !== null && is_file(base_path($relative)) && md5_file(base_path($relative)) === $recorded;
    }

    /** Whether a hand-written (code) page still uses a component. */
    protected function referencedByCode(string $tag): bool
    {
        $pattern = '/<x-' . preg_quote($tag, '/') . '(?=[\s\/>])/';
        $iterator = is_dir(SitePaths::views())
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(SitePaths::views(), \FilesystemIterator::SKIP_DOTS))
            : [];

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php') && preg_match($pattern, (string) file_get_contents($file->getPathname()))) {
                // Studio's own pages are rewritten in the same pass, so only
                // a file outside the managed set counts.
                if (!$this->isStudioFile($file->getPathname())) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isStudioFile(string $absolute): bool
    {
        $pages = SitePaths::pages();

        if (!str_starts_with($absolute, $pages . '/') || str_contains(substr($absolute, strlen($pages) + 1), '/')) {
            return false;
        }

        $source = (string) file_get_contents($absolute);

        return SiteReader::pageStructure($source) !== null;
    }

    protected function indentBlock(string $text, string $indent): string
    {
        return preg_replace('/^(?=.)/m', $indent, $text) ?? $text;
    }

    protected function dedent(string $text): string
    {
        return preg_replace('/^    /m', '', $text) ?? $text;
    }

    protected function readYaml(string $path): array
    {
        try {
            $parsed = Yaml::parseFile($path);
        } catch (\Throwable) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }

    protected function put(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));

        $temp = dirname($path) . '/.' . basename($path) . '.' . Str::random(6) . '.tmp';
        File::put($temp, $contents);
        File::move($temp, $path);
    }
}
