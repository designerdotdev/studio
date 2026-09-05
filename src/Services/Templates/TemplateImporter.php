<?php

namespace Designer\Studio\Services\Templates;

use Designer\Studio\Services\PublishService;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\SiteRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds a Studio site out of a synced template repository.
 *
 * The two formats already agree on the important things — a page is an
 * ordered list of components, and a component's editable fields are
 * declared in a YAML file beside it — so most of this is translation rather
 * than conversion:
 *
 *   sections            -> library components, prefixed with the template
 *                          slug so several templates can coexist
 *   supporting parts    -> anonymous Blade components in the host app, with
 *                          every tag rewritten to the namespaced path
 *   collections + site  -> the values bound to each section instance
 *   pages/*.blade.php   -> Studio pages
 *   layouts/main        -> a Studio layout, plus the site's fonts and theme
 *   public/*            -> published assets, with their URLs rewritten
 *
 * Pages driven by a collection (`pages/guides/[guides.slug].blade.php`) are
 * reported and skipped: Studio has no dynamic routing, so those need to be
 * rebuilt as ordinary pages by hand.
 */
class TemplateImporter
{
    /** Prefix that keeps imported section names out of the packaged ones. */
    public const NAMESPACE_PREFIX = 'tpl-';

    /** Category for a section whose name matches nothing more specific. */
    protected const DEFAULT_CATEGORY = 'content';

    /**
     * Name fragments that place a section in a library category. Checked in
     * order, so the more specific patterns come first.
     */
    protected const CATEGORY_HINTS = [
        'banners' => ['banner', 'announce'],
        'headers' => ['nav', 'header', 'menu', 'topbar'],
        'heroes' => ['hero', 'masthead', 'identity', 'intro-hero'],
        'logos' => ['logo', 'client', 'brands', 'marquee'],
        'features' => ['feature', 'capabilit', 'service', 'benefit', 'bento', 'deliverable', 'integration', 'comparison', 'step', 'process', 'how-it-works'],
        'stats' => ['stat', 'metric', 'number'],
        'gallery' => ['gallery', 'showcase', 'screenshot', 'portfolio', 'work'],
        'testimonials' => ['testimonial', 'quote', 'review', 'praise'],
        'pricing' => ['pricing', 'plan', 'tier'],
        'faq' => ['faq', 'question'],
        'team' => ['team', 'people', 'staff', 'author'],
        'blog' => ['blog', 'post', 'article', 'journal', 'writing', 'news', 'guide', 'changelog', 'release'],
        'contact' => ['contact', 'map', 'location', 'elsewhere', 'social'],
        'newsletter' => ['newsletter', 'subscribe', 'signup'],
        'cta' => ['cta', 'call-to-action', 'statement', 'closing'],
        'footers' => ['footer'],
        'content' => ['content', 'about', 'story', 'text', 'prose', 'split'],
    ];

    /** Field types the repos use that Studio spells differently. */
    protected const FIELD_TYPE_MAP = [
        'number' => 'text',
        'richtext' => 'textarea',
        'color' => 'colorpicker',
        'boolean' => 'toggle',
        'checkbox' => 'toggle',
    ];

    protected TemplateAssets $assets;

    /** @var array<string, mixed> collection name => rows */
    protected array $collections = [];

    /** Collection file name → Studio collection name (slug), for bindings */
    protected array $collectionNames = [];

    protected array $siteData = [];

    protected array $report = [];

    public function __construct(
        protected TemplateSync $sync,
        protected SectionTagParser $parser,
        protected TemplateChrome $chrome,
        protected ComponentRepository $components,
        protected PageRepository $pages,
        protected LayoutRepository $layouts,
        protected SiteRepository $site,
        protected StudioStorage $storage,
    ) {}

