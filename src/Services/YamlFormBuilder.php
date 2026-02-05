<?php

namespace Designer\Studio\Services;

use Designer\Studio\Models\TemplateSetting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Symfony\Component\Yaml\Yaml;

class YamlFormBuilder
{
    /**
     * Parse a YAML file and return its contents.
     */
    public static function parseYaml(string $templateName): array
    {
        $templatesPath = config('studio.templates_path', resource_path('views/templates'));
        $yamlPath = "{$templatesPath}/{$templateName}.yml";

        if (!file_exists($yamlPath)) {
            return [];
        }

        return Yaml::parseFile($yamlPath);
    }

    /**
     * Build Filament form fields from YAML configuration.
     */
    public static function buildFormFields(string $templateName): array
    {
        $yaml = static::parseYaml($templateName);

        if (empty($yaml['fields'])) {
            return [];
        }

        // Get current saved values
        $savedValues = TemplateSetting::getSettingsForTemplate($templateName);

        $fields = [];

        foreach ($yaml['fields'] as $key => $config) {
            $type = $config['type'] ?? 'text';
            $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $key));
            $default = $config['default'] ?? '';

            // Use saved value if exists, otherwise use default
            $value = $savedValues[$key] ?? $default;

            $field = match ($type) {
                'text' => TextInput::make($key)
                    ->label($label)
                    ->default($value),

                'textarea' => Textarea::make($key)
                    ->label($label)
                    ->rows(4)
                    ->default($value),

                'richeditor' => RichEditor::make($key)
                    ->label($label)
                    ->default($value),

                'markdown' => MarkdownEditor::make($key)
                    ->label($label)
                    ->default($value),

                'select' => Select::make($key)
                    ->label($label)
                    ->options($config['options'] ?? [])
                    ->default($value),

                'toggle' => Toggle::make($key)
                    ->label($label)
                    ->default((bool) $value),

                'colorpicker' => ColorPicker::make($key)
                    ->label($label)
                    ->default($value),

                'date-time-picker' => DateTimePicker::make($key)
                    ->label($label)
                    ->default($value),

                'image' => FileUpload::make($key)
                    ->label($label)
                    ->image()
                    ->imageEditor()
                    ->directory('template-images')
                    ->default($value),

                'tagsinput' => TagsInput::make($key)
                    ->label($label)
                    ->default($value ? explode(',', $value) : []),

                'keyvalue' => KeyValue::make($key)
                    ->label($label)
                    ->default($value ? json_decode($value, true) : []),

                default => TextInput::make($key)
                    ->label($label)
                    ->default($value),
            };

            // Add required validation if specified
            if (!empty($config['required'])) {
                $field->required();
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Save form data to template settings.
     */
    public static function saveFormData(string $templateName, array $data): void
    {
        $yaml = static::parseYaml($templateName);

        if (empty($yaml['fields'])) {
            return;
        }

        foreach ($yaml['fields'] as $key => $config) {
            $type = $config['type'] ?? 'text';
            $value = $data[$key] ?? $config['default'] ?? '';

            // Handle special types
            if ($type === 'tagsinput' && is_array($value)) {
                $value = implode(',', $value);
            } elseif ($type === 'keyvalue' && is_array($value)) {
                $value = json_encode($value);
            } elseif ($type === 'toggle') {
                $value = $value ? '1' : '0';
            }

            TemplateSetting::setSetting($templateName, $key, $value, $type);
        }
    }

    /**
     * Initialize template settings with defaults from YAML.
     */
    public static function initializeDefaults(string $templateName): void
    {
        $yaml = static::parseYaml($templateName);

        if (empty($yaml['fields'])) {
            return;
        }

        foreach ($yaml['fields'] as $key => $config) {
            $type = $config['type'] ?? 'text';
            $default = $config['default'] ?? '';

            // Only create if not exists
            $existing = TemplateSetting::where('template', $templateName)
                ->where('key', $key)
                ->first();

            if (!$existing) {
                TemplateSetting::setSetting($templateName, $key, $default, $type);
            }
        }
    }

    /**
     * Get variables for rendering a template.
     */
    public static function getTemplateVariables(string $templateName): array
    {
        // Initialize defaults if needed
        static::initializeDefaults($templateName);

        return TemplateSetting::getSettingsForTemplate($templateName);
    }
}
