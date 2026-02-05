# Designer Studio

A visual component builder/editor for Laravel applications.

## Installation

Add the package repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/designer/studio"
        }
    ]
}
```

Then require the package:

```bash
composer require designer/studio
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=studio-config
```

### Setting up a Data Provider

Create a class that implements `Designer\Studio\Contracts\ComponentDataProvider`:

```php
<?php

namespace App\Providers;

use App\Models\Project;
use Designer\Studio\Contracts\ComponentDataProvider;
use Symfony\Component\Yaml\Yaml;

class StudioDataProvider implements ComponentDataProvider
{
    public function getComponents(): array
    {
        $project = Project::with(['pages.components' => function ($query) {
            $query->orderBy('order');
        }])->findOrFail(1);

        $page = $project->pages->first();
        $components = [];

        if ($page) {
            foreach ($page->components as $component) {
                $componentData = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'html' => $component->html,
                    'fields' => [],
                ];

                if ($component->yml) {
                    $yaml = Yaml::parse($component->yml);
                    $componentData['title'] = $yaml['name'] ?? $component->name;
                    $componentData['description'] = $yaml['description'] ?? '';
                    $componentData['fields'] = $yaml['fields'] ?? [];
                }

                $components[$component->id] = $componentData;
            }
        }

        return $components;
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
```

Then register it in your `config/studio.php`:

```php
return [
    'data_provider' => \App\Providers\StudioDataProvider::class,
    // ...
];
```

## Usage

Once installed and configured, visit `/designer/studio` in your application to access the visual editor.

### Customizing the Route

You can change the route prefix in `config/studio.php`:

```php
return [
    'path' => 'admin/designer', // Now accessible at /admin/designer
    // ...
];
```

### Adding Middleware

Protect the studio with authentication or other middleware:

```php
return [
    'middleware' => ['web', 'auth'],
    // ...
];
```

## Publishing Assets

### Views

```bash
php artisan vendor:publish --tag=studio-views
```

### JavaScript

```bash
php artisan vendor:publish --tag=studio-assets
```

### Migrations

```bash
php artisan vendor:publish --tag=studio-migrations
```

## License

MIT
