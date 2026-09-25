<?php

namespace Designer\Studio\Services\Storage;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Global blocks — synced section instances.
 *
 * A block owns one section instance's data (component_ref + variables) in
 * storage/studio/blocks/. Pages and layouts embed lightweight placements
 * (`{id, block_ref, order, hidden}`) that all render from the block, so
 * editing any placement updates every page that uses it. Placement-local
 * concerns (position, hidden) stay on the placement.
 */
class BlockRepository
{
    public function __construct(
        protected StudioStorage $storage
    ) {}

    /* ------------------------------------------------------------ */
    /*  CRUD                                                         */
    /* ------------------------------------------------------------ */

    public function all(): Collection
    {
        return collect($this->storage->list('blocks'))
            ->map(fn ($slug) => $this->find($slug))
            ->filter()
            ->sortBy('name')
            ->values();
    }

    public function find(string $slug): ?array
    {
        return $this->storage->read("blocks/{$slug}.json");
    }

    /**
     * Create a block from an existing section instance's data.
     */
    public function create(string $name, string $componentRef, array $variables = []): array
    {
        $slug = Str::slug($name) ?: 'block';

        $base = $slug;
        $counter = 1;
        while ($this->storage->exists("blocks/{$slug}.json")) {
            $slug = $base . '-' . $counter++;
        }

        $block = [
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => $name ?: 'Block',
            'component_ref' => $componentRef,
            'variables' => $variables,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->storage->write("blocks/{$slug}.json", $block);

        return $block;
    }

    public function update(string $slug, array $data): ?array
    {
        $existing = $this->find($slug);

        if (!$existing) {
            return null;
        }

        $updated = array_merge($existing, $data, [
            'slug' => $slug,
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->storage->write("blocks/{$slug}.json", $updated);

        return $updated;
    }

    public function updateVariables(string $slug, array $variables): ?array
    {
        $block = $this->find($slug);

        if (!$block) {
            return null;
        }

        return $this->update($slug, [
            'variables' => array_merge($block['variables'] ?? [], $variables),
        ]);
    }

    /** Replace a block's collection bindings; [] unbinds (see PageRepository) */
    public function updateBindings(string $slug, array $bindings, ?array $variables = null): ?array
    {
        $block = $this->find($slug);

        if (!$block) {
            return null;
        }

        $data = ['bindings' => $bindings];

        if ($variables !== null) {
            $data['variables'] = array_merge($block['variables'] ?? [], $variables);
        }

        return $this->update($slug, $data);
    }

    public function delete(string $slug): bool
    {
        return $this->storage->delete("blocks/{$slug}.json");
    }

    /* ------------------------------------------------------------ */
    /*  Hydration                                                    */
    /* ------------------------------------------------------------ */

    /**
     * Expand block placements in an instance list into renderable
     * instances (component_ref + the block's variables), tagging them
     * with `block_ref`. Placements whose block no longer exists are
     * dropped. Regular instances pass through untouched.
     */
    public function hydrate(array $instances): array
    {
        $hydrated = [];

        foreach ($instances as $instance) {
            if (empty($instance['block_ref'])) {
                $hydrated[] = $instance;

                continue;
            }

            $block = $this->find($instance['block_ref']);

            if (!$block) {
                continue;
            }

            $hydrated[] = array_merge($instance, [
                'component_ref' => $block['component_ref'],
                'variables' => $block['variables'] ?? [],
                'bindings' => $block['bindings'] ?? [],
            ]);
        }

        return $hydrated;
    }

    /* ------------------------------------------------------------ */
    /*  Usage                                                        */
    /* ------------------------------------------------------------ */

    /**
     * Every placement of a block across pages and layouts.
     *
     * @return array{pages: string[], layouts: string[], count: int}
     */
    public function usage(string $slug): array
    {
        $pages = [];
        $layouts = [];
        $count = 0;

        foreach ($this->storage->list('pages') as $pageSlug) {
            $doc = $this->storage->read("pages/{$pageSlug}.json");
            $placements = $this->placementsIn($doc['components'] ?? [], $slug);

            if ($placements > 0) {
                $pages[] = $pageSlug;
                $count += $placements;
            }
        }

        foreach ($this->storage->list('layouts') as $layoutSlug) {
            $doc = $this->storage->read("layouts/{$layoutSlug}.json");
            $placements = $this->placementsIn($doc['components'] ?? [], $slug);

            if ($placements > 0) {
                $layouts[] = $layoutSlug;
                $count += $placements;
            }
        }

        return ['pages' => $pages, 'layouts' => $layouts, 'count' => $count];
    }

    /**
     * Remove every placement of a block from all pages and layouts,
     * then delete the block itself.
     */
    public function deleteEverywhere(string $slug): void
    {
        foreach ($this->storage->list('pages') as $pageSlug) {
            $doc = $this->storage->read("pages/{$pageSlug}.json");

            if ($this->placementsIn($doc['components'] ?? [], $slug) > 0) {
                $doc['components'] = $this->withoutPlacements($doc['components'], $slug);
                $this->storage->write("pages/{$pageSlug}.json", $doc);
            }
        }

        foreach ($this->storage->list('layouts') as $layoutSlug) {
            $doc = $this->storage->read("layouts/{$layoutSlug}.json");

            if ($this->placementsIn($doc['components'] ?? [], $slug) > 0) {
                $doc['components'] = $this->withoutPlacements($doc['components'], $slug);
                $this->storage->write("layouts/{$layoutSlug}.json", $doc);
            }
        }

        $this->delete($slug);
    }

    protected function placementsIn(array $components, string $slug): int
    {
        return count(array_filter($components, fn ($c) => ($c['block_ref'] ?? null) === $slug));
    }

    protected function withoutPlacements(array $components, string $slug): array
    {
        $kept = array_values(array_filter($components, fn ($c) => ($c['block_ref'] ?? null) !== $slug));

        foreach ($kept as $i => &$comp) {
            $comp['order'] = $i;
        }
        unset($comp);

        return $kept;
    }
}
