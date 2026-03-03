<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\PageRepository;
use Livewire\Attributes\On;
use Livewire\Component;

class ComponentEditor extends Component
{
    public array $components = [];

    public array $variables = [];

    public ?string $selectedComponentId = null;

    public ?array $selectedComponent = null;

    public string $pageSlug = '';

    public function mount(array $components = [], string $pageSlug = ''): void
    {
        $this->components = $components;
        $this->pageSlug = $pageSlug;

        // Initialize variables per component instance
        foreach ($components as $id => $component) {
            $this->variables[$id] = [];

            // Fill defaults first
            if (!empty($component['fields'])) {
                foreach ($component['fields'] as $key => $config) {
                    $fieldType = $config['type'] ?? 'text';

                    if ($fieldType === 'repeater') {
                        // For repeaters, default is an array of items
                        $default = $config['default'] ?? [];
                        $nestable = !empty($config['nestable']);

                        if (is_array($default)) {
                            $items = [];
                            foreach ($default as $item) {
                                if ($nestable && !isset($item['children'])) {
                                    $item['children'] = [];
                                }
                                $items[] = $item;
                            }
                            $this->variables[$id][$key] = $items;
                        } else {
                            $this->variables[$id][$key] = [];
                        }
                    } else {
                        $this->variables[$id][$key] = $config['default'] ?? '';
                    }
                }
            }

            // Override with instance variables
            if (!empty($component['variables'])) {
                foreach ($component['variables'] as $key => $value) {
                    $this->variables[$id][$key] = $value;
                }
            }
        }
    }

    #[On('component-selected')]
    public function selectComponent(string $componentId): void
    {
        $this->selectedComponentId = $componentId;
        $this->selectedComponent = $this->components[$componentId] ?? null;
    }

    #[On('component-deselected')]
    public function deselectComponent(): void
    {
        $this->selectedComponentId = null;
        $this->selectedComponent = null;
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'variables.')) {
            // Extract component ID from property path: variables.{componentId}.{key}
            $parts = explode('.', $property);
            $componentId = $parts[1] ?? null;

            if ($componentId && isset($this->variables[$componentId])) {
                $this->saveVariables($componentId);
            }
        }
    }

    protected function saveVariables(string $componentId): void
    {
        if (!$this->pageSlug) {
            return;
        }

        $pageRepository = app(PageRepository::class);
        $pageRepository->updateComponentVariables(
            $this->pageSlug,
            $componentId,
            $this->variables[$componentId]
        );
    }

    public function addRepeaterItem(string $componentId, string $fieldKey): void
    {
        $fields = $this->components[$componentId]['fields'] ?? [];
        $fieldConfig = $fields[$fieldKey] ?? [];
        $subFields = $fieldConfig['sub_fields'] ?? [];
        $nestable = !empty($fieldConfig['nestable']);

        $newItem = [];
        foreach ($subFields as $subKey => $subConfig) {
            $newItem[$subKey] = $subConfig['default'] ?? '';
        }

        if ($nestable) {
            $newItem['children'] = [];
        }

        $this->variables[$componentId][$fieldKey][] = $newItem;
        $this->saveVariables($componentId);
    }

    public function removeRepeaterItem(string $componentId, string $fieldKey, int $index): void
    {
        if (isset($this->variables[$componentId][$fieldKey][$index])) {
            array_splice($this->variables[$componentId][$fieldKey], $index, 1);
            $this->saveVariables($componentId);
        }
    }

    public function moveRepeaterItem(string $componentId, string $fieldKey, int $fromIndex, int $toIndex): void
    {
        $items = $this->variables[$componentId][$fieldKey] ?? [];

        if (!isset($items[$fromIndex]) || $toIndex < 0 || $toIndex >= count($items)) {
            return;
        }

        $item = array_splice($items, $fromIndex, 1)[0];
        array_splice($items, $toIndex, 0, [$item]);

        $this->variables[$componentId][$fieldKey] = $items;
        $this->saveVariables($componentId);
    }

    public function indentMenuItem(string $componentId, string $fieldKey, int $index): void
    {
        $items = $this->variables[$componentId][$fieldKey] ?? [];
        $fieldConfig = $this->components[$componentId]['fields'][$fieldKey] ?? [];
        $maxDepth = (int) ($fieldConfig['max_depth'] ?? 2);

        // Can't indent the first item (no previous sibling)
        if ($index <= 0 || !isset($items[$index])) {
            return;
        }

        // Check depth limit — the previous sibling's children would be depth 2
        if ($maxDepth <= 1) {
            return;
        }

        $item = array_splice($items, $index, 1)[0];
        $items[$index - 1]['children'][] = $item;

        $this->variables[$componentId][$fieldKey] = $items;
        $this->saveVariables($componentId);
    }

    public function outdentMenuItem(string $componentId, string $fieldKey, int $parentIndex, int $childIndex): void
    {
        $items = $this->variables[$componentId][$fieldKey] ?? [];

        if (!isset($items[$parentIndex]['children'][$childIndex])) {
            return;
        }

        $child = array_splice($items[$parentIndex]['children'], $childIndex, 1)[0];

        // Insert after the parent
        array_splice($items, $parentIndex + 1, 0, [$child]);

        $this->variables[$componentId][$fieldKey] = $items;
        $this->saveVariables($componentId);
    }

    public function updateRepeaterSubField(string $componentId, string $fieldKey, int $index, string $subField, string $value): void
    {
        if (isset($this->variables[$componentId][$fieldKey][$index])) {
            $this->variables[$componentId][$fieldKey][$index][$subField] = $value;
            $this->saveVariables($componentId);
        }
    }

    public function updateRepeaterChildSubField(string $componentId, string $fieldKey, int $parentIndex, int $childIndex, string $subField, string $value): void
    {
        if (isset($this->variables[$componentId][$fieldKey][$parentIndex]['children'][$childIndex])) {
            $this->variables[$componentId][$fieldKey][$parentIndex]['children'][$childIndex][$subField] = $value;
            $this->saveVariables($componentId);
        }
    }

    public function removeRepeaterChild(string $componentId, string $fieldKey, int $parentIndex, int $childIndex): void
    {
        if (isset($this->variables[$componentId][$fieldKey][$parentIndex]['children'][$childIndex])) {
            array_splice($this->variables[$componentId][$fieldKey][$parentIndex]['children'], $childIndex, 1);
            $this->saveVariables($componentId);
        }
    }

    public function render()
    {
        return view('studio::livewire.component-editor');
    }
}
