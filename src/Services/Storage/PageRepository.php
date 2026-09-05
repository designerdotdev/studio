<?php

namespace Designer\Studio\Services\Storage;

use Designer\Studio\DataTransferObjects\PageData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PageRepository
{
    public function __construct(
        protected StudioStorage $storage
    ) {}

    /**
     * Every page, in Pages-panel order: explicitly ordered pages first
     * (by `order`), then the rest by title.
     */
    public function all(): Collection
    {
        $slugs = $this->storage->list('pages');

        return collect($slugs)
            ->map(fn($slug) => $this->find($slug))
            ->filter()
            ->sortBy(fn(PageData $p) => [$p->order ?? PHP_INT_MAX, mb_strtolower($p->title)])
            ->values();
    }

    /** Persist a new order: the position of each slug in the list */
    public function reorder(array $slugs): void
    {
        foreach (array_values($slugs) as $position => $slug) {
            $existing = $this->storage->read("pages/{$slug}.json");

            if ($existing && ($existing['order'] ?? null) !== $position) {
                $existing['order'] = $position;
                $this->storage->write("pages/{$slug}.json", $existing);
            }
        }
    }

    public function find(string $slug): ?PageData
    {
        $data = $this->storage->read("pages/{$slug}.json");

        if (!$data) {
            return null;
        }

        return PageData::fromArray($data);
    }

    public function create(array $data): PageData
    {
        $slug = Str::slug(($data['slug'] ?? '') ?: ($data['title'] ?? 'untitled'));

        if ($slug === '') {
            $slug = 'untitled';
        }

        // Ensure unique slug
        $originalSlug = $slug;
        $counter = 1;
        while ($this->storage->exists("pages/{$slug}.json")) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $pageData = [
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'title' => $data['title'] ?? 'Untitled Page',
            'description' => $data['description'] ?? '',
            // null = follow studio.default_layout at export time
            'layout' => $data['layout'] ?? null,
            // Studio layout (shared header/footer sections), null = none
            'layout_ref' => $data['layout_ref'] ?? null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'meta' => $data['meta'] ?? [],
            'components' => $data['components'] ?? [],
        ];

        $this->storage->write("pages/{$slug}.json", $pageData);

        return PageData::fromArray($pageData);
    }

    public function update(string $slug, array $data): ?PageData
    {
        $existing = $this->storage->read("pages/{$slug}.json");

        if (!$existing) {
            return null;
        }

        // Handle slug change (slugify, keep unique, move the file)
        $newSlug = Str::slug($data['slug'] ?? $slug) ?: $slug;

        if ($newSlug !== $slug) {
            $base = $newSlug;
            $counter = 1;
            while ($this->storage->exists("pages/{$newSlug}.json")) {
                $newSlug = $base . '-' . $counter++;
            }
            $data['slug'] = $newSlug;

            // Remember retired slugs so published URLs can 301 to the new one
            $data['previous_slugs'] = array_values(array_diff(
                array_unique([...($existing['previous_slugs'] ?? []), $slug]),
                [$newSlug]
            ));

            $this->storage->delete("pages/{$slug}.json");
        } else {
            $data['slug'] = $slug;
        }

        $updated = array_merge($existing, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->storage->write("pages/{$newSlug}.json", $updated);

        return PageData::fromArray($updated);
    }

    public function delete(string $slug): bool
    {
        return $this->storage->delete("pages/{$slug}.json");
    }

    /**
     * Duplicate a page (components get fresh instance ids).
     */
    public function duplicate(string $slug): ?PageData
    {
        $existing = $this->storage->read("pages/{$slug}.json");

        if (!$existing) {
            return null;
        }

        $components = array_map(function ($comp) {
            $comp['id'] = (string) Str::uuid();

            return $comp;
        }, $existing['components'] ?? []);

        return $this->create([
            'title' => ($existing['title'] ?? 'Untitled') . ' Copy',
            'slug' => ($existing['slug'] ?? $slug) . '-copy',
            'description' => $existing['description'] ?? '',
            'layout' => $existing['layout'] ?? null,
            'layout_ref' => $existing['layout_ref'] ?? null,
            'meta' => $existing['meta'] ?? [],
            'components' => $components,
        ]);
    }

    /**
     * Duplicate a section instance in place (inserted directly below the original).
     */
    public function duplicateComponent(string $pageSlug, string $componentId): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = collect($page['components'] ?? [])->sortBy('order')->values()->toArray();
        $index = null;

        foreach ($components as $i => $comp) {
            if ($comp['id'] === $componentId) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return null;
        }

        $copy = $components[$index];
        $copy['id'] = (string) Str::uuid();

        array_splice($components, $index + 1, 0, [$copy]);

        foreach ($components as $i => &$comp) {
            $comp['order'] = $i;
        }
        unset($comp);

        return $this->update($pageSlug, ['components' => $components]);
    }

    /**
     * Toggle a section's visibility without removing it from the page.
     */
    public function setComponentHidden(string $pageSlug, string $componentId, bool $hidden): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = collect($page['components'] ?? [])->map(function ($comp) use ($componentId, $hidden) {
            if ($comp['id'] === $componentId) {
                $comp['hidden'] = $hidden;
            }

            return $comp;
        })->toArray();

        return $this->update($pageSlug, ['components' => $components]);
    }

    public function addComponent(string $pageSlug, string $componentRef, array $variables = [], ?int $insertAtIndex = null): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = $page['components'] ?? [];

        // Sort existing by order
        usort($components, fn($a, $b) => $a['order'] <=> $b['order']);

        $newComponent = [
            'id' => (string) Str::uuid(),
            'component_ref' => $componentRef,
            'order' => 0,
            'variables' => $variables,
        ];

        // A repeater declared with `source: collections.<name>` starts
        // bound to that collection when it exists in this site.
        if ($bindings = self::defaultBindings($componentRef)) {
            $newComponent['bindings'] = $bindings;
        }

        if ($insertAtIndex !== null && $insertAtIndex >= 0 && $insertAtIndex <= count($components)) {
            // Insert at specific position
            array_splice($components, $insertAtIndex, 0, [$newComponent]);
        } else {
            // Append at end
            $components[] = $newComponent;
        }

        // Re-number orders sequentially
        foreach ($components as $i => &$comp) {
            $comp['order'] = $i;
        }
        unset($comp);

        return $this->update($pageSlug, ['components' => $components]);
    }

    /**
     * The `{field: "collections.<name>"}` map a freshly added section should
     * start with: every repeater whose yml declares a `source` naming a
     * collection that exists. Shared by pages and layouts.
     */
    public static function defaultBindings(string $componentRef): array
    {
        $component = app(ComponentRepository::class)->find($componentRef);

        if (!$component) {
            return [];
        }

        $collections = app(CollectionRepository::class);
        $bindings = [];

        foreach ($component->fields as $key => $config) {
            if (($config['type'] ?? '') !== 'repeater') {
                continue;
            }

            $name = \Designer\Studio\Services\CollectionBinder::collectionName($config['source'] ?? null);

            if ($name !== null && $collections->exists($name)) {
                $bindings[$key] = 'collections.' . $name;
            }
        }

        return $bindings;
    }

    public function updateComponentVariables(string $pageSlug, string $componentId, array $variables): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = collect($page['components'] ?? [])->map(function ($comp) use ($componentId, $variables) {
            if ($comp['id'] === $componentId) {
                $comp['variables'] = array_merge($comp['variables'] ?? [], $variables);
            }

            return $comp;
        })->toArray();

        return $this->update($pageSlug, ['components' => $components]);
    }

    /**
     * Replace an instance's collection bindings (`{field: "collections.x"}`).
     * Passing an empty array unbinds every field; `$variables`, when given,
     * is merged at the same time (used to keep a copy of the rows on unbind).
     */
    public function updateComponentBindings(string $pageSlug, string $componentId, array $bindings, ?array $variables = null): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = collect($page['components'] ?? [])->map(function ($comp) use ($componentId, $bindings, $variables) {
            if ($comp['id'] === $componentId) {
                if ($bindings === []) {
                    unset($comp['bindings']);
                } else {
                    $comp['bindings'] = $bindings;
                }

                if ($variables !== null) {
                    $comp['variables'] = array_merge($comp['variables'] ?? [], $variables);
                }
            }

            return $comp;
        })->toArray();

        return $this->update($pageSlug, ['components' => $components]);
    }

    public function removeComponent(string $pageSlug, string $componentId): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = collect($page['components'] ?? [])
            ->reject(fn($comp) => $comp['id'] === $componentId)
            ->values()
            ->toArray();

        return $this->update($pageSlug, ['components' => $components]);
    }

    public function reorderComponents(string $pageSlug, array $orderedIds): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $componentsById = collect($page['components'] ?? [])->keyBy('id');

        $reordered = collect($orderedIds)->map(function ($id, $index) use ($componentsById) {
            $comp = $componentsById->get($id);
            if ($comp) {
                $comp['order'] = $index;
            }

            return $comp;
        })->filter()->values()->toArray();

        return $this->update($pageSlug, ['components' => $reordered]);
    }
}
