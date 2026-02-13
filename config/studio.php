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
    | These middleware will be assigned to every Studio route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */
    'middleware' => ['web'],

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
    | The default Blade layout that generated pages will extend.
    |
    */
    'default_layout' => 'layouts.app',

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
