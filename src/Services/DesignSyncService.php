<?php

namespace Designer\Studio\Services;

use Designer\Studio\DataTransferObjects\ComponentData;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Support\SitePaths;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps the section library in step with the installed site's components.
 *
 * A component under `resources/designer/views/components` counts as a
 * section when a `.yml` sits beside its `.blade.php`: that file is the
 * template's own contract for what an editor may change, which is exactly
 * what Studio's inspector needs. Layouts (document shells) and global blocks
 * (Studio's own shared instances) live in folders of their own and are not
 * sections.
 *
 * The library mirrors that set exactly: new files are added, edited ones
 * re-read, and entries whose files are gone are removed — so the Add
 * Section picker only ever offers what the site can render.
 */
class DesignSyncService
{
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

    /** Field types the templates use that Studio's inspector spells differently. */
    protected const FIELD_TYPE_MAP = [
        'number' => 'text',
        'richtext' => 'textarea',
        'color' => 'colorpicker',
        'boolean' => 'toggle',
        'checkbox' => 'toggle',
    ];

    /** Folders under components/ that never hold sections. */
    protected const EXCLUDED = [SitePaths::LAYOUTS, SitePaths::BLOCKS];

    public function __construct(
        protected ComponentRepository $components
    ) {}

    /** Where section components live. */
    public function getDesignsPath(): string
    {
        return SitePaths::components();
    }

    /**
     * Every section path, relative to the components folder and without the
     * extension (`sections/hero`, `nav`), sorted.
     *
     * @return string[]
     */
    public function discoverDesigns(): array
    {
        $base = $this->getDesignsPath();

        if (!is_dir($base)) {
            return [];
        }

        $designs = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($base) + 1, -strlen('.blade.php'));

            if (in_array(explode('/', $relative)[0], self::EXCLUDED, true)) {
                continue;
            }

            if (is_file($base . '/' . $relative . '.yml')) {
                $designs[] = $relative;
            }
        }

        sort($designs);

        return $designs;
    }

    /** The source path (relative, no extension) of a library section. */
    public function findDesignPath(string $name): ?string
    {
        $component = $this->components->find($name);

        if ($component && $component->path !== '' && is_file($this->getDesignsPath() . '/' . $component->path . '.blade.php')) {
            return $component->path;
        }

        foreach ($this->discoverDesigns() as $relative) {
            if ($this->nameFor($relative) === $name) {
                return $relative;
            }
        }

        return null;
    }

    /** Absolute paths of a section's two source files. */
    public function sourceFiles(string $name): ?array
    {
        $relative = $this->findDesignPath($name);

        if ($relative === null) {
            return null;
        }

        $base = $this->getDesignsPath() . '/' . $relative;

        return ['blade' => $base . '.blade.php', 'yaml' => $base . '.yml'];
    }

    /**
     * `sections/hero` → `sections-hero`. Library names travel in URLs and
     * data attributes, so they are kept to lowercase letters, digits, and
     * dashes; the tag keeps the file's own spelling.
     */
    public function nameFor(string $relative): string
    {
        return collect(explode('/', $relative))
            ->map(fn ($segment) => Str::slug(Str::snake($segment, '-')))
            ->filter()
            ->implode('-');
    }

    public function loadDesign(string $relative): array
    {
        $base = $this->getDesignsPath() . '/' . $relative;
        $meta = $this->readYaml($base . '.yml');
        $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : [];

        return [
            'name' => $this->nameFor($relative),
            'tag' => str_replace('/', '.', $relative),
            'path' => $relative,
            'title' => (string) ($meta['title'] ?? Str::headline(basename($relative))),
            'description' => (string) ($meta['description'] ?? ''),
            'category' => (string) ($meta['category'] ?? $this->category($relative)),
            'tags' => [],
            'html' => (string) file_get_contents($base . '.blade.php'),
            'fields' => $this->fields($fields),
            'preview_variables' => [],
            'source' => 'designer',
            // Always present so removing the key from the yml clears the flag
            'fixed' => (bool) ($meta['fixed'] ?? false),
        ];
    }

    /**
     * Make the library match the installed site's sections.
     *
     * @return array{total: int, created: int, updated: int, removed: int}
     */
    public function syncAll(): array
    {
        $created = 0;
        $updated = 0;
        $removed = 0;
        $seen = [];

        foreach ($this->discoverDesigns() as $relative) {
            $data = $this->loadDesign($relative);

            // Two files whose names flatten alike (`sections/foo-bar` and
            // `sections-foo/bar`) must not overwrite one another.
            while (isset($seen[$data['name']])) {
                $data['name'] .= '-2';
            }

            $seen[$data['name']] = true;
            $existing = $this->components->find($data['name']);

            if (!$existing) {
                $this->components->create($data);
                $created++;
            } elseif ($this->isDirty($existing, $data)) {
                $this->components->update($data['name'], $data);
                $updated++;
            }
        }

        // Anything else in the library belongs to a site that is no longer
        // installed (or to Studio's retired packaged library).
        foreach ($this->components->all() as $component) {
            if (!isset($seen[$component->name])) {
                $this->components->delete($component->name);
                $removed++;
            }
        }

        return [
            'total' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }

    protected function category(string $relative): string
    {
        $name = strtolower(basename($relative));

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

            $translated[$key] = $config;
        }

        return $translated;
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

    protected function isDirty(ComponentData $existing, array $data): bool
    {
        return $existing->html !== $data['html']
            || $existing->tag !== $data['tag']
            || $existing->path !== $data['path']
            || $existing->source !== $data['source']
            || $existing->fixed !== $data['fixed']
            || $existing->title !== $data['title']
            || $existing->description !== $data['description']
            || $existing->category !== $data['category']
            || $existing->fields != $data['fields']
            || $existing->preview_variables != $data['preview_variables']
            || $existing->tags != $data['tags'];
    }
}
