<?php

namespace Designer\Studio\Services\Site;

use Designer\Studio\DataTransferObjects\ComponentData;
use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the installed site (resources/designer) into Studio's documents.
 *
 *   views/pages/<slug>.blade.php          a page — when it is a composition:
 *                                         one <x-layouts.*> wrapping nothing
 *                                         but section tags, whitespace, and
 *                                         comments (index.blade.php is home)
 *   views/components/layouts/*.blade.php  a layout — sections before and
 *                                         after {{ $slot }} in the <body>
 *   views/components/blocks/*.blade.php   a global block — one section tag
 *   data/collections/<name>.json (+.yml)  a collection
 *   data/site.json                        the site document's data
 *   designer.json                         titles, SEO, order, block names
 *
 * Pages that are anything else — the 404 page, a `[posts.slug]` dynamic
 * page, a page with hand-written markup between its sections, a nested page
 * — are code pages: served by the runtime, edited in Code mode, and never
 * touched by the editor.
 *
 * Instance ids are stable across reads: each instance takes the id of the
 * one at the same position in the previous document when the section
 * matches, so re-reading an unchanged file changes nothing.
 */
class SiteReader
{
    /** @var array<string, ComponentData> tag => component */
    protected array $library = [];

    /** @var array<string, string> block slugs present on disk */
    protected array $blockSlugs = [];

    protected AttributeCodec $codec;

    public function __construct(
        protected ComponentRepository $components,
        protected CollectionRepository $collectionRepository,
    ) {}

    /**
     * @param  array  $previous  live documents keyed by tree (pages, layouts,
     *                           blocks, collections: slug => doc; site: doc)
     */
    public function read(array $previous = []): array
    {
        $manifest = SiteManifest::read();

        $this->library = [];
        foreach ($this->components->all() as $component) {
            if ($component->tag !== '') {
                $this->library[$component->tag] = $component;
            }
        }

        $collections = $this->readCollections($previous['collections'] ?? []);
        $this->codec = new AttributeCodec(array_map(fn ($entry) => $entry['doc']['name'], $collections));

        $this->blockSlugs = [];
        foreach ($this->blockFiles() as $slug => $file) {
            $this->blockSlugs[$slug] = $slug;
        }

        $blocks = $this->readBlocks($manifest, $previous['blocks'] ?? []);
        $this->blockSlugs = array_combine(array_keys($blocks), array_keys($blocks)) ?: [];

        $layouts = $this->readLayouts($manifest, $previous['layouts'] ?? []);
        [$pages, $codePages] = $this->readPages($manifest, array_keys($layouts), $previous['pages'] ?? []);

        return [
            'pages' => $pages,
            'layouts' => $layouts,
            'blocks' => $blocks,
            'collections' => $collections,
            'site' => $this->readSite($manifest, $previous['site'] ?? null),
            'code_pages' => $codePages,
            'manifest' => $manifest,
            'tags' => array_keys($this->library),
        ];
    }

    /** The home page's slug: the manifest's choice, else "home". */
    public static function homeSlug(array $manifest): string
    {
        if ($manifest['home'] ?? null) {
            return $manifest['home'];
        }

        // A site with its own home.blade.php keeps that URL for itself
        return is_file(SitePaths::pages('home.blade.php')) ? 'index' : 'home';
    }

    /* ------------------------------------------------------------ */
    /*  Pages                                                        */
    /* ------------------------------------------------------------ */

    /** @return array{0: array, 1: string[]} [pages, code page names] */
    protected function readPages(array $manifest, array $layoutSlugs, array $previous): array
    {
        $home = self::homeSlug($manifest);
        $pages = [];
        $code = [];

        foreach (glob(SitePaths::pages('*.blade.php')) ?: [] as $file) {
            $base = basename($file, '.blade.php');
            $slug = $base === 'index' ? $home : $base;

            $managed = $base !== '404'
                && preg_match('/^[a-z0-9-]+$/', $base) === 1
                && !($base !== 'index' && $slug === $home)
                && ($page = $this->readPage($file, $slug, $base === 'index', $manifest, $layoutSlugs, $previous[$slug] ?? null)) !== null;

            if (!$managed) {
                $code[] = $base;

                continue;
            }

            $pages[$slug] = $page;
        }

        sort($code);

        return [$pages, $code];
    }

