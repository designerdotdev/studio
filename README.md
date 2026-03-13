# Designer Studio

A visual page builder for Laravel applications. Design pages with a live preview editor and export them as Blade files.

## Features

- Visual page editor with iframe-based live preview
- Component library with customizable fields
- Repeater fields for dynamic lists and nested menu builders
- JSON-based storage (no database required)
- Automatic page routing (pages become live routes instantly)
- Export to Blade files
- Easy install and uninstall

## Installation

```bash
composer require designer/studio
```

## Quick Start

1. **Visit the studio**:
   ```
   http://your-app.test/studio
   ```

2. **Choose a template** from the onboarding screen, or create a blank page

3. **Edit a page** by clicking on components in the live preview

4. **Generate Blade files** when you're ready to export

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

    // Default layout component for generated pages
    'default_layout' => 'layout',

    // Automatically regenerate Blade files on save
    'auto_generate' => true,

    // Auto-routing: pages become live public routes without generation
    'page_routing' => [
        'enabled' => true,
        'middleware' => ['web'],
        'home_slug' => 'home',
    ],

    // Editor sidebar position: 'left' or 'right'
    'sidebar_position' => 'left',

    // Iframe preview settings (Tailwind CDN, Alpine.js, custom styles/scripts)
    'iframe' => [
        'tailwind_cdn' => true,
        'alpine_cdn' => true,
        // ...
    ],
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
    └── header-nav.json
```

### Output

Generated Blade files are placed in `resources/views/designer/`:

```
resources/views/designer/
├── home.blade.php
└── about.blade.php
```

## Creating Components

Components are defined as a pair of YAML + HTML files in `resources/views/designer/`:

```
resources/views/designer/
├── heroes/
│   ├── hero-01.yml
│   └── hero-01.html
├── headers/
│   ├── header-01.yml
│   └── header-01.html
└── features/
    ├── features-01.yml
    └── features-01.html
```

### YAML Definition

The `.yml` file defines the component metadata and editable fields:

```yaml
name: hero-basic
title: Basic Hero Section
description: A simple hero with title, subtitle, and CTA
category: heroes
tags:
    - hero
    - landing

fields:
    heading:
        type: text
        label: Heading
        default: "Build faster."
        required: true

    description:
        type: textarea
        label: Description
        default: "A short description of your product."

    show_cta:
        type: toggle
        label: Show CTA Button
        default: true
```

### HTML Template

The `.html` file contains the component markup using Blade syntax:

```html
<section class="py-20 text-center">
    <h1>{{ $heading ?? 'Build faster.' }}</h1>
    <p>{{ $description ?? 'A short description.' }}</p>
    @if($show_cta ?? false)
        <a href="#">Get Started</a>
    @endif
</section>
```

Variables use standard Blade syntax: `{{ $var ?? 'default' }}` for escaped output and `{!! $var ?? '' !!}` for raw HTML.

## Field Types

### Basic Fields

| Type | Description | Example |
|------|-------------|---------|
| `text` | Single line text input | Headings, button labels, URLs |
| `textarea` | Multi-line text input | Descriptions, SVG code |
| `select` | Dropdown with predefined options | Alignment, style variants |
| `toggle` | Boolean on/off switch | Show/hide elements |
| `colorpicker` | Color picker | Background colors, accents |

### Repeater

The `repeater` field type allows dynamic lists of items with defined sub-fields. Use it for navigation links, feature lists, testimonials, or any repeating content.

```yaml
fields:
    features:
        type: repeater
        label: Features
        add_button_label: "Add Feature"
        sub_fields:
            title:
                type: text
                label: Title
                default: "Feature"
            description:
                type: text
                label: Description
                default: "Feature description"
```

In the HTML template, use `@foreach` to loop over repeater items:

```html
<div class="grid grid-cols-3 gap-8">
    @foreach($features as $feature)
        <div>
            <h3>{{ $feature['title'] }}</h3>
            <p>{{ $feature['description'] }}</p>
        </div>
    @endforeach
</div>
```

#### Nestable Repeaters (Menu Builder)

Add `nestable: true` to enable hierarchical nesting, turning the repeater into a menu builder. Each item automatically gets a `children` array. Use `max_depth` to limit nesting levels.

```yaml
fields:
    nav_links:
        type: repeater
        label: Navigation Links
        nestable: true
        max_depth: 2
        add_button_label: "Add Link"
        sub_fields:
            text:
                type: text
                label: Link Text
                default: "Link"
            url:
                type: text
                label: URL
                default: "#"
        default:
            - text: "Home"
              url: "/"
            - text: "About"
              url: "/about"
```

In the HTML template, check `children` to render dropdowns:

```html
<nav>
    @foreach($nav_links as $link)
        @if(count($link['children']) > 0)
            <div class="dropdown">
                <span>{{ $link['text'] }}</span>
                <div class="dropdown-menu">
                    @foreach($link['children'] as $child)
                        <a href="{{ $child['url'] }}">{{ $child['text'] }}</a>
                    @endforeach
                </div>
            </div>
        @else
            <a href="{{ $link['url'] }}">{{ $link['text'] }}</a>
        @endif
    @endforeach
</nav>
```

The sidebar UI provides:
- Add/remove items
- Reorder with up/down arrows
- Indent (make child of item above) and outdent (move back to top level) for nestable repeaters
- Inline editing of sub-fields with live preview updates

#### Repeater Data Structure

Repeater data is stored as arrays within the existing page JSON:

```json
{
    "variables": {
        "company_name": "Acme",
        "nav_links": [
            { "text": "Home", "url": "/", "children": [] },
            { "text": "Products", "url": "#", "children": [
                { "text": "Widget A", "url": "/widget-a", "children": [] }
            ]}
        ]
    }
}
```

Flat repeaters (without `nestable: true`) omit the `children` key.

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
