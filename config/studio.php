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
    'path' => 'designer/studio',

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
    'storage_path' => storage_path('designer-studio'),

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
    | Designer.dev API (Pro Feature)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => false,
        'base_url' => 'https://api.designer.dev',
        'key' => env('DESIGNER_API_KEY'),
    ],
];