    protected function readPage(string $file, string $slug, bool $isHome, array $manifest, array $layoutSlugs, ?array $previous): ?array
    {
        $source = (string) file_get_contents($file);
        $structure = self::pageStructure($source);

        if ($structure === null) {
            return null;
        }

        $layoutRef = null;
        $attributes = [];

        if ($structure['wrapper'] !== null) {
            $layoutRef = substr($structure['wrapper']['ref'], strlen('layouts.'));

            if (!in_array($layoutRef, $layoutSlugs, true)) {
                return null;
            }

            foreach ($structure['wrapper']['items'] as $item) {
                $read = $this->codec->read($item);

                if (in_array($read['key'] ?? null, ['title', 'description'], true)) {
                    if ($read['kind'] !== 'value' || !is_string($read['value'])) {
                        return null; // a computed title is code, not a setting
                    }

                    $attributes[$read['key']] = $read['value'];
                }
            }
        }

        $instances = [];

        foreach (BladeTags::scan($source, $structure['start'], $structure['end']) as $entry) {
            $instance = $this->instance($entry);

            if ($instance === null) {
                return null; // a tag Studio can't edit makes this a code page
            }

            $instances[] = $instance;
        }

        $instances = $this->withIds($instances, $previous['components'] ?? []);

        $settings = $manifest['pages'][$slug] ?? [];
        $title = is_string($settings['title'] ?? null) && $settings['title'] !== ''
            ? $settings['title']
            : ($isHome ? 'Home' : Str::headline($slug));
        $description = is_string($settings['description'] ?? null) ? $settings['description'] : ($attributes['description'] ?? '');

        $meta = is_array($settings['meta'] ?? null) ? $settings['meta'] : [];
        unset($meta['seo_title'], $meta['seo_description']);

        // The layout tag's title is the document <title>: a setting of its
        // own only when it differs from the page's name.
        if (isset($attributes['title']) && $attributes['title'] !== $title) {
            $meta['seo_title'] = $attributes['title'];
        }

        if (isset($attributes['description']) && $attributes['description'] !== $description) {
            $meta['seo_description'] = $attributes['description'];
        }

        $doc = [
            'id' => $previous['id'] ?? (string) Str::uuid(),
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'layout' => null,
            'layout_ref' => $layoutRef,
            'created_at' => $previous['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'meta' => $meta,
            'components' => $instances,
        ];

        if (isset($settings['order']) && is_int($settings['order'])) {
            $doc['order'] = $settings['order'];
        }

        if (!empty($settings['previous_slugs']) && is_array($settings['previous_slugs'])) {
            $doc['previous_slugs'] = array_values(array_filter($settings['previous_slugs'], 'is_string'));
        }

        return [
            'doc' => self::keepIfSame($doc, $previous),
            'path' => SitePaths::relative($file),
            'ids' => array_column($instances, 'id'),
        ];
    }

    /**
     * Where a page's sections sit: inside its `<x-layouts.*>` wrapper, or
     * the whole file for a page without one. Null when the file is not a
     * composition — anything but the wrapper outside it, or anything but
     * section tags and comments inside it.
     *
     * @return array{wrapper: ?array, start: int, end: int}|null
     */
    public static function pageStructure(string $source): ?array
    {
        $entries = BladeTags::scan($source);
        $first = $entries[0] ?? null;

        if (
            $first !== null
            && $first['type'] === 'tag'
            && str_starts_with($first['tag']['ref'], 'layouts.')
            && !$first['tag']['selfClosing']
            && BladeTags::isInert(substr($source, 0, $first['start']))
            && BladeTags::isInert(substr($source, $first['end']))
        ) {
            $tag = $first['tag'];
            // The slot starts right after the opening tag's `>`
            $start = $tag['start'] + BladeTags::openLength($tag);
            $end = $tag['end'] - strlen($tag['closing']);

            return self::inertBetweenTags($source, $start, $end) ? ['wrapper' => $tag, 'start' => $start, 'end' => $end] : null;
        }

        return self::inertBetweenTags($source, 0, strlen($source)) ? ['wrapper' => null, 'start' => 0, 'end' => strlen($source)] : null;
    }

    /** Only component tags (and comments/whitespace) between two offsets. */
    protected static function inertBetweenTags(string $source, int $start, int $end): bool
    {
        $cursor = $start;

        foreach (BladeTags::scan($source, $start, $end) as $entry) {
            if (!BladeTags::isInert(substr($source, $cursor, $entry['start'] - $cursor))) {
                return false;
            }

            $cursor = $entry['end'];
        }

        return BladeTags::isInert(substr($source, $cursor, $end - $cursor));
    }

    /* ------------------------------------------------------------ */
    /*  Layouts                                                      */
    /* ------------------------------------------------------------ */

    protected function readLayouts(array $manifest, array $previous): array
    {
        $layouts = [];

        foreach (glob(SitePaths::components(SitePaths::LAYOUTS . '/*.blade.php')) ?: [] as $file) {
            $slug = basename($file, '.blade.php');

            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                continue;
            }

            $source = (string) file_get_contents($file);
            $regions = self::layoutRegions($source);

            if ($regions === null) {
                continue; // no {{ $slot }}: a component that happens to live here
            }

            $before = [];
            $after = [];

            foreach (BladeTags::scan($source, $regions['before'][0], $regions['before'][1]) as $entry) {
                if ($instance = $this->instance($entry)) {
                    $before[] = $instance;
                }
            }

            foreach (BladeTags::scan($source, $regions['after'][0], $regions['after'][1]) as $entry) {
                if ($instance = $this->instance($entry)) {
                    $after[] = $instance;
                }
            }

            $previousDoc = $previous[$slug] ?? null;
            $previousRegions = app(LayoutRepository::class)->splitComponents($previousDoc['components'] ?? []);

            $before = $this->withIds($before, $previousRegions['before']);
            $after = $this->withIds($after, $previousRegions['after']);

            $components = [
                ...$before,
                ['id' => LayoutRepository::CONTENT_ID, 'component_ref' => LayoutRepository::CONTENT_REF, 'order' => 0, 'variables' => []],
                ...$after,
            ];

            foreach ($components as $i => &$component) {
                $component['order'] = $i;
            }
            unset($component);

            $name = $manifest['layouts'][$slug]['name'] ?? null;

            $doc = [
                'id' => $previousDoc['id'] ?? (string) Str::uuid(),
                'slug' => $slug,
                'name' => is_string($name) && $name !== '' ? $name : Str::headline($slug),
                'created_at' => $previousDoc['created_at'] ?? now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'components' => $components,
            ];

            $layouts[$slug] = [
                'doc' => self::keepIfSame($doc, $previousDoc),
                'path' => SitePaths::relative($file),
                'before' => array_column($before, 'id'),
                'after' => array_column($after, 'id'),
            ];
        }

        ksort($layouts);

        return $layouts;
    }

