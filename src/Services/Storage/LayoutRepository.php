<?php

namespace Designer\Studio\Services\Storage;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reusable layouts — shared sections that wrap page content.
 *
 * A layout is stored like a page (a JSON doc with a `components` array) with
 * one special entry: the content slot (`@content`). Sections before the slot
 * render above every page's own sections, sections after it render below.
 * Because the slot is just another array entry, every component operation
 * (insert, reorder, move) works positionally with no special cases beyond
 * "the slot itself can't be removed, hidden, or duplicated".
 */
class LayoutRepository
{
    public const CONTENT_ID = '__content__';

    public const CONTENT_REF = '@content';

    public function __construct(
        protected StudioStorage $storage
    ) {}

    /* ------------------------------------------------------------ */
    /*  CRUD                                                         */
    /* ------------------------------------------------------------ */

    public function all(): Collection
    {
        return collect($this->storage->list('layouts'))
            ->map(fn ($slug) => $this->find($slug))
            ->filter()
            ->sortBy('name')
            ->values();
    }

    public function find(string $slug): ?array
    {
        return $this->storage->read("layouts/{$slug}.json");
    }

    public function create(string $name): array
    {
        $slug = Str::slug($name) ?: 'layout';

        $base = $slug;
        $counter = 1;
        while ($this->storage->exists("layouts/{$slug}.json")) {
            $slug = $base . '-' . $counter++;
        }

        $layout = [
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => $name ?: 'Layout',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'components' => [
                [
                    'id' => self::CONTENT_ID,
                    'component_ref' => self::CONTENT_REF,
                    'order' => 0,
                    'variables' => [],
                ],
            ],
        ];

        $this->storage->write("layouts/{$slug}.json", $layout);

        return $layout;
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

        $this->storage->write("layouts/{$slug}.json", $updated);

        return $updated;
    }

    public function delete(string $slug): bool
    {
        return $this->storage->delete("layouts/{$slug}.json");
    }

    /**
     * The layout new pages use: `main` when the site has one, else the
     * layout the home page uses, else the first. Null for a site with none.
     */
    public function primary(): ?string
    {
        if ($this->find('main')) {
            return 'main';
        }

        $home = app(PageRepository::class)->find(\Designer\Studio\Support\SiteUrls::homeSlug());

        if ($home?->layout_ref && $this->find($home->layout_ref)) {
            return $home->layout_ref;
        }

        return $this->all()->first()['slug'] ?? null;
    }

    /* ------------------------------------------------------------ */
    /*  Regions                                                      */
    /* ------------------------------------------------------------ */

    /**
     * The layout's section instances split at the content slot.
     *
     * @return array{before: array, after: array}
     */
    public function regions(?string $slug): array
    {
        $empty = ['before' => [], 'after' => []];

        if (!$slug || !($layout = $this->find($slug))) {
            return $empty;
        }

        $components = collect($layout['components'] ?? [])->sortBy('order')->values();
        $slotIndex = $components->search(fn ($c) => $c['id'] === self::CONTENT_ID);

        if ($slotIndex === false) {
            return ['before' => $components->all(), 'after' => []];
        }

        return [
            'before' => $components->slice(0, $slotIndex)->values()->all(),
            'after' => $components->slice($slotIndex + 1)->values()->all(),
        ];
    }

    /**
     * A layout's components array split at the content slot (no slot entry
     * in either half).
     *
     * @return array{before: array, after: array}
     */
    public function splitComponents(array $components): array
    {
        $components = collect($components)->sortBy('order')->values();
        $slotIndex = $components->search(fn ($c) => ($c['id'] ?? null) === self::CONTENT_ID);

        if ($slotIndex === false) {
            return ['before' => $components->all(), 'after' => []];
        }

        return [
            'before' => $components->slice(0, $slotIndex)->values()->all(),
            'after' => $components->slice($slotIndex + 1)->values()->all(),
        ];
    }

