<?php

namespace Designer\Studio\Services;

use Illuminate\Support\Str;

class TemplateRegistry
{
    public function all(): array
    {
        return [
            'blank' => [
                'name' => 'blank',
                'title' => 'Blank',
                'description' => 'Start with an empty page and add sections as you go.',
                'pages' => [
                    [
                        'title' => 'Home Page',
                        'slug' => 'home',
                        'description' => '',
                        'components' => [],
                    ],
                ],
            ],
            'starter' => [
                'name' => 'starter',
                'title' => 'Starter',
                'description' => 'A home page with a hero, features grid, and call-to-action section.',
                'pages' => [
                    [
                        'title' => 'Home Page',
                        'slug' => 'home',
                        'description' => 'Sample home page',
                        'components' => [
                            [
                                'id' => null,
                                'component_ref' => 'hero-basic',
                                'order' => 0,
                                'variables' => [],
                            ],
                            [
                                'id' => null,
                                'component_ref' => 'features-grid',
                                'order' => 1,
                                'variables' => [],
                            ],
                            [
                                'id' => null,
                                'component_ref' => 'cta-section',
                                'order' => 2,
                                'variables' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function find(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }
}
