# Designer Studio

A visual component builder/editor for Laravel applications. Build beautiful pages visually and export them as Blade files.

## Features

- Visual page editor with live preview
- Component library with customizable fields
- JSON-based storage (no database pollution)
- Export to Blade files
- Easy install and uninstall

## Installation

Add the package repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/designerdotdev/studio.git"
        }
    ]
}
```

Then require the package:

```bash
composer require designer/studio
```

## Quick Start

1. **Seed sample data** (optional):
   ```bash
   php artisan studio:seed
   ```

2. **Visit the studio**:
   ```
   http://your-app.test/studio
   ```

3. **Edit a page** by clicking on it in the dashboard

4. **Generate Blade files** by clicking "Generate All Blade Files"

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=studio-config
```

### Config Options

```php
return [
    // Route prefix (e.g., 'admin/studio' → /admin/studio)
    'path' => 'studio',

    // Middleware for studio routes
    'middleware' => ['web'],

    // Where JSON data is stored
    'storage_path' => storage_path('studio'),

    // Where generated Blade files go
    'output_path' => resource_path('views/designer'),

    // Default layout for generated pages
    'default_layout' => 'layouts.app',
];
```

## How It Works

### Storage

All data is stored as JSON files in `storage/studio/`:

```
storage/studio/
├── pages/
│   ├── home.json
│   └── about.json
└── components/library/
    ├── hero-basic.json
    └── features-grid.json
```

### Output

Generated Blade files are placed in `resources/views/designer/`:

```
resources/views/designer/
├── home.blade.php
└── about.blade.php
```

## Creating Components

Components are JSON files with this structure:

```json
{
  "name": "hero-basic",
  "title": "Basic Hero Section",
  "description": "A simple hero with title and subtitle",
  "category": "heroes",
  "html": "<section class=\"py-20\"><h1>{{ $title ?? \"Welcome\" }}</h1></section>",
  "fields": {
    "title": {
      "type": "text",
      "label": "Title",
      "default": "Welcome"
    }
  }
}
```

### Field Types

- `text` - Single line text input
- `textarea` - Multi-line text
- `select` - Dropdown with options
- `toggle` - Boolean switch
- `colorpicker` - Color picker

## Artisan Commands

```bash
# Seed sample components and page
php artisan studio:seed

# Uninstall and remove all data
php artisan studio:uninstall

# Uninstall but keep generated Blade files
php artisan studio:uninstall --keep-generated

# Uninstall but keep JSON data
php artisan studio:uninstall --keep-data
```

## Uninstall

To completely remove Designer Studio:

```bash
# Remove data
php artisan studio:uninstall

# Remove package
composer remove designer/studio

# Remove config (if published)
rm config/studio.php
```

## License

MIT