    /**
     * Import one synced template as the current site.
     *
     * @param  bool  $fresh  Replace the existing site rather than adding to it
     * @return array{template: string, pages: string[], sections: int, skipped: array<string,string>, assets: int, layout: ?string, notes: string[]}
     */
    public function import(string $slug, bool $fresh = true): array
    {
        $dir = $this->sync->directory($slug);

        if (!$dir) {
            throw new RuntimeException("Template [{$slug}] is not synced — run studio:templates:sync first.");
        }

        if ($problem = $this->sync->validate($dir)) {
            throw new RuntimeException("Template [{$slug}] is not importable: {$problem}");
        }

        $this->report = [
            'template' => $slug,
            'pages' => [],
            'sections' => 0,
            'skipped' => [],
            'assets' => 0,
            'layout' => null,
            'notes' => [],
        ];

        $this->assets = new TemplateAssets($slug);

        if ($fresh) {
            $this->wipe($slug);
        }

        $this->report['assets'] = $this->assets->publish($dir);

        $this->siteData = $this->readSiteData($dir);
        $this->collections = $this->readCollections($dir);
        $this->collectionNames = $this->importCollections($dir);

        $this->copySupportComponents($slug, $dir);
        $this->importSections($slug, $dir);

        $layoutSlug = $this->importLayout($slug, $dir);
        $this->report['layout'] = $layoutSlug;

        $this->importPages($slug, $dir, $layoutSlug);

        $this->site->save([
            'template' => $slug,
            'data' => $this->siteData,
            ...$this->chrome->extract(
                $this->layoutSource($dir) ?? '',
                $dir,
                $this->siteData,
                $this->assets
            ),
        ]);

        // An imported site starts published. The direction has to follow
        // where the import actually wrote: the editor runs against the
        // draft workspace, the console against the live one, and mirroring
        // the wrong way would erase everything just written.
        if (config('studio.draft_mode', true)) {
            $publisher = app(PublishService::class);
            $publisher->ensureDraftSeeded();

            if ($this->storage->workspace() === 'draft') {
                $publisher->publishAll();
            } else {
                $publisher->discardAll();
            }
        }

        app(\Designer\Studio\Support\WelcomeRoutePruner::class)->claimHome();

        return $this->report;
    }

    /* ------------------------------------------------------------ */
    /*  Clearing out                                                 */
    /* ------------------------------------------------------------ */

    /**
     * Remove the current site so the import lands on an empty slate: pages,
     * layouts, blocks, and any components a previous import of this same
     * template left behind.
     */
    protected function wipe(string $slug): void
    {
        // Both workspaces, not just the active one: a draft left over from
        // the previous site would otherwise survive the import and be
        // mirrored back over it on the next publish.
        $base = $this->storage->getBasePath();

        foreach (StudioStorage::WORKSPACE_TREES as $tree) {
            foreach ([$base . '/' . $tree, $base . '/draft/' . $tree] as $directory) {
                if (!is_dir($directory)) {
                    continue;
                }

                foreach (glob($directory . '/*.json') ?: [] as $document) {
                    File::delete($document);
                }
            }
        }

        // Every previously imported section goes, not only this template's.
        // A site renders under one template's theme, so leaving ten other
        // templates' sections in the picker offers choices that would come
        // out unstyled. The packaged library is untouched.
        foreach ($this->components->all() as $component) {
            if (str_starts_with($component->source, 'template:')) {
                $this->components->delete($component->name);
            }
        }

        $this->purgeInstalled();
    }

    /**
     * Remove everything template imports have written outside the storage
     * tree: published assets under public/, and the supporting Blade
     * components copied into the app. Used by reset and uninstall, which
     * would otherwise leave both behind.
     */
    public function purgeInstalled(): void
    {
        $roots = [
            public_path(trim((string) config('studio.templates.assets_path', 'studio-templates'), '/')),
            (string) config('studio.templates.components_path', resource_path('views/components/studio-templates')),
        ];

        foreach ($roots as $root) {
            if ($root !== '' && is_dir($root)) {
                File::deleteDirectory($root);
            }
        }
    }

    /* ------------------------------------------------------------ */
    /*  Data                                                         */
    /* ------------------------------------------------------------ */

