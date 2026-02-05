<?php

namespace Designer\Studio\Livewire;

use Livewire\Component;

/**
 * Template Editor - placeholder for future template editing functionality.
 *
 * This component is currently not used in the JSON-based storage architecture.
 * It may be reimplemented in the future for template-level editing.
 */
class TemplateEditor extends Component
{
    public ?array $data = [];

    public string $template = '';

    public function mount(string $template = ''): void
    {
        $this->template = $template;
    }

    public function render()
    {
        return view('studio::livewire.template-editor');
    }
}
