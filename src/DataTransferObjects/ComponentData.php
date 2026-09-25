<?php

namespace Designer\Studio\DataTransferObjects;

class ComponentData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly string $category,
        public readonly array $tags,
        public readonly string $version,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly string $html,
        public readonly array $fields,
        public readonly array $preview_variables,
        public readonly string $source,
        public readonly bool $fixed = false,
        /** The Blade tag a page writes for it, e.g. `sections.hero` for <x-sections.hero> */
        public readonly string $tag = '',
        /** Source file, relative to resources/designer/views/components (no extension) */
        public readonly string $path = '',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            title: $data['title'] ?? 'Untitled',
            description: $data['description'] ?? '',
            category: $data['category'] ?? 'general',
            tags: $data['tags'] ?? [],
            version: $data['version'] ?? '1.0.0',
            created_at: $data['created_at'] ?? now()->toIso8601String(),
            updated_at: $data['updated_at'] ?? now()->toIso8601String(),
            html: $data['html'] ?? '',
            fields: $data['fields'] ?? [],
            preview_variables: $data['preview_variables'] ?? [],
            source: $data['source'] ?? 'local',
            fixed: (bool) ($data['fixed'] ?? false),
            tag: (string) ($data['tag'] ?? ''),
            path: (string) ($data['path'] ?? ''),
        );
    }

    /**
     * Resolve the full variable set for this component.
     *
     * Merges stored instance values over field defaults, normalising by field
     * type so every declared field always resolves to a usable value
     * (repeaters always resolve to arrays, scalars to strings/bools).
     *
     * @param array $overrides Stored instance variables (take precedence)
     * @param bool $usePreviewDefaults Prefer preview_variables over field defaults
     */
    public function resolveVariables(array $overrides = [], bool $usePreviewDefaults = false): array
    {
        $vars = [];

        foreach ($this->fields as $key => $config) {
            $type = $config['type'] ?? 'text';

            $default = $config['default'] ?? ($type === 'repeater' ? [] : '');

            if ($usePreviewDefaults && array_key_exists($key, $this->preview_variables)) {
                $default = $this->preview_variables[$key];
            }

            $value = array_key_exists($key, $overrides) ? $overrides[$key] : $default;

            if ($type === 'repeater') {
                $value = is_array($value) ? array_values($value) : [];

                if (!empty($config['nestable'])) {
                    $value = array_map(function ($item) {
                        if (is_array($item) && !isset($item['children'])) {
                            $item['children'] = [];
                        }

                        return $item;
                    }, $value);
                }
            }

            $vars[$key] = $value;
        }

        // Values with no field behind them still reach the section. An
        // imported template binds page data straight to a component prop
        // (`:items="$logos"`) without ever declaring it as editable, and
        // dropping those would empty half the page.
        foreach ($overrides as $key => $value) {
            if (!array_key_exists($key, $vars)) {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'html' => $this->html,
            'fields' => $this->fields,
            'preview_variables' => $this->preview_variables,
            'source' => $this->source,
            'fixed' => $this->fixed,
            'tag' => $this->tag,
            'path' => $this->path,
        ];
    }
}
