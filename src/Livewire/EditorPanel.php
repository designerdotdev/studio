<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\BlockRepository;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class EditorPanel extends Component
{
    /** Per-page head/SEO settings persisted in the page's meta array */
    protected const META_KEYS = [
        'seo_title', 'seo_description', 'canonical_url', 'seo_keywords',
        'og_image', 'og_image_alt', 'og_type', 'og_site_name', 'og_locale',
        'twitter_card', 'twitter_site', 'twitter_creator',
        'noindex', 'nofollow', 'favicon', 'theme_color', 'json_ld', 'head_html',
    ];

    protected const META_TOGGLES = ['noindex', 'nofollow'];

    public string $pageSlug = '';

    /** Editable page settings (title, slug, meta) */
    public array $page = [];

    /** Ordered section instances: id, ref, title, category, hidden, scope */
    public array $sections = [];

    /** The page's layout sections (shared header/footer), same row shape */
    public array $layoutSections = [];

    /** Slug of the layout this page uses (null = none) */
    public ?string $layoutSlug = null;

    /** Editable layout name */
    public string $layoutName = '';

    /** All layouts for the picker: [slug => name] */
    public array $layouts = [];

    /** Name for the create-layout form */
    public string $newLayoutName = '';

    /** Show the create-layout form even when a layout is assigned */
    public bool $showCreateLayout = false;

    /** The most recently deleted section, restorable via the toast's Undo */
    public ?array $lastDeleted = null;

    /** updated_at of the page/layout docs as loaded — for conflict detection */
    public array $docVersions = [];

    /** Editable name of the selected global block (synced on selection) */
    public string $selectedBlockName = '';

    /** Field definitions per component ref */
    public array $fieldsByRef = [];

    /** Resolved variables per section instance id (page + layout) */
    public array $variables = [];

    /** Per-section {field: 'collections.<name>'} — repeaters bound to a collection */
    public array $bindings = [];

    public ?string $selectedId = null;

    /** Active sidebar tab: sections | page | layout */
    public string $tab = 'sections';

    /** Runs every Livewire request — panel edits always target the draft */
    public function boot(): void
    {
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    public function mount(string $pageSlug = ''): void
    {
        $this->pageSlug = $pageSlug;
        $this->loadPage();
    }

    /* ------------------------------------------------------------ */
    /*  Loading                                                      */
    /* ------------------------------------------------------------ */

    protected function pages(): PageRepository
    {
        return app(PageRepository::class);
    }

    protected function components(): ComponentRepository
    {
        return app(ComponentRepository::class);
    }

    protected function layoutRepo(): LayoutRepository
    {
        return app(LayoutRepository::class);
    }

    protected function blockRepo(): BlockRepository
    {
        return app(BlockRepository::class);
    }

    /** The global block slug behind an instance id, or null */
    public function blockFor(string $id): ?string
    {
        foreach ([...$this->sections, ...$this->layoutSections] as $section) {
            if ($section['id'] === $id) {
                return $section['block'] ?? null;
            }
        }

        return null;
    }

    /** Does this instance id belong to the page's layout (vs. the page)? */
    public function isLayoutSection(string $id): bool
    {
        foreach ($this->layoutSections as $section) {
            if ($section['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    protected function loadPage(): void
    {
        $page = $this->pages()->find($this->pageSlug);

        if (!$page) {
            $this->sections = [];

            return;
        }

        $this->page = [
            'title' => $page->title,
            'slug' => $page->slug,
            'description' => $page->description,
        ];

        foreach (self::META_KEYS as $key) {
            $this->page[$key] = $page->meta[$key]
                ?? (in_array($key, self::META_TOGGLES, true) ? false : '');
        }

        $this->variables = [];
        $this->sections = $this->buildRows(
            collect($page->components)->sortBy('order')->values()->all(),
            'page'
        );

        $this->docVersions['page'] = $page->updated_at;

        $this->loadLayout($page->layout_ref);
    }

    /* ------------------------------------------------------------ */
    /*  Concurrent-edit detection                                    */
    /* ------------------------------------------------------------ */

    /**
     * True (and shows a reload toast) when the underlying document was
     * modified since this panel loaded it — another tab or another
     * person. Prevents silent last-write-wins clobbering.
     */
    protected function guardConflict(string $scope): bool
    {
        $loaded = $this->docVersions[$scope] ?? null;

        $current = $scope === 'layout'
            ? ($this->layoutSlug ? ($this->layoutRepo()->find($this->layoutSlug)['updated_at'] ?? null) : null)
            : $this->pages()->find($this->pageSlug)?->updated_at;

        if ($loaded === null || $current === null || $current === $loaded) {
            return false;
        }

        $this->dispatch(
            'studio:toast',
            message: 'This ' . ($scope === 'layout' ? 'layout' : 'page') . ' was changed in another tab — reload to keep editing',
            type: 'error',
            action: ['label' => 'Reload', 'reload' => true],
        );

        return true;
    }

    protected function scopeFor(string $id): string
    {
        return $this->isLayoutSection($id) ? 'layout' : 'page';
    }

    protected function loadLayout(?string $layoutRef): void
    {
        $this->layouts = $this->layoutRepo()->all()
            ->mapWithKeys(fn ($layout) => [$layout['slug'] => $layout['name']])
            ->toArray();

        $layout = $layoutRef ? $this->layoutRepo()->find($layoutRef) : null;

        if (!$layout) {
            $this->layoutSlug = null;
            $this->layoutName = '';
            $this->layoutSections = [];

            return;
        }

        $this->layoutSlug = $layout['slug'];
        $this->layoutName = $layout['name'];
        $this->docVersions['layout'] = $layout['updated_at'] ?? null;

        $instances = collect($layout['components'] ?? [])
            ->sortBy('order')
            ->reject(fn ($instance) => $instance['id'] === LayoutRepository::CONTENT_ID)
            ->values()
            ->all();

        $this->layoutSections = $this->buildRows($instances, 'layout');
    }

    /** @return array<int, array> section rows; also fills fieldsByRef + variables */
    protected function buildRows(array $instances, string $scope): array
    {
        $rows = [];

        foreach ($this->blockRepo()->hydrate($instances) as $instance) {
            $component = $this->components()->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $blockSlug = $instance['block_ref'] ?? null;

            $rows[] = [
                'id' => $instance['id'],
                'ref' => $component->name,
                'title' => $component->title,
                'category' => $component->category,
                'hidden' => (bool) ($instance['hidden'] ?? false),
                'scope' => $scope,
                'block' => $blockSlug,
                'blockName' => $blockSlug ? ($this->blockRepo()->find($blockSlug)['name'] ?? $blockSlug) : null,
            ];

            $this->fieldsByRef[$component->name] = $component->fields;
            $this->variables[$instance['id']] = $component->resolveVariables($instance['variables'] ?? []);
            $this->bindings[$instance['id']] = $instance['bindings'] ?? [];
        }

        return $rows;
    }

    public function getSelectedSectionProperty(): ?array
    {
        foreach ([...$this->sections, ...$this->layoutSections] as $section) {
            if ($section['id'] === $this->selectedId) {
                return $section;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------ */
    /*  Selection                                                    */
    /* ------------------------------------------------------------ */

    #[On('studio:select-section')]
    public function selectSection(string $id): void
    {
        $this->selectedId = $id;
        $this->tab = 'sections';
        $this->syncSelectedBlockName();
        $this->dispatch('studio:selection-changed', id: $id);
    }

    protected function syncSelectedBlockName(): void
    {
        $section = $this->selectedSection;
        $this->selectedBlockName = $section['blockName'] ?? '';
    }

    #[On('studio:deselect-section')]
    public function deselectSection(): void
    {
        $this->selectedId = null;
        $this->dispatch('studio:selection-changed', id: null);
    }

    /**
     * Dev mode saved a section's source files — re-resolve everything so
     * the inspector reflects the new fields/defaults.
     */
    #[On('studio:code-saved')]
    public function refreshAfterCodeSave(): void
    {
        $this->loadPage();
    }

    /** Select from the layers list — also focus the section in the canvas */
    public function selectFromList(string $id): void
    {
        $this->selectedId = $id;
        $this->syncSelectedBlockName();
        $this->dispatch('studio:selection-changed', id: $id);
        $this->dispatch('studio:to-iframe', type: 'studio:select', sectionId: $id, scroll: true);
    }

    public function closeInspector(): void
    {
        $this->selectedId = null;
        $this->dispatch('studio:selection-changed', id: null);
        $this->dispatch('studio:to-iframe', type: 'studio:deselect');
    }

    /* ------------------------------------------------------------ */
    /*  Section operations                                           */
    /* ------------------------------------------------------------ */

    #[On('studio:add-section')]
    public function addSection(string $ref, ?int $index = null, string $scope = 'page'): void
    {
        if (str_starts_with($ref, 'block:')) {
            $this->addBlockPlacement(substr($ref, 6), $index, $scope);

            return;
        }

        if ($this->guardConflict($scope === 'layout' ? 'layout' : 'page')) {
            return;
        }

        $component = $this->components()->find($ref);

        if (!$component) {
            $this->dispatch('studio:toast', message: 'Section not found', type: 'error');

            return;
        }

        $defaults = $component->resolveVariables([], usePreviewDefaults: true);

        if ($scope === 'layout' && $this->layoutSlug) {
            $doc = $this->layoutRepo()->addComponent($this->layoutSlug, $ref, $defaults, $index);
            $components = $doc['components'] ?? null;
        } else {
            $doc = $this->pages()->addComponent($this->pageSlug, $ref, $defaults, $index);
            $components = $doc?->components;
        }

        if ($components === null) {
            return;
        }

        $this->loadPage();

        // Select the newly added instance
        $ordered = collect($components)->sortBy('order')->values();
        $new = $index !== null ? $ordered->get($index) : $ordered->last();

        if ($new) {
            $this->selectedId = $new['id'];
            $this->dispatch('studio:selection-changed', id: $new['id']);
        }

        $this->dispatch('studio:section-added');
        $this->dispatch('studio:refresh-preview');
        $this->dispatch(
            'studio:toast',
            message: $component->title . ($scope === 'layout' ? ' added to layout' : ' added'),
        );
    }

    #[On('studio:section-action')]
    public function handleSectionAction(string $id, string $action): void
    {
        match ($action) {
            'move-up' => $this->moveSection($id, -1),
            'move-down' => $this->moveSection($id, 1),
            'duplicate' => $this->duplicateSection($id),
            'toggle-hidden' => $this->toggleHidden($id),
            'make-global' => $this->makeGlobal($id),
            'delete' => $this->removeSection($id),
            default => null,
        };
    }

    public function moveSection(string $id, int $delta): void
    {
        if ($this->guardConflict($this->scopeFor($id))) {
            return;
        }

        if ($this->isLayoutSection($id)) {
            // Order within the layout doc includes the content slot, so a
            // header section moved down past the slot becomes a footer one.
            $layout = $this->layoutSlug ? $this->layoutRepo()->find($this->layoutSlug) : null;

            if (!$layout) {
                return;
            }

            $ids = collect($layout['components'] ?? [])->sortBy('order')->pluck('id')->all();
        } else {
            $ids = array_column($this->sections, 'id');
        }

        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        if ($this->isLayoutSection($id)) {
            $this->layoutRepo()->reorderComponents($this->layoutSlug, $ids);
        } else {
            $this->pages()->reorderComponents($this->pageSlug, $ids);
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
    }

    public function reorderSections(array $ids): void
    {
        $this->pages()->reorderComponents($this->pageSlug, $ids);
        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
    }

    public function duplicateSection(string $id): void
    {
        if ($this->guardConflict($this->scopeFor($id))) {
            return;
        }

        $result = $this->isLayoutSection($id)
            ? $this->layoutRepo()->duplicateComponent($this->layoutSlug, $id)
            : $this->pages()->duplicateComponent($this->pageSlug, $id);

        if (!$result) {
            return;
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Section duplicated');
    }

    public function toggleHidden(string $id): void
    {
        if ($this->guardConflict($this->scopeFor($id))) {
            return;
        }

        $current = false;

        foreach ([...$this->sections, ...$this->layoutSections] as $section) {
            if ($section['id'] === $id) {
                $current = $section['hidden'];
                break;
            }
        }

        if ($this->isLayoutSection($id)) {
            $this->layoutRepo()->setComponentHidden($this->layoutSlug, $id, !$current);
        } else {
            $this->pages()->setComponentHidden($this->pageSlug, $id, !$current);
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
    }

    public function removeSection(string $id): void
    {
        if ($this->guardConflict($this->scopeFor($id))) {
            return;
        }

        // Capture the raw instance + position first so the toast can undo
        $this->lastDeleted = $this->captureInstance($id);

        if ($this->isLayoutSection($id)) {
            $this->layoutRepo()->removeComponent($this->layoutSlug, $id);
        } else {
            $this->pages()->removeComponent($this->pageSlug, $id);
        }

        if ($this->selectedId === $id) {
            $this->selectedId = null;
            $this->dispatch('studio:selection-changed', id: null);
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');

        if ($this->lastDeleted) {
            $this->dispatch(
                'studio:toast',
                message: '“' . $this->lastDeleted['title'] . '” deleted',
                type: 'info',
                action: ['label' => 'Undo', 'dispatch' => 'studio:undo-delete'],
            );
        } else {
            $this->dispatch('studio:toast', message: 'Section deleted');
        }
    }

    /** Snapshot an instance (raw entry + position) before it's removed */
    protected function captureInstance(string $id): ?array
    {
        if ($isLayout = $this->isLayoutSection($id)) {
            $doc = $this->layoutSlug ? $this->layoutRepo()->find($this->layoutSlug) : null;
            $components = collect($doc['components'] ?? []);
            $docSlug = $this->layoutSlug;
        } else {
            $components = collect($this->pages()->find($this->pageSlug)?->components ?? []);
            $docSlug = $this->pageSlug;
        }

        $ordered = $components->sortBy('order')->values();
        $index = $ordered->search(fn ($c) => $c['id'] === $id);

        if ($index === false || !$docSlug) {
            return null;
        }

        $title = 'Section';
        foreach ([...$this->sections, ...$this->layoutSections] as $row) {
            if ($row['id'] === $id) {
                $title = $row['blockName'] ?: $row['title'];
                break;
            }
        }

        return [
            'scope' => $isLayout ? 'layout' : 'page',
            'doc' => $docSlug,
            'index' => $index,
            'instance' => $ordered->get($index),
            'title' => $title,
        ];
    }

    #[On('studio:undo-delete')]
    public function undoDelete(): void
    {
        $deleted = $this->lastDeleted;
        $this->lastDeleted = null;

        if (!$deleted) {
            return;
        }

        if ($this->guardConflict($deleted['scope'])) {
            return;
        }

        // Restore only into the document it was deleted from
        $currentDoc = $deleted['scope'] === 'layout' ? $this->layoutSlug : $this->pageSlug;

        if ($deleted['doc'] !== $currentDoc) {
            $this->dispatch('studio:toast', message: 'Could not restore — the ' . $deleted['scope'] . ' has changed', type: 'error');

            return;
        }

        $this->insertRawInstance($deleted['instance'], $deleted['index'], $deleted['scope']);

        $this->loadPage();
        $this->selectedId = $deleted['instance']['id'];
        $this->syncSelectedBlockName();
        $this->dispatch('studio:selection-changed', id: $this->selectedId);
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: '“' . $deleted['title'] . '” restored');
    }

    /* ------------------------------------------------------------ */
    /*  Variable persistence                                         */
    /* ------------------------------------------------------------ */

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'variables.')) {
            $parts = explode('.', $property);
            $sectionId = $parts[1] ?? null;

            if ($sectionId && isset($this->variables[$sectionId])) {
                $this->saveVariables($sectionId);
            }
        }

        if (str_starts_with($property, 'page.')) {
            $this->savePageSettings();
        }

        if ($property === 'layoutName') {
            $this->renameLayout();
        }

        if ($property === 'selectedBlockName') {
            $this->renameSelectedBlock();
        }
    }

    /* ------------------------------------------------------------ */
    /*  Collection bindings                                          */
    /* ------------------------------------------------------------ */

    /** Collections available to bind: name => title */
    public function getCollectionsProperty(): array
    {
        return collect(app(\Designer\Studio\Services\Storage\CollectionRepository::class)->all())
            ->map(fn ($doc) => $doc['title'])
            ->toArray();
    }

    /** Point a repeater at a collection; its rows now come from Content */
    public function bindRepeater(string $sectionId, string $key, string $collection): void
    {
        $name = \Designer\Studio\Services\CollectionBinder::collectionName('collections.' . $collection);

        if ($name === null || !app(\Designer\Studio\Services\Storage\CollectionRepository::class)->exists($name)) {
            return;
        }

        $bindings = ($this->bindings[$sectionId] ?? []) + [$key => 'collections.' . $name];
        $bindings[$key] = 'collections.' . $name;

        $this->persistBindings($sectionId, $bindings);
        $this->dispatch('studio:toast', message: 'Bound to ' . ($this->collections[$name] ?? $name), type: 'success');
    }

    /** Detach a repeater from its collection, keeping the current rows as plain values */
    public function unbindRepeater(string $sectionId, string $key): void
    {
        $bindings = $this->bindings[$sectionId] ?? [];
        $source = $bindings[$key] ?? null;
        unset($bindings[$key]);

        $rows = null;

        if ($name = \Designer\Studio\Services\CollectionBinder::collectionName($source)) {
            $rows = app(\Designer\Studio\Services\Storage\CollectionRepository::class)->rows($name);
            $this->variables[$sectionId][$key] = $rows;
        }

        $this->persistBindings($sectionId, $bindings, $rows === null ? null : [$key => $rows]);
    }

    protected function persistBindings(string $sectionId, array $bindings, ?array $variables = null): void
    {
        if ($block = $this->blockFor($sectionId)) {
            $this->blockRepo()->updateBindings($block, $bindings, $variables);
        } elseif ($this->isLayoutSection($sectionId)) {
            if ($this->guardConflict('layout')) {
                return;
            }
            $updated = $this->layoutRepo()->updateComponentBindings($this->layoutSlug, $sectionId, $bindings, $variables);
            $this->docVersions['layout'] = $updated['updated_at'] ?? $this->docVersions['layout'] ?? null;
        } else {
            if ($this->guardConflict('page')) {
                return;
            }
            $updated = $this->pages()->updateComponentBindings($this->pageSlug, $sectionId, $bindings, $variables);
            $this->docVersions['page'] = $updated?->updated_at ?? $this->docVersions['page'] ?? null;
        }

        $this->bindings[$sectionId] = $bindings;
        $this->dispatch('studio:refresh-preview');
    }

    protected function saveVariables(string $sectionId): void
    {
        if (!$this->pageSlug) {
            return;
        }

        // Global block placements write to the shared block, wherever they live
        if ($block = $this->blockFor($sectionId)) {
            $this->blockRepo()->updateVariables($block, $this->variables[$sectionId]);

            return;
        }

        if ($this->guardConflict($this->scopeFor($sectionId))) {
            return;
        }

        if ($this->isLayoutSection($sectionId)) {
            $updated = $this->layoutRepo()->updateComponentVariables(
                $this->layoutSlug,
                $sectionId,
                $this->variables[$sectionId]
            );
            $this->docVersions['layout'] = $updated['updated_at'] ?? $this->docVersions['layout'] ?? null;

            return;
        }

        $updated = $this->pages()->updateComponentVariables(
            $this->pageSlug,
            $sectionId,
            $this->variables[$sectionId]
        );

        if ($updated) {
            $this->docVersions['page'] = $updated->updated_at;
        }
    }

    /** Toggle fields persist + push their state to the preview in one round trip */
    public function setVariable(string $sectionId, string $key, $value): void
    {
        if (!isset($this->variables[$sectionId])) {
            return;
        }

        $this->variables[$sectionId][$key] = $value;
        $this->saveVariables($sectionId);

        $this->dispatch(
            'studio:to-iframe',
            type: 'studio:update-variable',
            sectionId: $sectionId,
            key: $key,
            value: $value,
        );
    }

    /* ------------------------------------------------------------ */
    /*  Repeater fields                                              */
    /* ------------------------------------------------------------ */

    protected function pushRepeaterToPreview(string $sectionId, string $fieldKey): void
    {
        $this->dispatch(
            'studio:to-iframe',
            type: 'studio:update-variables',
            sectionId: $sectionId,
            variables: [$fieldKey => $this->variables[$sectionId][$fieldKey] ?? []],
        );
    }

    protected function repeaterConfig(string $sectionId, string $fieldKey): array
    {
        foreach ([...$this->sections, ...$this->layoutSections] as $section) {
            if ($section['id'] === $sectionId) {
                return $this->fieldsByRef[$section['ref']][$fieldKey] ?? [];
            }
        }

        return [];
    }

    public function addRepeaterItem(string $sectionId, string $fieldKey): void
    {
        $config = $this->repeaterConfig($sectionId, $fieldKey);
        $subFields = $config['sub_fields'] ?? [];

        $newItem = [];
        foreach ($subFields as $subKey => $subConfig) {
            $newItem[$subKey] = $subConfig['default'] ?? '';
        }

        if (!empty($config['nestable'])) {
            $newItem['children'] = [];
        }

        $this->variables[$sectionId][$fieldKey][] = $newItem;
        $this->saveVariables($sectionId);
        $this->pushRepeaterToPreview($sectionId, $fieldKey);
    }

    public function removeRepeaterItem(string $sectionId, string $fieldKey, int $index): void
    {
        if (isset($this->variables[$sectionId][$fieldKey][$index])) {
            array_splice($this->variables[$sectionId][$fieldKey], $index, 1);
            $this->saveVariables($sectionId);
            $this->pushRepeaterToPreview($sectionId, $fieldKey);
        }
    }

    public function moveRepeaterItem(string $sectionId, string $fieldKey, int $fromIndex, int $toIndex): void
    {
        $items = $this->variables[$sectionId][$fieldKey] ?? [];

        if (!isset($items[$fromIndex]) || $toIndex < 0 || $toIndex >= count($items)) {
            return;
        }

        $item = array_splice($items, $fromIndex, 1)[0];
        array_splice($items, $toIndex, 0, [$item]);

        $this->variables[$sectionId][$fieldKey] = $items;
        $this->saveVariables($sectionId);
        $this->pushRepeaterToPreview($sectionId, $fieldKey);
    }

    public function updateRepeaterSubField(string $sectionId, string $fieldKey, int $index, string $subField, string $value): void
    {
        if (isset($this->variables[$sectionId][$fieldKey][$index])) {
            $this->variables[$sectionId][$fieldKey][$index][$subField] = $value;
            $this->saveVariables($sectionId);
            $this->pushRepeaterToPreview($sectionId, $fieldKey);
        }
    }

    /* --- nested (two-level) repeaters --------------------------- */

    public function indentRepeaterItem(string $sectionId, string $fieldKey, int $index): void
    {
        $items = $this->variables[$sectionId][$fieldKey] ?? [];
        $config = $this->repeaterConfig($sectionId, $fieldKey);
        $maxDepth = (int) ($config['max_depth'] ?? 2);

        if ($index <= 0 || !isset($items[$index]) || $maxDepth <= 1) {
            return;
        }

        $item = array_splice($items, $index, 1)[0];
        unset($item['children']);
        $items[$index - 1]['children'][] = $item;

        $this->variables[$sectionId][$fieldKey] = $items;
        $this->saveVariables($sectionId);
        $this->pushRepeaterToPreview($sectionId, $fieldKey);
    }

    public function outdentRepeaterItem(string $sectionId, string $fieldKey, int $parentIndex, int $childIndex): void
    {
        $items = $this->variables[$sectionId][$fieldKey] ?? [];

        if (!isset($items[$parentIndex]['children'][$childIndex])) {
            return;
        }

        $child = array_splice($items[$parentIndex]['children'], $childIndex, 1)[0];
        $child['children'] = [];

        array_splice($items, $parentIndex + 1, 0, [$child]);

        $this->variables[$sectionId][$fieldKey] = $items;
        $this->saveVariables($sectionId);
        $this->pushRepeaterToPreview($sectionId, $fieldKey);
    }

    public function updateRepeaterChildSubField(string $sectionId, string $fieldKey, int $parentIndex, int $childIndex, string $subField, string $value): void
    {
        if (isset($this->variables[$sectionId][$fieldKey][$parentIndex]['children'][$childIndex])) {
            $this->variables[$sectionId][$fieldKey][$parentIndex]['children'][$childIndex][$subField] = $value;
            $this->saveVariables($sectionId);
            $this->pushRepeaterToPreview($sectionId, $fieldKey);
        }
    }

    public function removeRepeaterChild(string $sectionId, string $fieldKey, int $parentIndex, int $childIndex): void
    {
        if (isset($this->variables[$sectionId][$fieldKey][$parentIndex]['children'][$childIndex])) {
            array_splice($this->variables[$sectionId][$fieldKey][$parentIndex]['children'], $childIndex, 1);
            $this->saveVariables($sectionId);
            $this->pushRepeaterToPreview($sectionId, $fieldKey);
        }
    }

    /* ------------------------------------------------------------ */
    /*  Global blocks                                                */
    /* ------------------------------------------------------------ */

    /**
     * Insert a placement of an existing global block into the page (or
     * the layout, when added from a layout insert zone).
     */
    protected function addBlockPlacement(string $blockSlug, ?int $index, string $scope): void
    {
        if ($this->guardConflict($scope === 'layout' ? 'layout' : 'page')) {
            return;
        }

        $block = $this->blockRepo()->find($blockSlug);

        if (!$block) {
            $this->dispatch('studio:toast', message: 'Global block not found', type: 'error');

            return;
        }

        $placement = [
            'id' => (string) Str::uuid(),
            'block_ref' => $blockSlug,
            'order' => 0,
        ];

        $this->insertRawInstance($placement, $index, $scope);

        $this->loadPage();
        $this->selectedId = $placement['id'];
        $this->syncSelectedBlockName();
        $this->dispatch('studio:selection-changed', id: $placement['id']);
        $this->dispatch('studio:section-added');
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: '“' . $block['name'] . '” added');
    }

    /** Splice a raw instance entry into the page or layout components array */
    protected function insertRawInstance(array $entry, ?int $index, string $scope): void
    {
        if ($scope === 'layout' && $this->layoutSlug) {
            $doc = $this->layoutRepo()->find($this->layoutSlug);
            $components = collect($doc['components'] ?? [])->sortBy('order')->values()->all();
        } else {
            $page = $this->pages()->find($this->pageSlug);
            $components = collect($page?->components ?? [])->sortBy('order')->values()->all();
        }

        if ($index !== null && $index >= 0 && $index <= count($components)) {
            array_splice($components, $index, 0, [$entry]);
        } else {
            $components[] = $entry;
        }

        foreach ($components as $i => &$comp) {
            $comp['order'] = $i;
        }
        unset($comp);

        if ($scope === 'layout' && $this->layoutSlug) {
            $this->layoutRepo()->update($this->layoutSlug, ['components' => $components]);
        } else {
            $this->pages()->update($this->pageSlug, ['components' => $components]);
        }
    }

    /**
     * Promote a page section into a global block: its data moves into the
     * shared block and the section becomes a placement of it.
     */
    public function makeGlobal(string $id): void
    {
        if ($this->blockFor($id) || $this->isLayoutSection($id)) {
            return;
        }

        if ($this->guardConflict('page')) {
            return;
        }

        $page = $this->pages()->find($this->pageSlug);

        if (!$page) {
            return;
        }

        $components = collect($page->components)->sortBy('order')->values()->all();
        $component = null;

        foreach ($components as &$comp) {
            if ($comp['id'] === $id) {
                $component = $this->components()->find($comp['component_ref']);

                if (!$component) {
                    return;
                }

                $block = $this->blockRepo()->create(
                    $component->title,
                    $comp['component_ref'],
                    $comp['variables'] ?? []
                );

                $comp = array_filter([
                    'id' => $comp['id'],
                    'block_ref' => $block['slug'],
                    'order' => $comp['order'],
                    'hidden' => $comp['hidden'] ?? null,
                ], fn ($v) => $v !== null);
                break;
            }
        }
        unset($comp);

        if (!$component) {
            return;
        }

        $this->pages()->update($this->pageSlug, ['components' => $components]);
        $this->loadPage();
        $this->selectedId = $id;
        $this->syncSelectedBlockName();
        $this->dispatch('studio:selection-changed', id: $id);
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: '“' . $component->title . '” is now a global block — add it to any page from the library');
    }

    /**
     * Detach a placement: it becomes an ordinary local section again,
     * copying the block's current content. Other placements keep syncing.
     */
    public function detachBlock(string $id): void
    {
        if ($this->guardConflict($this->scopeFor($id))) {
            return;
        }

        $blockSlug = $this->blockFor($id);
        $block = $blockSlug ? $this->blockRepo()->find($blockSlug) : null;

        if (!$block) {
            return;
        }

        $inLayout = $this->isLayoutSection($id);

        if ($inLayout) {
            $doc = $this->layoutRepo()->find($this->layoutSlug);
            $components = $doc['components'] ?? [];
        } else {
            $components = $this->pages()->find($this->pageSlug)?->components ?? [];
        }

        foreach ($components as &$comp) {
            if ($comp['id'] === $id) {
                $comp = [
                    'id' => $comp['id'],
                    'component_ref' => $block['component_ref'],
                    'order' => $comp['order'],
                    'variables' => $block['variables'] ?? [],
                ] + (isset($comp['hidden']) ? ['hidden' => $comp['hidden']] : []);
                break;
            }
        }
        unset($comp);

        if ($inLayout) {
            $this->layoutRepo()->update($this->layoutSlug, ['components' => $components]);
        } else {
            $this->pages()->update($this->pageSlug, ['components' => $components]);
        }

        $this->loadPage();
        $this->syncSelectedBlockName();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Detached — this copy is now independent');
    }

    /** Delete the block and every placement of it, on every page */
    public function deleteBlockEverywhere(string $id): void
    {
        $blockSlug = $this->blockFor($id);

        if (!$blockSlug) {
            return;
        }

        $this->blockRepo()->deleteEverywhere($blockSlug);

        if ($this->selectedId === $id) {
            $this->selectedId = null;
            $this->dispatch('studio:selection-changed', id: null);
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Global block deleted everywhere');
    }

    protected function renameSelectedBlock(): void
    {
        $blockSlug = $this->selectedId ? $this->blockFor($this->selectedId) : null;

        if (!$blockSlug) {
            return;
        }

        $name = trim($this->selectedBlockName);

        if ($name === '') {
            $this->syncSelectedBlockName();

            return;
        }

        $this->blockRepo()->update($blockSlug, ['name' => $name]);
        $this->loadPage();
    }

    public function getBlockUsageCountProperty(): int
    {
        $blockSlug = $this->selectedId ? $this->blockFor($this->selectedId) : null;

        return $blockSlug ? $this->blockRepo()->usage($blockSlug)['count'] : 0;
    }

    /* ------------------------------------------------------------ */
    /*  Layouts                                                      */
    /* ------------------------------------------------------------ */

    public function assignLayout(?string $slug): void
    {
        if ($this->guardConflict('page')) {
            return;
        }

        $slug = $slug ?: null;

        if ($slug && !$this->layoutRepo()->find($slug)) {
            return;
        }

        $this->pages()->update($this->pageSlug, ['layout_ref' => $slug]);
        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch(
            'studio:toast',
            message: $slug ? '“' . ($this->layouts[$slug] ?? $slug) . '” applied to this page' : 'Layout removed from this page',
        );
    }

    /** Open the Layout tab with the create form ready (menu → New layout…) */
    #[On('studio:new-layout')]
    public function promptNewLayout(): void
    {
        $this->selectedId = null;
        $this->tab = 'layout';
        $this->showCreateLayout = true;
        $this->dispatch('studio:selection-changed', id: null);
    }

    public function createLayout(): void
    {
        $name = trim($this->newLayoutName) ?: 'Main';

        $layout = $this->layoutRepo()->create($name);
        $this->newLayoutName = '';
        $this->showCreateLayout = false;

        $this->pages()->update($this->pageSlug, ['layout_ref' => $layout['slug']]);
        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: '“' . $layout['name'] . '” created — add sections above or below the page content');
    }

    protected function renameLayout(): void
    {
        if (!$this->layoutSlug) {
            return;
        }

        $name = trim($this->layoutName);

        if ($name === '') {
            $this->layoutName = $this->layouts[$this->layoutSlug] ?? 'Layout';

            return;
        }

        $this->layoutRepo()->update($this->layoutSlug, ['name' => $name]);
        $this->loadPage();
    }

    public function deleteLayout(): void
    {
        if (!$this->layoutSlug) {
            return;
        }

        // Detach from every page that uses it, then remove the layout doc
        foreach ($this->layoutRepo()->pagesUsing($this->layoutSlug) as $pageSlug) {
            $this->pages()->update($pageSlug, ['layout_ref' => null]);
        }

        $this->layoutRepo()->delete($this->layoutSlug);

        $this->selectedId = null;
        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Layout deleted');
    }

    public function getLayoutUsageCountProperty(): int
    {
        return $this->layoutSlug
            ? count($this->layoutRepo()->pagesUsing($this->layoutSlug))
            : 0;
    }

    /* ------------------------------------------------------------ */
    /*  Page settings                                                */
    /* ------------------------------------------------------------ */

    protected function savePageSettings(): void
    {
        if ($this->guardConflict('page')) {
            return;
        }

        $meta = [];

        foreach (self::META_KEYS as $key) {
            $value = $this->page[$key] ?? null;

            // Only persist meaningful values — blank/off means "use defaults"
            if ($value !== '' && $value !== null && $value !== false) {
                $meta[$key] = $value;
            }
        }

        $updated = $this->pages()->update($this->pageSlug, [
            'title' => $this->page['title'] ?? 'Untitled',
            'slug' => $this->page['slug'] ?? $this->pageSlug,
            'description' => $this->page['description'] ?? '',
            'meta' => $meta,
        ]);

        if (!$updated) {
            return;
        }

        $slugChanged = $updated->slug !== $this->pageSlug;
        $this->pageSlug = $updated->slug;
        $this->page['slug'] = $updated->slug;

        if ($slugChanged) {
            // The iframe src, live URL, and topbar links all key off the slug —
            // reload the editor at its new address to keep everything consistent.
            $this->redirect(route('studio.index', ['page' => $updated->slug]));

            return;
        }

        $this->dispatch(
            'studio:page-meta-updated',
            title: $updated->title,
            slug: $updated->slug,
        );
    }

    public function duplicatePage(): void
    {
        $copy = $this->pages()->duplicate($this->pageSlug);

        if ($copy) {
            $this->redirect(route('studio.index', ['page' => $copy->slug]));
        }
    }

    public function deletePage(): void
    {
        $this->pages()->delete($this->pageSlug);
        $this->redirect(route('studio.index'));
    }

    public function render()
    {
        return view('studio::livewire.editor-panel');
    }
}