    /**
     * Slugs of every page that uses the given layout.
     */
    public function pagesUsing(string $slug): array
    {
        return collect($this->storage->list('pages'))
            ->filter(function ($pageSlug) use ($slug) {
                $page = $this->storage->read("pages/{$pageSlug}.json");

                return ($page['layout_ref'] ?? null) === $slug;
            })
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------ */
    /*  Component operations (mirror PageRepository, + slot guard)   */
    /* ------------------------------------------------------------ */

    protected function isSlot(string $componentId): bool
    {
        return $componentId === self::CONTENT_ID;
    }

    public function addComponent(string $slug, string $componentRef, array $variables = [], ?int $insertAtIndex = null): ?array
    {
        $layout = $this->find($slug);

        if (!$layout) {
            return null;
        }

        $components = $layout['components'] ?? [];
        usort($components, fn ($a, $b) => $a['order'] <=> $b['order']);

        $newComponent = [
            'id' => (string) Str::uuid(),
            'component_ref' => $componentRef,
            'order' => 0,
            'variables' => $variables,
        ];

        if ($bindings = PageRepository::defaultBindings($componentRef)) {
            $newComponent['bindings'] = $bindings;
        }

        if ($insertAtIndex !== null && $insertAtIndex >= 0 && $insertAtIndex <= count($components)) {
            array_splice($components, $insertAtIndex, 0, [$newComponent]);
        } else {
            $components[] = $newComponent;
        }

        foreach ($components as $i => &$comp) {
            $comp['order'] = $i;
        }
        unset($comp);

        return $this->update($slug, ['components' => $components]);
    }

    public function updateComponentVariables(string $slug, string $componentId, array $variables): ?array
    {
        if ($this->isSlot($componentId) || !($layout = $this->find($slug))) {
            return null;
        }

        $components = collect($layout['components'] ?? [])->map(function ($comp) use ($componentId, $variables) {
            if ($comp['id'] === $componentId) {
                $comp['variables'] = array_merge($comp['variables'] ?? [], $variables);
            }

            return $comp;
        })->toArray();

        return $this->update($slug, ['components' => $components]);
    }

    /** Replace an instance's collection bindings; [] unbinds (see PageRepository) */
    public function updateComponentBindings(string $slug, string $componentId, array $bindings, ?array $variables = null): ?array
    {
        if ($this->isSlot($componentId) || !($layout = $this->find($slug))) {
            return null;
        }

        $components = collect($layout['components'] ?? [])->map(function ($comp) use ($componentId, $bindings, $variables) {
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

        return $this->update($slug, ['components' => $components]);
    }

    public function removeComponent(string $slug, string $componentId): ?array
    {
        if ($this->isSlot($componentId) || !($layout = $this->find($slug))) {
            return null;
        }

        $components = collect($layout['components'] ?? [])
            ->reject(fn ($comp) => $comp['id'] === $componentId)
            ->values()
            ->toArray();

        return $this->update($slug, ['components' => $components]);
    }

    public function reorderComponents(string $slug, array $orderedIds): ?array
    {
        $layout = $this->find($slug);

        if (!$layout) {
            return null;
        }

        $componentsById = collect($layout['components'] ?? [])->keyBy('id');

        $reordered = collect($orderedIds)->map(function ($id, $index) use ($componentsById) {
            $comp = $componentsById->get($id);
            if ($comp) {
                $comp['order'] = $index;
            }

            return $comp;
        })->filter()->values()->toArray();

        // Never lose the content slot to a bad reorder payload
        if (!collect($reordered)->contains(fn ($c) => $c['id'] === self::CONTENT_ID)) {
            return null;
        }

        return $this->update($slug, ['components' => $reordered]);
    }

    public function setComponentHidden(string $slug, string $componentId, bool $hidden): ?array
    {
        if ($this->isSlot($componentId) || !($layout = $this->find($slug))) {
            return null;
        }

        $components = collect($layout['components'] ?? [])->map(function ($comp) use ($componentId, $hidden) {
            if ($comp['id'] === $componentId) {
                $comp['hidden'] = $hidden;
            }

            return $comp;
        })->toArray();

        return $this->update($slug, ['components' => $components]);
    }

    public function duplicateComponent(string $slug, string $componentId): ?array
    {
        if ($this->isSlot($componentId) || !($layout = $this->find($slug))) {
            return null;
        }

        $components = collect($layout['components'] ?? [])->sortBy('order')->values()->toArray();
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

        return $this->update($slug, ['components' => $components]);
    }
}
