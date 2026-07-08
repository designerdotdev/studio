<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Studio Route Prefix
    |--------------------------------------------------------------------------
    |
    | This is the URI prefix where Studio will be accessible from. You can
    | change this to whatever you'd like. For example, if you set this to
    | 'admin/studio', Studio will be available at /admin/studio.
    |
    */
    'path' => 'studio',

    /*
    |--------------------------------------------------------------------------
    | Studio Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Studio route. In production
    | you should protect the editor — add 'auth' (or your own middleware)
    | so only authorized users can open the Studio and its API:
    |
    |     'middleware' => ['web', 'auth'],
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Draft Mode
    |--------------------------------------------------------------------------
    |
    | When enabled (the default), edits made in the Studio are saved to a
    | draft of the whole site and are NOT live until you hit Publish. The
    | draft is viewable at /studio/preview (and /studio/preview/{slug}),
    | behind the same middleware as the editor. Publishing mirrors the
    | draft onto the live site in one step; discarding restores the draft
    | from live. Disable to make every edit live immediately.
    |
    */
    'draft_mode' => true,

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    |
    | Optionally require a Gate ability for every Studio route (applied as
    | the `can:` middleware). Define the gate in a service provider:
    |
    |     Gate::define('viewStudio', fn ($user) => $user->isAdmin());
    |
    | and set 'gate' => 'viewStudio'. Leave null to disable.
    |
    */
    'gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Where Designer Studio stores its JSON data files. This is intentionally
    | in the storage directory to keep it separate from your application code.
    |
    */
    'storage_path' => storage_path('studio'),

    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | Where generated Blade files will be placed. These are the final output
    | that you can use in your Laravel application.
    |
    */
    'output_path' => resource_path('views/designer'),

    /*
    |--------------------------------------------------------------------------
    | Default Layout
    |--------------------------------------------------------------------------
    |
    | The Blade component that exported pages are wrapped in. When null
    | (the default), exports are fully standalone HTML documents that work
    | in any application with zero setup. Set this to a component name to
    | wrap exports instead — e.g. 'layout' becomes <x-layout>, and dot
    | notation works too: 'layouts.app' becomes <x-layouts.app>.
    |
    */
    'default_layout' => null,

    /*
    |--------------------------------------------------------------------------
    | Auto-Generate on Save
    |--------------------------------------------------------------------------
    |
    | Automatically regenerate Blade files when pages are saved.
    |
    */
    'auto_generate' => true,

    /*
    |--------------------------------------------------------------------------
    | Page Auto-Routing
    |--------------------------------------------------------------------------
    |
    | When enabled, every Studio page automatically becomes a live public
    | route in your app — no generation step needed. When the package is
    | removed, all routes disappear and your app is unaffected.
    |
    | - enabled: toggle auto-routing on/off
    | - middleware: middleware for public page routes (separate from editor)
    | - home_slug: which page slug maps to "/" (default: "home")
    |
    */
    'page_routing' => [
        'enabled' => true,
        'middleware' => ['web'],
        'home_slug' => 'home',
        // Serve /sitemap.xml listing all published, indexable pages
        'sitemap' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar Position
    |--------------------------------------------------------------------------
    |
    | Controls which side the component editor sidebar appears on.
    | Supported values: "left", "right"
    |
    */
    'sidebar_position' => 'left',

    /*
    |--------------------------------------------------------------------------
    | Iframe Preview Settings
    |--------------------------------------------------------------------------
    |
    | Customize the iframe preview layout used in the page editor. You can
    | toggle default CDN includes, add custom stylesheets/scripts, set
    | body classes, or inject raw HTML into the <head>.
    |
    | For full control, publish the layout:
    |   php artisan vendor:publish --tag=studio-iframe-layout
    |
    */
    'iframe' => [
        'tailwind_cdn' => true,
        'alpine_cdn' => true,
        'alpine_plugins' => [
            'mask' => true,
            'intersect' => true,
            'resize' => true,
            'persist' => true,
            'focus' => true,
            'collapse' => true,
            'anchor' => true,
            'morph' => true,
            'sort' => true,
        ],
        'extra_styles' => [],
        'extra_scripts' => [],
        'body_class' => 'min-h-screen w-full',
        'head_html' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Designer.dev API (Pro Feature)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => false,
        'base_url' => 'https://api.designer.dev',
        'key' => env('DESIGNER_API_KEY'),
    ],
];