    /**
     * The two stretches of a layout's <body> that hold its shared sections,
     * split at `{{ $slot }}`. Null when the file has no slot.
     *
     * @return array{before: array{0: int, 1: int}, after: array{0: int, 1: int}, slot: array{0: int, 1: int}}|null
     */
    public static function layoutRegions(string $source): ?array
    {
        $bodyStart = preg_match('/<body\b[^>]*>/i', $source, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] + strlen($m[0][0]) : 0;
        $bodyEnd = preg_match('/<\/body\s*>/i', $source, $m, PREG_OFFSET_CAPTURE, $bodyStart) ? $m[0][1] : strlen($source);

        if (!preg_match('/\{\{\s*\$slot\s*\}\}/', $source, $slot, PREG_OFFSET_CAPTURE, $bodyStart) || $slot[0][1] >= $bodyEnd) {
            return null;
        }

        $slotStart = $slot[0][1];
        $slotEnd = $slotStart + strlen($slot[0][0]);

        return [
            'before' => [$bodyStart, $slotStart],
            'after' => [$slotEnd, $bodyEnd],
            'slot' => [$slotStart, $slotEnd],
        ];
    }

    /* ------------------------------------------------------------ */
    /*  Blocks                                                       */
    /* ------------------------------------------------------------ */

    /** @return array<string, string> slug => file */
    protected function blockFiles(): array
    {
        $files = [];

        foreach (glob(SitePaths::components(SitePaths::BLOCKS . '/*.blade.php')) ?: [] as $file) {
            $slug = basename($file, '.blade.php');

            // A file with its own yml is a section that happens to live here
            if (preg_match('/^[a-z0-9-]+$/', $slug) && !is_file(substr($file, 0, -strlen('.blade.php')) . '.yml')) {
                $files[$slug] = $file;
            }
        }

        ksort($files);

        return $files;
    }

