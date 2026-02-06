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

    public function all(): Collection
    {
        $slugs = $this->storage->list('pages');

        return collect($slugs)->map(fn($slug) => $this->find($slug))->filter();
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
        $slug = $data['slug'] ?? Str::slug($data['title'] ?? 'untitled');

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
            'layout' => $data['layout'] ?? config('studio.default_layout', 'layouts.app'),
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

        // Handle slug change
        $newSlug = $data['slug'] ?? $slug;
        if ($newSlug !== $slug) {
            $this->storage->delete("pages/{$slug}.json");
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

    public function addComponent(string $pageSlug, string $componentRef, array $variables = [], ?int $order = null): ?PageData
    {
        $page = $this->storage->read("pages/{$pageSlug}.json");

        if (!$page) {
            return null;
        }

        $components = $page['components'] ?? [];
        $maxOrder = collect($components)->max('order') ?? -1;

        $components[] = [
            'id' => (string) Str::uuid(),
            'component_ref' => $componentRef,
            'order' => $order ?? ($maxOrder + 1),
            'variables' => $variables,
        ];

        // Re-sort by order
        usort($components, fn($a, $b) => $a['order'] <=> $b['order']);

        return $this->update($pageSlug, ['components' => $components]);
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
