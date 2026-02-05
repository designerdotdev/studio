<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\YamlFormBuilder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class TemplateEditor extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public string $template = '';

    public function mount(string $template = 'template-01'): void
    {
        $this->template = $template;

        // Initialize the form with current values
        $variables = YamlFormBuilder::getTemplateVariables($this->template);
        $this->form->fill($variables);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(YamlFormBuilder::buildFormFields($this->template))
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        YamlFormBuilder::saveFormData($this->template, $data);

        // Dispatch event to refresh the iframe
        $this->dispatch('template-updated');

        Notification::make()
            ->title('Saved successfully')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('studio::livewire.template-editor');
    }
}