    protected function readBlocks(array $manifest, array $previous): array
    {
        $blocks = [];

        foreach ($this->blockFiles() as $slug => $file) {
            $source = (string) file_get_contents($file);
            $entries = BladeTags::scan($source);

            if (count($entries) !== 1 || $entries[0]['type'] !== 'tag' || str_starts_with($entries[0]['tag']['ref'], 'blocks.')) {
                continue;
            }

            if (!BladeTags::isInert(substr($source, 0, $entries[0]['start'])) || !BladeTags::isInert(substr($source, $entries[0]['end']))) {
                continue;
            }

            $instance = $this->instance($entries[0]);

            if ($instance === null) {
                continue;
            }

            $previousDoc = $previous[$slug] ?? null;
            $name = $manifest['blocks'][$slug]['name'] ?? null;

            $doc = [
                'id' => $previousDoc['id'] ?? (string) Str::uuid(),
                'slug' => $slug,
                'name' => is_string($name) && $name !== '' ? $name : Str::headline($slug),
                'component_ref' => $instance['component_ref'],
                'variables' => $instance['variables'],
                'created_at' => $previousDoc['created_at'] ?? now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            if (!empty($instance['bindings'])) {
                $doc['bindings'] = $instance['bindings'];
            }

            if (isset($instance['slot'])) {
                $doc['slot'] = $instance['slot'];
            }

            $blocks[$slug] = [
                'doc' => self::keepIfSame($doc, $previousDoc),
                'path' => SitePaths::relative($file),
            ];
        }

        return $blocks;
    }

    /* ------------------------------------------------------------ */
    /*  Instances                                                    */
    /* ------------------------------------------------------------ */

    /**
     * A scanned tag as a section instance (or block placement), or null
     * when it is not something Studio edits.
     */
    protected function instance(array $entry): ?array
    {
        $tag = $entry['tag'];
        $hidden = $entry['type'] === 'hidden';

        if (str_starts_with($tag['ref'], 'blocks.')) {
            $slug = substr($tag['ref'], strlen('blocks.'));

            if (!isset($this->blockSlugs[$slug])) {
                return null;
            }

            return array_filter(['block_ref' => $slug, 'hidden' => $hidden ?: null], fn ($v) => $v !== null);
        }

        $component = $this->library[$tag['ref']] ?? null;

        if ($component === null) {
            return null;
        }

        $variables = [];
        $bindings = [];

        foreach ($tag['items'] as $item) {
            $read = $this->codec->read($item, $component->fields);

            if ($read['kind'] === 'value') {
                $variables[$read['key']] = $read['value'];
            } elseif ($read['kind'] === 'binding') {
                $bindings[$read['key']] = $read['binding'];
            }
        }

        $instance = [
            'component_ref' => $component->name,
            'variables' => $variables,
        ];

        if ($bindings !== []) {
            $instance['bindings'] = $bindings;
        }

        if ($hidden) {
            $instance['hidden'] = true;
        }

        if (!$tag['selfClosing'] && trim((string) $tag['slot']) !== '') {
            $instance['slot'] = $tag['slot'];
        }

        return $instance;
    }

    /**
     * Give each instance a stable id and its position. An instance keeps
     * the id of the previous one at the same position when it is the same
     * section; otherwise the first unclaimed previous instance of the same
     * section with the same values; otherwise a new id.
     */
    public function withIds(array $instances, array $previous): array
    {
        $previous = array_values(array_filter($previous, fn ($p) => ($p['id'] ?? null) !== LayoutRepository::CONTENT_ID));
        $claimed = [];

        foreach ($instances as $i => &$instance) {
            $id = null;
            $candidate = $previous[$i] ?? null;

            if ($candidate && self::sameSection($candidate, $instance) && !isset($claimed[$candidate['id']])) {
                $id = $candidate['id'];
            } else {
                foreach ($previous as $other) {
                    if (!isset($claimed[$other['id']]) && self::sameSection($other, $instance) && ($other['variables'] ?? []) == ($instance['variables'] ?? [])) {
                        $id = $other['id'];
                        break;
                    }
                }
            }

            $id ??= (string) Str::uuid();
            $claimed[$id] = true;

            $instance = ['id' => $id] + $instance;
            $instance['order'] = $i;
        }
        unset($instance);

        return $instances;
    }

    protected static function sameSection(array $a, array $b): bool
    {
        return ($a['component_ref'] ?? null) === ($b['component_ref'] ?? null)
            && ($a['block_ref'] ?? null) === ($b['block_ref'] ?? null);
    }

    /* ------------------------------------------------------------ */
    /*  Collections                                                  */
    /* ------------------------------------------------------------ */

    protected function readCollections(array $previous): array
    {
        $collections = [];
        $taken = [];

        foreach (glob(SitePaths::data('collections/*.json')) ?: [] as $file) {
            $source = basename($file, '.json');

            // Only names a page can reach as a variable are collections
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $source)) {
                continue;
            }

            $rows = json_decode((string) file_get_contents($file), true);

            if (!is_array($rows) || !array_is_list($rows) || array_filter($rows, fn ($row) => !is_array($row) || array_is_list($row) && $row !== []) !== []) {
                continue; // not a list of records — nothing the Content panel can edit
            }

            $name = Str::slug(Str::snake($source, '-')) ?: 'collection';
            while (isset($taken[$name])) {
                $name .= '-2';
            }
            $taken[$name] = true;

            $yml = substr($file, 0, -5) . '.yml';
            $meta = is_file($yml) ? $this->readYaml($yml) : [];
            $previousDoc = ($previous[$name]['source'] ?? null) === $source ? $previous[$name] : null;

            $nativeIds = $rows !== [] && array_filter($rows, fn ($row) => !array_key_exists('id', $row)) === [];
            $intIds = $nativeIds && array_filter($rows, fn ($row) => !is_int($row['id'])) === [];

            $doc = [
                'name' => $name,
                'title' => is_string($meta['title'] ?? null) && $meta['title'] !== '' ? $meta['title'] : Str::headline($source),
                'fields' => $this->collectionRepository->normaliseFields(
                    is_array($meta['fields'] ?? null) && $meta['fields'] !== [] ? $meta['fields'] : self::inferFields($rows)
                ),
                'rows' => $nativeIds
                    ? array_map(fn ($row) => ['id' => (string) $row['id']] + $row, $rows)
                    : $this->withRowIds($rows, $previousDoc['rows'] ?? []),
                'source' => $source,
                'studio_ids' => !$nativeIds,
                'created_at' => $previousDoc['created_at'] ?? now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            if ($intIds) {
                $doc['int_ids'] = true;
            }

            $collections[$name] = [
                'doc' => self::keepIfSame($doc, $previousDoc),
                'path' => SitePaths::relative($file),
            ];
        }

        return $collections;
    }