    protected function readSiteData(string $dir): array
    {
        $file = $dir . '/files/resources/data/site.json';

        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode($this->assets->rewrite(file_get_contents($file)), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Turn every collection file into a Studio collection document, so its
     * rows are editable in the Content panel and sections bind to them.
     * A sibling `<name>.yml` with a `fields:` map supplies the schema;
     * otherwise it is inferred from the rows. Returns file name → doc name.
     */
    protected function importCollections(string $dir): array
    {
        $repository = app(\Designer\Studio\Services\Storage\CollectionRepository::class);
        $names = [];

        foreach ($this->collections as $name => $rows) {
            if (!is_array($rows) || $rows === [] || !array_is_list($rows)) {
                continue;
            }

            $rows = array_values(array_filter($rows, 'is_array'));

            if ($rows === []) {
                continue;
            }

            $schema = $this->collectionSchema($dir, $name, $rows);
            $doc = $repository->create(Str::headline($name), $schema, $rows, Str::kebab($name));

            $names[$name] = $doc['name'];
        }

        return $names;
    }

    /** Field types for a collection: the .yml `fields:` map, else inferred */
    protected function collectionSchema(string $dir, string $name, array $rows): array
    {
        $yml = $dir . '/files/resources/data/collections/' . $name . '.yml';
        $declared = is_file($yml) ? ($this->readYaml($yml)['fields'] ?? []) : [];
        $schema = [];

        foreach (array_keys($rows[0]) as $key) {
            if ($key === 'id') {
                continue;
            }

            $type = is_array($declared[$key] ?? null) ? ($declared[$key]['type'] ?? null) : ($declared[$key] ?? null);
            $schema[$key] = ['type' => $type ?: $this->inferFieldType($key, array_column($rows, $key))];
        }

        return $schema;
    }

    protected function inferFieldType(string $key, array $values): string
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

    /**
     * Every file in `resources/data/collections`, keyed by its name. These
     * are what a page's bound attributes point at.
     */
    protected function readCollections(string $dir): array
    {
        $path = $dir . '/files/resources/data/collections';

        if (!is_dir($path)) {
            return [];
        }

        $collections = [];

        foreach (File::files($path) as $file) {
            $name = $file->getFilenameWithoutExtension();
            $extension = strtolower($file->getExtension());

            // A collection shipped as both .json and .yml is the same data
            // twice; the JSON is what the site actually reads.
            if (isset($collections[$name]) && $extension !== 'json') {
                continue;
            }

            $raw = $this->assets->rewrite($file->getContents());

            try {
                $decoded = $extension === 'json'
                    ? json_decode($raw, true)
                    : Yaml::parse($raw);
            } catch (\Throwable) {
                continue;
            }

            if (is_array($decoded)) {
                $collections[$name] = $decoded;
            }
        }

        return $collections;
    }

    /* ------------------------------------------------------------ */
    /*  Components                                                   */
    /* ------------------------------------------------------------ */

    protected function supportPath(string $slug): string
    {
        $base = (string) config('studio.templates.components_path', resource_path('views/components/studio-templates'));

        return $base . '/' . $slug;
    }

    /** The Blade tag prefix the copied components answer to. */
    protected function tagPrefix(string $slug): string
    {
        $base = (string) config('studio.templates.components_path', resource_path('views/components/studio-templates'));
        $relative = Str::after($base, resource_path('views/components/'));

        return str_replace('/', '.', trim($relative, '/')) . '.' . $slug . '.';
    }

    /**
     * Copy every component the template ships into the host app, so a
     * section that composes smaller parts keeps working once the clone is
     * gone. Tags are rewritten to the copied location as they are written.
     */
    protected function copySupportComponents(string $slug, string $dir): void
    {
        $source = $dir . '/files/resources/views/components';

        if (!is_dir($source)) {
            return;
        }

        $target = $this->supportPath($slug);
        File::ensureDirectoryExists($target);

        foreach ($this->bladeFiles($source) as $file) {
            $relative = Str::after($file->getPathname(), $source . '/');
            $destination = $target . '/' . $relative;

            File::ensureDirectoryExists(dirname($destination));
            File::put($destination, $this->prepare((string) file_get_contents($file->getPathname()), $slug));
        }
    }

    /**
     * Register every section the template declares as a library component.
     *
     * A component counts as a section when it ships a YAML file beside it —
     * that file is the template's own contract for what an editor may
     * change, which is exactly what Studio's inspector needs.
     */
    protected function importSections(string $slug, string $dir): void
    {
        $source = $dir . '/files/resources/views/components';

        if (!is_dir($source)) {
            return;
        }

        foreach ($this->bladeFiles($source) as $file) {
            $relative = Str::after($file->getPathname(), $source . '/');
            $withoutExtension = Str::beforeLast($relative, '.blade.php');
            $yml = $source . '/' . $withoutExtension . '.yml';

            // Page layouts describe a whole document, not a section.
            if (!is_file($yml) || str_starts_with($withoutExtension, 'layouts/')) {
                continue;
            }

            $meta = $this->readYaml($yml);
            $name = $this->componentName($slug, $withoutExtension);

            $this->components->create([
                'name' => $name,
                'title' => $meta['title'] ?? Str::headline(basename($withoutExtension)),
                'description' => $meta['description'] ?? '',
                'category' => $this->category($withoutExtension, $meta),
                'tags' => [$slug],
                // Marks the component as belonging to an import, so the
                // design sync leaves it alone.
                'source' => 'template:' . $slug,
                'html' => $this->prepare((string) file_get_contents($file->getPathname()), $slug),
                'fields' => $this->fields(is_array($meta['fields'] ?? null) ? $meta['fields'] : []),
                'fixed' => (bool) ($meta['fixed'] ?? false),
            ]);

            $this->report['sections']++;
        }
    }

    /**
     * `sections/hero` of the monarch template becomes `tpl-monarch-hero`; a
     * component nested elsewhere keeps its folder, so `home/writings`
     * becomes `tpl-monarch-home-writings`.
     *
     * The `tpl-` namespace is what keeps an imported section from colliding
     * with a packaged one of the same name — the packaged Atlas family is
     * already `atlas-hero`, and without this the design sync would quietly
     * overwrite the import on the next editor load.
     */
    protected function componentName(string $slug, string $path): string
    {
        $path = Str::startsWith($path, 'sections/') ? Str::after($path, 'sections/') : $path;

        return self::NAMESPACE_PREFIX . $slug . '-' . str_replace('/', '-', Str::slug(str_replace('/', ' ', $path), '-'));
    }

    protected function category(string $path, array $meta): string
    {
        if (!empty($meta['category'])) {
            return $meta['category'];
        }

        $name = strtolower(basename($path));

        foreach (self::CATEGORY_HINTS as $category => $hints) {
            foreach ($hints as $hint) {
                if (str_contains($name, $hint)) {
                    return $category;
                }
            }
        }

        return self::DEFAULT_CATEGORY;
    }

    /** Translate a template's field declarations into Studio's own. */
    protected function fields(array $fields): array
    {
        $translated = [];

        foreach ($fields as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            $type = $config['type'] ?? 'text';
            $config['type'] = self::FIELD_TYPE_MAP[$type] ?? $type;

            if (isset($config['sub_fields']) && is_array($config['sub_fields'])) {
                $config['sub_fields'] = $this->fields($config['sub_fields']);
            }

            if (isset($config['default'])) {
                $config['default'] = $this->assets->rewriteData($config['default']);
            }

            $translated[$key] = $config;
        }

        return $translated;
    }

    /**
     * Make a template's Blade safe to render from the library: point every
     * component tag at the copied location, move asset URLs, and drop the
     * Vite call, which has no build behind it here.
     */
    protected function prepare(string $html, string $slug): string
    {
        $prefix = $this->tagPrefix($slug);

        $html = preg_replace(
            '/<(\/?)x-(?!slot\b)(?!' . preg_quote(rtrim($prefix, '.'), '/') . '\b)([A-Za-z0-9._:-]+)/',
            '<$1x-' . $prefix . '$2',
            $html
        ) ?? $html;

        $html = preg_replace('/@vite\s*\(.*?\)/s', '', $html) ?? $html;

        return $this->assets->rewrite($html);
    }

    /* ------------------------------------------------------------ */
    /*  Layout                                                       */
    /* ------------------------------------------------------------ */

    protected function layoutSource(string $dir): ?string
    {
        $file = $dir . '/files/resources/views/components/layouts/main.blade.php';

        return is_file($file) ? file_get_contents($file) : null;
    }

    /**
     * Turn the template's page layout into a Studio layout: whatever it
     * renders before its slot becomes the shared header, whatever follows
     * becomes the shared footer.
     */
    protected function importLayout(string $slug, string $dir): ?string
    {
        $source = $this->layoutSource($dir);

        if ($source === null) {
            return null;
        }

        $body = preg_match('/<body\b[^>]*>(.*)<\/body>/is', $source, $match) ? $match[1] : $source;
        $slotAt = strpos($body, '{{ $slot }}');

        if ($slotAt === false) {
            $slotAt = strpos($body, '{{$slot}}') ?: strlen($body);
        }

        $before = $this->instances($slug, substr($body, 0, $slotAt));
        $after = $this->instances($slug, substr($body, $slotAt));

        if ($before === [] && $after === []) {
            return null;
        }

        $layout = $this->layouts->create('Main');

        $components = [
            ...$before,
            [
                'id' => LayoutRepository::CONTENT_ID,
                'component_ref' => LayoutRepository::CONTENT_REF,
                'order' => 0,
                'variables' => [],
            ],
            ...$after,
        ];

        foreach ($components as $i => &$component) {
            $component['order'] = $i;
        }
        unset($component);

        $this->layouts->update($layout['slug'], ['components' => $components]);

        return $layout['slug'];
    }

    /* ------------------------------------------------------------ */
    /*  Pages                                                        */
    /* ------------------------------------------------------------ */

    protected function importPages(string $slug, string $dir, ?string $layoutSlug): void
    {
        $root = $dir . '/files/resources/views/pages';
        $homeSlug = (string) \Designer\Studio\Support\SiteUrls::homeSlug();

        foreach ($this->bladeFiles($root) as $file) {
            $relative = Str::beforeLast(Str::after($file->getPathname(), $root . '/'), '.blade.php');
            $basename = basename($relative);

            if ($basename === '404') {
                continue;
            }

            // `[guides.slug]` is one URL per row of a collection. Studio
            // routes a fixed set of pages, so there is nothing to import.
            if (str_contains($relative, '[')) {
                $this->report['skipped'][$relative] = 'collection-driven page — Studio has no dynamic routes';

                continue;
            }

            $parsed = $this->parser->parsePage((string) file_get_contents($file->getPathname()));
            $attributes = $parsed['layout']['attributes'] ?? [];

            $pageSlug = $relative === 'index' ? $homeSlug : Str::slug(str_replace('/', '-', $relative));

            if ($this->pages->find($pageSlug)) {
                $this->report['skipped'][$relative] = "a page already exists at /{$pageSlug}";

                continue;
            }

            if ($relative !== 'index' && str_contains($relative, '/')) {
                $this->report['notes'][] = "{$relative} became /{$pageSlug} — Studio page URLs are a single segment.";
            }

            $title = $this->literal($attributes['title'] ?? null)
                ?? Str::headline($relative === 'index' ? 'Home' : $basename);

            $this->pages->create([
                'slug' => $pageSlug,
                'title' => $title,
                'description' => $this->literal($attributes['description'] ?? null) ?? '',
                'layout_ref' => $layoutSlug,
                'components' => $this->instances($slug, $parsed['body']),
                'meta' => array_filter([
                    'seo_title' => $this->literal($attributes['title'] ?? null),
                    'seo_description' => $this->literal($attributes['description'] ?? null),
                ]),
            ]);

            $this->report['pages'][] = $pageSlug;
        }
    }

    /* ------------------------------------------------------------ */
    /*  Instances                                                    */
    /* ------------------------------------------------------------ */

    /**
     * Turn a run of component tags into Studio section instances, dropping
     * any that reference a component this template never declared as a
     * section.
     */
    protected function instances(string $slug, string $fragment): array
    {
        $instances = [];

        foreach ($this->parser->parseTags($fragment) as $tag) {
            $name = $this->componentName($slug, str_replace('.', '/', $tag['ref']));
            $component = $this->components->find($name);

            if (!$component) {
                continue;
            }

            $resolved = $this->variables($tag['attributes']);

            $instance = [
                'id' => (string) Str::uuid(),
                'component_ref' => $name,
                'order' => count($instances),
                'variables' => $resolved['variables'],
            ];

            // Collections read as globals (`@foreach ($projects …)`) bind
            // under their own name; bound tag attributes under the prop's.
            $bindings = $this->collectionsUsedBy($component->html) + $resolved['bindings'];

            if ($bindings !== []) {
                $instance['bindings'] = $bindings;
            }

            $instances[] = $instance;
        }

        return $instances;
    }

    /**
     * Collections a section reads without being handed them.
     *
     * A template's own runtime publishes every collection file as a global
     * of the same name, so a section can write `@foreach ($projects as …)`
     * with nothing bound on the tag. Studio keeps a section's data on the
     * instance instead, so the rows are copied onto it here — which also
     * makes them editable afterwards rather than fixed in a data file.
     */
    protected function collectionsUsedBy(string $html): array
    {
        $used = [];

        foreach ($this->collections as $name => $rows) {
            if (preg_match('/\$' . preg_quote($name, '/') . '\b/', $html) && isset($this->collectionNames[$name])) {
                $used[$name] = 'collections.' . $this->collectionNames[$name];
            }
        }

        return $used;
    }

    /**
     * Resolve a tag's attributes into stored values. Literals are taken as
     * written; a bound attribute names a collection or a key of the site
     * document, and is replaced with the data it points at.
     */
    protected function variables(array $attributes): array
    {
        $variables = [];
        $bindings = [];

        foreach ($attributes as $key => $attribute) {
            if (array_key_exists('value', $attribute)) {
                $variables[$key] = $attribute['value'];

                continue;
            }

            $expression = trim($attribute['bind'] ?? '');

            // `:items="$guides"` — a whole collection: bind it so the rows
            // stay editable in the Content panel rather than freezing here.
            if (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)$/', $expression, $m) && isset($this->collectionNames[$m[1]])) {
                $bindings[$key] = 'collections.' . $this->collectionNames[$m[1]];

                continue;
            }

            $resolved = $this->resolveBinding($expression);

            if ($resolved !== null) {
                $variables[$key] = $resolved;
            }
        }

        return ['variables' => $variables, 'bindings' => $bindings];
    }

    /**
     * `$logos` is a collection; `$site->social_links` is a key of the site
     * document. Anything else is an expression Studio cannot evaluate
     * without the template's own runtime, so it is left to the section's
     * own default.
     */
    protected function resolveBinding(string $expression): mixed
    {
        $expression = trim($expression);

        if ($expression === '' || !str_starts_with($expression, '$')) {
            return null;
        }

        $path = explode('->', str_replace('?->', '->', substr($expression, 1)));
        $root = array_shift($path);

        $value = $root === 'site' ? $this->siteData : ($this->collections[$root] ?? null);

        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /* ------------------------------------------------------------ */
    /*  Small helpers                                                */
    /* ------------------------------------------------------------ */

    protected function literal(?array $attribute): ?string
    {
        return $attribute !== null && array_key_exists('value', $attribute)
            ? $attribute['value']
            : null;
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

    /** @return \SplFileInfo[] every .blade.php under a directory, sorted */
    protected function bladeFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[$file->getPathname()] = $file;
            }
        }

        ksort($files);

        return array_values($files);
    }
}
