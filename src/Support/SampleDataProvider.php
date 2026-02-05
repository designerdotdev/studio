<?php

namespace Designer\Studio\Support;

use Designer\Studio\Contracts\ComponentDataProvider;

/**
 * A sample data provider showing the expected data structure.
 * Extend or reference this when creating your own data provider.
 */
class SampleDataProvider implements ComponentDataProvider
{
    public function getComponents(): array
    {
        // Return an array keyed by component ID
        return [
            1 => [
                'id' => 1,
                'name' => 'hero-section',
                'html' => '<section class="bg-gray-100 py-20"><div class="container mx-auto text-center"><h1 class="text-4xl font-bold">{{ $title ?? "Welcome" }}</h1><p class="mt-4 text-xl">{{ $subtitle ?? "Your subtitle here" }}</p></div></section>',
                'title' => 'Hero Section',
                'description' => 'The main hero section at the top of the page',
                'fields' => [
                    'title' => [
                        'type' => 'text',
                        'label' => 'Title',
                        'default' => 'Welcome to our site',
                    ],
                    'subtitle' => [
                        'type' => 'textarea',
                        'label' => 'Subtitle',
                        'default' => 'Your subtitle here',
                    ],
                ],
            ],
            2 => [
                'id' => 2,
                'name' => 'features-section',
                'html' => '<section class="py-16"><div class="container mx-auto"><h2 class="text-3xl font-bold text-center">{{ $features_title ?? "Features" }}</h2></div></section>',
                'title' => 'Features Section',
                'description' => 'Showcase your product features',
                'fields' => [
                    'features_title' => [
                        'type' => 'text',
                        'label' => 'Section Title',
                        'default' => 'Our Features',
                    ],
                ],
            ],
        ];
    }

    public function getVariables(): array
    {
        $variables = [];

        foreach ($this->getComponents() as $component) {
            if (!empty($component['fields'])) {
                foreach ($component['fields'] as $key => $config) {
                    $variables[$key] = $config['default'] ?? '';
                }
            }
        }

        return $variables;
    }
}
