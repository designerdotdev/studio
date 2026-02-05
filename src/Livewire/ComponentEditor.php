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

        // Initialize variables with values from all components
        foreach ($components as $component) {
            // Use instance variables or fall back to field defaults
            if (!empty($component['variables'])) {
                foreach ($component['variables'] as $key => $value) {
                    $this->variables[$key] = $value;
                }
            }

            // Fill in any missing fields with defaults
            if (!empty($component['fields'])) {
                foreach ($component['fields'] as $key => $config) {
                    if (!isset($this->variables[$key])) {
                        $this->variables[$key] = $config['default'] ?? '';
                    }
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
            $this->dispatch('variable-updated', variables: $this->variables);
            $this->saveVariables();
        }
    }

    protected function saveVariables(): void
    {
        if (!$this->pageSlug || !$this->selectedComponentId) {
            return;
        }

        $pageRepository = app(PageRepository::class);
        $pageRepository->updateComponentVariables(
            $this->pageSlug,
            $this->selectedComponentId,
            $this->variables
        );
    }

    public function render()
    {
        return view('studio::livewire.component-editor');
    }
}
