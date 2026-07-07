<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Livewire\Attributes\On;
use Livewire\Component;

class EditorPanel extends Component
{
    public string $pageSlug = '';

    /** Editable page settings (title, slug, meta) */
    public array $page = [];

    /** Ordered section instances: id, ref, title, category, hidden */
    public array $sections = [];

    /** Field definitions per component ref */
    public array $fieldsByRef = [];

    /** Resolved variables per section instance id */
    public array $variables = [];

    public ?string $selectedId = null;

    /** Active sidebar tab: sections | page */
    public string $tab = 'sections';

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
            'seo_title' => $page->meta['seo_title'] ?? '',
            'seo_description' => $page->meta['seo_description'] ?? '',
        ];

        $sections = [];
        $variables = [];

        foreach (collect($page->components)->sortBy('order')->values() as $instance) {
            $component = $this->components()->find($instance['component_ref']);

            if (!$component) {
                continue;
            }

            $sections[] = [
                'id' => $instance['id'],
                'ref' => $component->name,
                'title' => $component->title,
                'category' => $component->category,
                'hidden' => (bool) ($instance['hidden'] ?? false),
            ];

            $this->fieldsByRef[$component->name] = $component->fields;
            $variables[$instance['id']] = $component->resolveVariables($instance['variables'] ?? []);
        }

        $this->sections = $sections;
        $this->variables = $variables;
    }

    public function getSelectedSectionProperty(): ?array
    {
        foreach ($this->sections as $section) {
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
        $this->dispatch('studio:selection-changed', id: $id);
    }

    #[On('studio:deselect-section')]
    public function deselectSection(): void
    {
        $this->selectedId = null;
        $this->dispatch('studio:selection-changed', id: null);
    }

    /** Select from the layers list — also focus the section in the canvas */
    public function selectFromList(string $id): void
    {
        $this->selectedId = $id;
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
    public function addSection(string $ref, ?int $index = null): void
    {
        $component = $this->components()->find($ref);

        if (!$component) {
            $this->dispatch('studio:toast', message: 'Section not found', type: 'error');

            return;
        }

        $defaults = $component->resolveVariables([], usePreviewDefaults: true);

        $page = $this->pages()->addComponent($this->pageSlug, $ref, $defaults, $index);

        if (!$page) {
            return;
        }

        $this->loadPage();

        // Select the newly added instance
        $ordered = collect($page->components)->sortBy('order')->values();
        $new = $index !== null ? $ordered->get($index) : $ordered->last();

        if ($new) {
            $this->selectedId = $new['id'];
            $this->dispatch('studio:selection-changed', id: $new['id']);
        }

        $this->dispatch('studio:section-added');
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: $component->title . ' added');
    }

    #[On('studio:section-action')]
    public function handleSectionAction(string $id, string $action): void
    {
        match ($action) {
            'move-up' => $this->moveSection($id, -1),
            'move-down' => $this->moveSection($id, 1),
            'duplicate' => $this->duplicateSection($id),
            'toggle-hidden' => $this->toggleHidden($id),
            'delete' => $this->removeSection($id),
            default => null,
        };
    }

    public function moveSection(string $id, int $delta): void
    {
        $ids = array_column($this->sections, 'id');
        $index = array_search($id, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->pages()->reorderComponents($this->pageSlug, $ids);
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
        $page = $this->pages()->duplicateComponent($this->pageSlug, $id);

        if (!$page) {
            return;
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Section duplicated');
    }

    public function toggleHidden(string $id): void
    {
        $current = false;

        foreach ($this->sections as $section) {
            if ($section['id'] === $id) {
                $current = $section['hidden'];
                break;
            }
        }

        $this->pages()->setComponentHidden($this->pageSlug, $id, !$current);
        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
    }

    public function removeSection(string $id): void
    {
        $this->pages()->removeComponent($this->pageSlug, $id);

        if ($this->selectedId === $id) {
            $this->selectedId = null;
            $this->dispatch('studio:selection-changed', id: null);
        }

        $this->loadPage();
        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Section deleted');
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
    }

    protected function saveVariables(string $sectionId): void
    {
        if (!$this->pageSlug) {
            return;
        }

        $this->pages()->updateComponentVariables(
            $this->pageSlug,
            $sectionId,
            $this->variables[$sectionId]
        );
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
        foreach ($this->sections as $section) {
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
    /*  Page settings                                                */
    /* ------------------------------------------------------------ */

    protected function savePageSettings(): void
    {
        $updated = $this->pages()->update($this->pageSlug, [
            'title' => $this->page['title'] ?? 'Untitled',
            'slug' => $this->page['slug'] ?? $this->pageSlug,
            'description' => $this->page['description'] ?? '',
            'meta' => array_filter([
                'seo_title' => $this->page['seo_title'] ?? '',
                'seo_description' => $this->page['seo_description'] ?? '',
            ]),
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
