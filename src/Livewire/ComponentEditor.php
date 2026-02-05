<?php

namespace Designer\Studio\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class ComponentEditor extends Component
{
    public array $components = [];

    public array $variables = [];

    public ?int $selectedComponentId = null;

    public ?array $selectedComponent = null;

    public function mount(array $components = []): void
    {
        $this->components = $components;

        // Initialize variables with defaults from all components
        foreach ($components as $component) {
            if (!empty($component['fields'])) {
                foreach ($component['fields'] as $key => $config) {
                    $this->variables[$key] = $config['default'] ?? '';
                }
            }
        }
    }

    #[On('component-selected')]
    public function selectComponent(int $componentId): void
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
        }
    }

    public function render()
    {
        return view('studio::livewire.component-editor');
    }
}
