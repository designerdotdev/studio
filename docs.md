# Designer Studio Documentation

A JSON-based visual page builder for Laravel applications that generates static Blade templates.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Directory Structure](#directory-structure)
- [Core Concepts](#core-concepts)
- [Iframe & Parent Window Communication](#iframe--parent-window-communication)
- [Livewire Components](#livewire-components)
- [JavaScript Layer](#javascript-layer)
- [Services](#services)
- [Data Transfer Objects](#data-transfer-objects)
- [Configuration](#configuration)
- [Routing](#routing)
- [Console Commands](#console-commands)
- [Complete Workflow](#complete-workflow)
- [Quick Reference](#quick-reference)

---

## Architecture Overview

Designer Studio uses a **stateless, JSON-based architecture** with no database storage. Key architectural principles:

- **JSON File Storage**: Pages and components stored as JSON in `storage/designer-studio/`
- **Immutable DTOs**: Data Transfer Objects with readonly properties for type safety
- **Singleton Services**: Core services registered as singletons via the service provider
- **postMessage API**: Secure iframe-parent communication
- **Client-side Blade Rendering**: Real-time preview without server round-trips

### Data Flow

```
User Input → Livewire Component → Repository → JSON Storage
                    ↓
            postMessage to iframe
                    ↓
        Client-side Blade Rendering
                    ↓
            Live Preview Update
```

---

## Directory Structure

```
src/
├── StudioServiceProvider.php          # Package bootstrap & service registration
├── Http/Controllers/
│   └── StudioController.php           # Route handlers
├── Livewire/
│   ├── ComponentEditor.php            # Main sidebar form component
│   └── TemplateEditor.php             # Legacy (unused)
├── Services/
│   ├── BladeGenerator.php             # Generates output Blade files
│   └── Storage/
│       ├── StudioStorage.php          # Low-level file I/O
│       ├── PageRepository.php         # Page CRUD operations
│       └── ComponentRepository.php    # Component library management
├── DataTransferObjects/
│   ├── PageData.php                   # Immutable page object
│   └── ComponentData.php              # Immutable component object
├── View/Components/Layouts/
│   ├── App.php                        # Main editor layout
│   └── Iframe.php                     # Preview iframe layout
└── Console/Commands/
    ├── SeedSampleData.php             # Initialize demo data
    └── Uninstall.php                  # Clean uninstall utility

resources/
├── views/
│   ├── home.blade.php                 # Main editor page (parent window)
│   ├── iframe.blade.php               # Preview iframe content
│   ├── dashboard.blade.php            # Page list/management
│   └── livewire/
│       └── component-editor.blade.php # Variable editing form
├── js/
│   ├── studio.js                      # Entry point, exports blade
│   └── blade.js                       # Client-side template renderer

config/studio.php                      # Package configuration
routes/web.php                         # Route definitions
```

### Storage Structure

```
storage/designer-studio/
├── pages/
│   ├── home.json
│   └── about.json
└── components/library/
    ├── hero-basic.json
    └── features-grid.json
```

---

## Core Concepts

### Pages

A page represents a single route/view in your application. Each page contains:

- **Metadata**: id, slug, title, description, layout
- **Components**: Array of component instances with variables

```json
{
  "id": "uuid",
  "slug": "home",
  "title": "Home Page",
  "layout": "layouts.app",
  "components": [
    {
      "id": "component-instance-uuid",
      "component_ref": "hero-basic",
      "order": 0,
      "variables": {
        "title": "Welcome"
      }
    }
  ]
}
```

### Components

Components are reusable building blocks with HTML templates and configurable fields:

```json
{
  "id": "uuid",
  "name": "hero-basic",
  "title": "Basic Hero Section",
  "category": "heroes",
  "html": "<section><h1>{{ $title ?? 'Default' }}</h1></section>",
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

Supported field types in `component-editor.blade.php`:

| Type | Renders As |
|------|------------|
| `text` | `<input type="text">` |
| `textarea` | `<textarea>` |
| `select` | `<select>` with options |
| `toggle` | Custom button toggle |
| `colorpicker` | `<input type="color">` |

---

## Iframe & Parent Window Communication

The editor uses a split-panel layout: the main area contains an iframe for preview, and the sidebar contains the Livewire component editor. Communication happens via the **postMessage API**.

### Message Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      PARENT WINDOW                               │
│                      (home.blade.php)                            │
│                                                                  │
│  ┌─────────────────────────┐    ┌─────────────────────────────┐ │
│  │         IFRAME          │    │         SIDEBAR             │ │
│  │                         │    │   <livewire:component-      │ │
│  │  ←──── postMessage ────→│    │         editor>             │ │
│  │                         │    │                             │ │
│  └─────────────────────────┘    └─────────────────────────────┘ │
│              ↑                              ↑                    │
│              │                              │                    │
│              └──── Livewire.dispatch() ─────┘                    │
└─────────────────────────────────────────────────────────────────┘
```

### Parent → Iframe Messages

**Sending from parent** (`home.blade.php`):

```javascript
// Alpine.js method in home.blade.php
sendToIframe(type, data) {
    if (this.iframe && this.iframe.contentWindow) {
        this.iframe.contentWindow.postMessage({ type, ...data }, '*');
    }
}

// Triggered when Livewire updates variables:
// x-on:variable-updated.window="sendToIframe('update-variables', { variables: $event.detail.variables })"
```

**Message type: `update-variables`**

Sent when a component variable changes in the sidebar form.

```javascript
// Payload structure
{
    type: 'update-variables',
    variables: { title: 'New Title', subtitle: '...' }
}
```

### Iframe → Parent Messages

**Sending from iframe** (`iframe.blade.php`):

```javascript
// When user clicks a component
selectComponent(componentId, event) {
    event.stopPropagation();
    this.selectedComponentId = componentId;
    window.parent.postMessage({
        type: 'component-selected',
        componentId: componentId
    }, '*');
}

// When clicking outside components
deselectAll() {
    this.selectedComponentId = null;
    window.parent.postMessage({
        type: 'component-deselected'
    }, '*');
}
```

**Message types:**

| Type | Payload | Purpose |
|------|---------|---------|
| `component-selected` | `{ componentId }` | User clicked a component |
| `component-deselected` | `{}` | User clicked outside components |

### Parent Receiving Messages

```javascript
// In home.blade.php Alpine x-data init()
window.addEventListener('message', (event) => {
    if (event.data.type === 'component-selected') {
        Livewire.dispatch('component-selected', {
            componentId: event.data.componentId
        });
    } else if (event.data.type === 'component-deselected') {
        Livewire.dispatch('component-deselected');
    }
});
```

### Iframe Receiving Messages

```javascript
// In iframe.blade.php Alpine x-data init()
window.addEventListener('message', (event) => {
    if (event.data.type === 'update-variables') {
        this.updateVariables(event.data.variables);
    }
});
```

---

## Livewire Components

### ComponentEditor.php

**Location**: `src/Livewire/ComponentEditor.php`

The main Livewire component that manages component selection and variable editing.

#### Properties

```php
public array $components = [];           // All page components with definitions
public array $variables = [];            // Merged variables from all components
public ?string $selectedComponentId = null;
public ?array $selectedComponent = null;
public string $pageSlug = '';
```

#### Key Methods

**`mount(array $components, string $pageSlug)`**

Initializes the component with page data. Merges instance variables with field defaults.

```php
public function mount(array $components, string $pageSlug): void
{
    $this->components = $components;
    $this->pageSlug = $pageSlug;

    // Build variables array with defaults
    foreach ($components as $id => $component) {
        foreach ($component['fields'] ?? [] as $key => $field) {
            $this->variables[$key] = $component['variables'][$key]
                ?? $field['default']
                ?? '';
        }
    }
}
```

**`selectComponent(string $componentId)` - Livewire Listener**

Receives the `component-selected` event from the parent window and updates the selected component.

```php
#[On('component-selected')]
public function selectComponent(string $componentId): void
{
    $this->selectedComponentId = $componentId;
    $this->selectedComponent = $this->components[$componentId] ?? null;
}
```

**`deselectComponent()` - Livewire Listener**

```php
#[On('component-deselected')]
public function deselectComponent(): void
{
    $this->selectedComponentId = null;
    $this->selectedComponent = null;
}
```

**`updated(string $property)` - Property Watcher**

Triggered automatically when any `$variables.*` property changes via `wire:model.live`.

```php
public function updated(string $property): void
{
    if (str_starts_with($property, 'variables.')) {
        // Dispatch event to parent window
        $this->dispatch('variable-updated', variables: $this->variables);

        // Persist to storage
        $this->saveVariables();
    }
}
```

**`saveVariables()` - Protected**

Persists variable changes to JSON storage.

```php
protected function saveVariables(): void
{
    if ($this->pageSlug && $this->selectedComponentId) {
        app(PageRepository::class)->updateComponentVariables(
            $this->pageSlug,
            $this->selectedComponentId,
            $this->variables
        );
    }
}
```

#### Blade Bindings

```blade
{{-- component-editor.blade.php --}}
<input
    type="text"
    wire:model.live.debounce.300ms="variables.{{ $key }}"
/>
```

The 300ms debounce prevents excessive saves while typing.

---

## JavaScript Layer

### blade.js - Client-Side Template Renderer

**Location**: `resources/js/blade.js`

Renders Blade syntax in the browser for real-time preview without server requests.

#### Main Function

```javascript
export function renderBladeTemplate(template, variables) {
    let result = template;

    // Processing order matters!
    // 1. @if($var ?? false)...@endif
    // 2. @if($var)...@endif
    // 3. @if($var ?? 'default')...@endif
    // 4. {{ $var ?? 'default' }}
    // 5. {!! $var ?? 'default' !!}
    // 6. {{ $var }}
    // 7. {!! $var !!}

    return result;
}
```

#### Supported Blade Syntax

| Syntax | Description |
|--------|-------------|
| `{{ $var }}` | Escaped output |
| `{!! $var !!}` | Unescaped output |
| `{{ $var ?? 'default' }}` | Escaped with default |
| `{!! $var ?? 'default' !!}` | Unescaped with default |
| `@if($var)...@endif` | Conditional |
| `@if($var ?? false)...@endif` | Conditional with null coalescing |

#### Helper Function

```javascript
export function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
```

### studio.js - Entry Point

```javascript
import blade from './blade.js';
window.blade = blade;
export { blade };
```

Makes the blade utility globally available on the window object.

### Using blade.js in the Iframe

```javascript
// In iframe.blade.php
renderComponents() {
    document.querySelectorAll('[data-component]').forEach(el => {
        const componentId = el.dataset.component;
        const template = this.templates[componentId];

        if (template && typeof blade !== 'undefined') {
            el.innerHTML = blade.renderBladeTemplate(template, this.variables);
        }
    });
}
```

---

## Services

### StudioStorage

**Location**: `src/Services/Storage/StudioStorage.php`

Low-level file system abstraction for JSON storage.

```php
// Configuration
$basePath = config('studio.storage_path'); // storage/designer-studio/

// Methods
$storage->read('pages/home.json');           // Returns array or null
$storage->write('pages/home.json', $data);   // Returns bool
$storage->delete('pages/home.json');         // Returns bool
$storage->exists('pages/home.json');         // Returns bool
$storage->list('pages', 'json');             // Returns array of slugs
$storage->ensureDirectoryExists('pages');    // Creates dir, returns path
$storage->purge();                           // Deletes everything
```

### PageRepository

**Location**: `src/Services/Storage/PageRepository.php`

Page CRUD operations using StudioStorage.

```php
// List all pages
$pages = $pageRepo->all(); // Collection<PageData>

// Get single page
$page = $pageRepo->find('home'); // PageData or null

// Create page
$page = $pageRepo->create([
    'title' => 'About Us',
    'slug' => 'about',  // optional, auto-generated from title
]);

// Update page
$page = $pageRepo->update('home', ['title' => 'New Title']);

// Delete page
$pageRepo->delete('home');

// Component operations
$pageRepo->addComponent('home', 'hero-basic', ['title' => 'Hi'], 0);
$pageRepo->updateComponentVariables('home', $componentId, $variables);
$pageRepo->removeComponent('home', $componentId);
$pageRepo->reorderComponents('home', [$id1, $id2, $id3]);
```

### ComponentRepository

**Location**: `src/Services/Storage/ComponentRepository.php`

Component library management.

```php
// List all components
$components = $componentRepo->all(); // Collection<ComponentData>

// Get by name
$component = $componentRepo->find('hero-basic');

// Filter by category
$heroes = $componentRepo->byCategory('heroes');

// Get unique categories
$categories = $componentRepo->categories(); // ['heroes', 'features', ...]

// CRUD
$componentRepo->create($data);
$componentRepo->update('hero-basic', $data);
$componentRepo->delete('hero-basic');
```

### BladeGenerator

**Location**: `src/Services/BladeGenerator.php`

Generates publishable Blade files from page data.

```php
// Generate single page
$path = $generator->generatePage('home');
// Returns: resources/views/designer/home.blade.php

// Generate all pages
$paths = $generator->generateAll();

// Generate component partials
$paths = $generator->generateComponentPartials();
// Creates: resources/views/designer/partials/_hero-basic.blade.php

// Clean up
$generator->purge();
```

#### Generated Output Example

```blade
{{--
    Generated by Designer Studio
    Page: Home Page
    Generated at: 2026-02-05T...
    WARNING: Manual edits will be overwritten when regenerated.
--}}

@extends('layouts.app')

@section('content')

    {{-- Basic Hero Section --}}
    <section class="...">
        <h1>Welcome to Designer Studio</h1>
    </section>

@endsection
```

---

## Data Transfer Objects

### PageData

**Location**: `src/DataTransferObjects/PageData.php`

Immutable page data object with readonly properties.

```php
class PageData
{
    public readonly string $id;
    public readonly string $slug;
    public readonly string $title;
    public readonly string $description;
    public readonly ?string $layout;
    public readonly string $created_at;
    public readonly string $updated_at;
    public readonly array $meta;
    public readonly array $components;

    public static function fromArray(array $data): self;
    public function toArray(): array;
}

// Usage
$page = PageData::fromArray($jsonData);
echo $page->title;
```

### ComponentData

**Location**: `src/DataTransferObjects/ComponentData.php`

Immutable component definition.

```php
class ComponentData
{
    public readonly string $id;
    public readonly string $name;
    public readonly string $title;
    public readonly string $description;
    public readonly string $category;
    public readonly array $tags;
    public readonly string $version;
    public readonly string $created_at;
    public readonly string $updated_at;
    public readonly string $html;
    public readonly array $fields;
    public readonly array $preview_variables;
    public readonly string $source;

    public static function fromArray(array $data): self;
    public function toArray(): array;
}
```

---

## Configuration

**Location**: `config/studio.php`

```php
return [
    // Route prefix for studio access
    'path' => 'designer/studio',

    // Middleware applied to all routes
    'middleware' => ['web'],

    // JSON storage location
    'storage_path' => storage_path('designer-studio'),

    // Generated Blade output directory
    'output_path' => resource_path('views/designer'),

    // Default layout for generated pages
    'default_layout' => 'layouts.app',

    // Auto-regenerate Blade on save
    'auto_generate' => true,

    // Designer.dev API (Pro feature)
    'api' => [
        'enabled' => false,
        'base_url' => 'https://api.designer.dev',
        'key' => env('DESIGNER_API_KEY'),
    ],
];
```

---

## Routing

**Location**: `routes/web.php`

All routes use the configured prefix and middleware.

### View Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/` | `studio.index` | `StudioController@index` |
| GET | `/page/{slug}` | `studio.page.edit` | `StudioController@edit` |
| GET | `/page/{slug}/iframe` | `studio.page.iframe` | `StudioController@iframe` |

### API Routes

| Method | URI | Name | Purpose |
|--------|-----|------|---------|
| POST | `/api/pages` | `studio.api.pages.store` | Create page |
| DELETE | `/api/pages/{slug}` | `studio.api.pages.destroy` | Delete page |
| PUT | `/api/pages/{slug}/components` | `studio.api.pages.components.update` | Update components |
| POST | `/api/generate` | `studio.api.generate` | Generate all Blade files |
| POST | `/api/generate/{slug}` | `studio.api.generate.page` | Generate single page |

---

## Console Commands

### Seed Sample Data

```bash
php artisan studio:seed
```

Creates sample components (hero-basic, features-grid, cta-section) and a home page.

### Uninstall

```bash
php artisan studio:uninstall

# Options
--force           # Skip confirmation prompts
--keep-generated  # Keep generated Blade files
--keep-data       # Keep JSON data files
```

---

## Complete Workflow

Here's the full event chain when a user edits a component variable:

```
1. USER CLICKS COMPONENT IN IFRAME
   ↓
   iframe.blade.php: selectComponent(componentId)
   - Updates DOM (adds .selected class)
   - Calls window.parent.postMessage({ type: 'component-selected', componentId })

2. PARENT RECEIVES MESSAGE
   ↓
   home.blade.php: window.addEventListener('message')
   - Sees event.data.type === 'component-selected'
   - Calls Livewire.dispatch('component-selected', { componentId })

3. LIVEWIRE UPDATES STATE
   ↓
   ComponentEditor.php: selectComponent()
   - Sets $selectedComponentId and $selectedComponent
   - Blade view re-renders with form

4. USER EDITS VARIABLE IN FORM
   ↓
   component-editor.blade.php: wire:model.live.debounce.300ms="variables.title"
   - After 300ms debounce, Livewire syncs property

5. LIVEWIRE PROPERTY WATCHER FIRES
   ↓
   ComponentEditor.php: updated('variables.title')
   - Dispatches 'variable-updated' event with all variables
   - Calls saveVariables()

6. DATA PERSISTS TO STORAGE
   ↓
   PageRepository.php: updateComponentVariables()
   - Reads page JSON
   - Merges new variables
   - Writes updated JSON

7. PARENT SENDS UPDATE TO IFRAME
   ↓
   home.blade.php: x-on:variable-updated.window
   - Calls sendToIframe('update-variables', { variables })
   - Uses iframe.contentWindow.postMessage()

8. IFRAME RE-RENDERS
   ↓
   iframe.blade.php: message listener
   - Receives 'update-variables' message
   - Updates this.variables
   - Calls renderComponents()
   - Uses blade.renderBladeTemplate() for each component
   - Updates innerHTML
   - Preview shows changes immediately!
```

---

## Quick Reference

### Adding a New Field Type

1. Add the field type handling in `component-editor.blade.php`:

```blade
@elseif($field['type'] === 'newtype')
    <input type="newtype" wire:model.live="variables.{{ $key }}" />
@endif
```

### Creating a New Component

```json
{
  "name": "my-component",
  "title": "My Component",
  "category": "custom",
  "html": "<div>{{ $content ?? 'Default content' }}</div>",
  "fields": {
    "content": {
      "type": "textarea",
      "label": "Content",
      "default": "Default content"
    }
  }
}
```

Save to `storage/designer-studio/components/library/my-component.json`

### Adding a New postMessage Type

1. **Iframe side** - Send the message:
```javascript
window.parent.postMessage({ type: 'my-event', data: 'value' }, '*');
```

2. **Parent side** - Handle in message listener:
```javascript
if (event.data.type === 'my-event') {
    Livewire.dispatch('my-event', { data: event.data.data });
}
```

3. **Livewire side** - Add listener:
```php
#[On('my-event')]
public function handleMyEvent($data): void
{
    // Handle event
}
```

### Key Files to Modify

| Task | Files |
|------|-------|
| Change editor UI | `home.blade.php`, `component-editor.blade.php` |
| Change preview behavior | `iframe.blade.php`, `blade.js` |
| Add storage features | `PageRepository.php`, `ComponentRepository.php` |
| Change generation output | `BladeGenerator.php` |
| Add routes | `routes/web.php`, `StudioController.php` |
| Add configuration | `config/studio.php` |

### Service Registration

All services are registered as singletons in `StudioServiceProvider.php`:

```php
$this->app->singleton(StudioStorage::class);
$this->app->singleton(PageRepository::class);
$this->app->singleton(ComponentRepository::class);
$this->app->singleton(BladeGenerator::class);
```
