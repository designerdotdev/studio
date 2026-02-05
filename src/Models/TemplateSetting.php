<?php

namespace Designer\Studio\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateSetting extends Model
{
    protected $fillable = [
        'template',
        'key',
        'value',
        'type',
    ];

    /**
     * Get all settings for a specific template as key-value pairs.
     */
    public static function getSettingsForTemplate(string $template): array
    {
        return static::where('template', $template)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Update or create a setting for a template.
     */
    public static function setSetting(string $template, string $key, mixed $value, string $type): static
    {
        return static::updateOrCreate(
            ['template' => $template, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
