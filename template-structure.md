# Design Component Templates Storage Strategy

This document outlines the architecture for storing and serving ~100+ design component templates (headers, navs, features, pricing, footers, etc.) with their accompanying YAML field definition files.

## Requirements

- **Frequent updates** - New templates added weekly or more
- **Offline support** - Base/default templates must work without internet
- **Multiple tiers planned** - Free and premium template collections
- **Scalability** - Support for 100+ templates

---

## Architecture: Local Defaults + S3 Remote

```
┌─────────────────────────────────────────────────────────────────┐
│                        TEMPLATE SOURCES                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐         ┌──────────────────────────────┐  │
│  │  LOCAL (Bundled) │         │     S3 / CDN (Remote)        │  │
│  │                  │         │                              │  │
│  │  • 10-15 free    │         │  • Full template library     │  │
│  │    defaults      │         │  • Versioned manifests       │  │
│  │  • Works offline │         │  • Tier-gated access         │  │
│  │  • Seeded on     │         │  • Frequent updates          │  │
│  │    install       │         │  • CDN for fast delivery     │  │
│  └────────┬─────────┘         └─────────────┬────────────────┘  │
│           │                                 │                    │
│           └──────────────┬──────────────────┘                    │
│                          ▼                                       │
│              ┌───────────────────────┐                           │
│              │   TemplateLoader      │                           │
│              │   Service             │                           │
│              └───────────┬───────────┘                           │
│                          ▼                                       │
│              ┌───────────────────────┐                           │
│              │  Local Cache          │                           │
│              │  storage/components/  │                           │
│              │  remote-cache/        │                           │
│              └───────────────────────┘                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## File Structure

### Studio Package (Local Defaults)

```
packages/designer/studio/
├── database/seeders/
│   └── DefaultTemplatesSeeder.php
├── resources/templates/defaults/
│   ├── manifest.json              # List of bundled templates
│   ├── heroes/
│   │   ├── basic-hero.json
│   │   └── basic-hero.yml
│   ├── features/
│   │   ├── simple-features.json
│   │   └── simple-features.yml
│   └── footers/
│       ├── basic-footer.json
│       └── basic-footer.yml
└── config/studio.php              # Remote endpoint config
```

### S3 Bucket Structure

```
s3://designer-templates/
├── manifests/
│   ├── free.json                  # Free tier manifest
│   └── premium.json               # Premium tier manifest
├── v1/                            # Versioned templates
│   ├── heroes/
│   │   ├── modern-hero.json
│   │   ├── modern-hero.yml
│   │   ├── gradient-hero.json
│   │   └── gradient-hero.yml
│   ├── navs/
│   ├── features/
│   ├── pricing/
│   ├── testimonials/
│   ├── cta/
│   └── footers/
└── v2/                            # Future version
```

### Local Cache (Runtime)

```
storage/designer-studio/
├── components/
│   ├── library/                   # User-created components
│   └── remote-cache/              # Downloaded from S3
│       ├── manifests/
│       │   ├── free.json
│       │   └── premium.json
│       └── templates/
│           ├── modern-hero.json
│           └── modern-hero.yml
└── cache-meta.json                # Cache timestamps, versions
```

---

## Implementation Plan

### 1. Create TemplateLoader Service

**File:** `src/Services/TemplateLoader.php`

Responsibilities:
- Load local bundled templates
- Fetch remote manifests from S3
- Download individual templates on-demand
- Cache management (TTL, invalidation)
- Tier access control (free vs premium)

### 2. Update Configuration

**File:** `config/studio.php`

```php
'templates' => [
    // Local bundled templates
    'local_path' => resource_path('templates/defaults'),

    // Remote template source
    'remote' => [
        'enabled' => env('STUDIO_REMOTE_TEMPLATES', true),
        'base_url' => env('STUDIO_TEMPLATES_URL', 'https://cdn.example.com/templates'),
        'version' => 'v1',
    ],

    // Caching
    'cache' => [
        'path' => storage_path('designer-studio/components/remote-cache'),
        'manifest_ttl' => 3600,      // Re-fetch manifest hourly
        'template_ttl' => 86400,     // Cache templates for 24h
    ],
],
```

### 3. Create Manifest Schema

**File:** `manifests/free.json` (example)

```json
{
  "version": "1.0.0",
  "updated_at": "2026-02-05T00:00:00Z",
  "tier": "free",
  "categories": [
    {
      "slug": "heroes",
      "name": "Hero Sections",
      "templates": [
        {
          "id": "modern-hero",
          "name": "Modern Hero",
          "version": "1.0.0",
          "preview_url": "https://cdn.../previews/modern-hero.png"
        }
      ]
    }
  ]
}
```

### 4. Add Template Sync Command

**File:** `src/Console/Commands/SyncTemplates.php`

```bash
# Sync all remote templates
php artisan studio:sync-templates

# Sync specific tier
php artisan studio:sync-templates --tier=premium

# Force refresh cache
php artisan studio:sync-templates --fresh
```

### 5. Update ComponentRepository

Modify `src/Services/Storage/ComponentRepository.php` to:
- Merge local, cached remote, and user components
- Support filtering by source (local/remote/user)
- Support filtering by tier

### 6. Seed Default Templates

**File:** `database/seeders/DefaultTemplatesSeeder.php`

Called during package installation to populate initial templates.

---

## Why This Architecture

| Requirement | How It's Addressed |
|-------------|-------------------|
| **Frequent updates** | S3 with manifest versioning, hourly cache refresh |
| **Offline support** | Bundled defaults in package, cached remote templates |
| **Multiple tiers** | Separate manifests per tier, access control in TemplateLoader |
| **Scalability** | S3/CDN handles any number of templates |
| **Performance** | Local cache, lazy loading, CDN delivery |

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `src/Services/TemplateLoader.php` | Create |
| `src/Services/Storage/ComponentRepository.php` | Modify |
| `src/Console/Commands/SyncTemplates.php` | Create |
| `config/studio.php` | Modify |
| `resources/templates/defaults/` | Create directory + seed files |
| `database/seeders/DefaultTemplatesSeeder.php` | Create |

---

## Verification Steps

1. Run `php artisan studio:sync-templates` and verify templates download
2. Disconnect network, confirm local defaults still load
3. Test tier filtering in component browser
4. Verify cache invalidation when manifest version changes