    /** Rows without ids of their own get Studio ids, kept stable by content. */
    protected function withRowIds(array $rows, array $previous): array
    {
        $claimed = [];
        $out = [];

        foreach ($rows as $i => $row) {
            $id = null;

            foreach ($previous as $candidate) {
                $data = $candidate;
                unset($data['id']);

                if (!isset($claimed[$candidate['id'] ?? '']) && $data == $row) {
                    $id = $candidate['id'];
                    break;
                }
            }

            $id ??= 'r' . substr(md5($i . ':' . json_encode($row)), 0, 12);

            while (isset($claimed[$id])) {
                $id .= 'x';
            }

            $claimed[$id] = true;
            $out[] = ['id' => $id] + $row;
        }

        return $out;
    }

    /** Field types for a collection that ships no schema, read off its rows. */
    public static function inferFields(array $rows): array
    {
        $fields = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                if ($key === 'id' || isset($fields[$key])) {
                    continue;
                }

                $fields[$key] = ['type' => self::inferFieldType((string) $key, array_column($rows, $key))];
            }
        }

        return $fields;
    }

    protected static function inferFieldType(string $key, array $values): string
    {
        $samples = array_values(array_filter($values, fn ($v) => is_string($v) && $v !== ''));
        $sample = $samples[0] ?? '';

        return match (true) {
            array_filter($values, 'is_bool') !== [] => 'toggle',
            $samples === [] && array_filter($values, 'is_numeric') !== [] => 'number',
            preg_match('/<(p|h[1-6]|ul|ol|blockquote)\b/i', $sample) === 1 => 'richtext',
            preg_match('/\.(jpe?g|png|gif|webp|avif|svg)(\?|$)/i', $sample) === 1 => 'image',
            preg_match('/^(https?:\/\/|\/|mailto:|#)/', $sample) === 1 => 'url',
            preg_match('/^<svg\b/i', $sample) === 1 => 'textarea',
            max(array_map('strlen', $samples) ?: [0]) > 120 => 'textarea',
            default => 'text',
        };
    }

    /* ------------------------------------------------------------ */
    /*  Site                                                         */
    /* ------------------------------------------------------------ */

    protected function readSite(array $manifest, ?array $previous): array
    {
        $file = SitePaths::data('site.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        $doc = [
            'template' => $manifest['template'] ?? null,
            'data' => is_array($data) ? $data : [],
            'home_slug' => self::homeSlug($manifest),
            'updated_at' => now()->toIso8601String(),
        ];

        return ['doc' => self::keepIfSame($doc, $previous), 'path' => SitePaths::relative($file)];
    }

    /* ------------------------------------------------------------ */

    /** The previous document itself when nothing but timestamps differ. */
    public static function keepIfSame(array $doc, ?array $previous): array
    {
        return $previous !== null && self::normalise($doc) === self::normalise($previous) ? $previous : $doc;
    }

    public static function normalise(?array $doc): ?string
    {
        if ($doc === null) {
            return null;
        }

        unset($doc['updated_at'], $doc['created_at']);

        return json_encode($doc);
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
}
