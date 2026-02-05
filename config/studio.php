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
    | Models
    |--------------------------------------------------------------------------
    |
    | These are the Eloquent models used by Studio. You can replace them
    | with your own models as long as they have the same relationships.
    |
    */
    'models' => [
        'project' => 'App\\Models\\Project',
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates Path
    |--------------------------------------------------------------------------
    |
    | This is the path where template YAML files are stored. These files
    | define the editable fields for your templates.
    |
    */
    'templates_path' => resource_path('views/templates'),
];
