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
                    $this->variables[$id][$key] = $config['default'] ?? '';
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
                $this->dispatch('variable-updated',
                    componentId: $componentId,
                    variables: $this->variables[$componentId]
                );
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

    public function render()
    {
        return view('studio::livewire.component-editor');
    }
}
