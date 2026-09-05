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
    | Dev Mode
    |--------------------------------------------------------------------------
    |
    | Dev mode adds an "Edit code" button to the editor that opens the
    | selected section's .html and .yml source files in a code editor and
    | writes changes back to resources/views/designer. Because it edits
    | application source files from the browser, it defaults to being
    | available only in the local environment. Set true/false to force it
    | on or off regardless of environment.
    |
    */
    'dev_mode' => env('STUDIO_DEV_MODE'),

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
    | Site templates
    |--------------------------------------------------------------------------
    |
    | Whole starter sites, each living in its own git repository, so one
    | template can be maintained in one place and installed into any app
    | that runs Studio.
    |
    |   php artisan studio:templates:sync          clone/pull every entry
    |   php artisan studio:templates:import atlas  build a site from one
    |
    | `catalog` is the authority on which templates exist: slug => repo URL.
    | Sync clones each into `path` (which ignores its own contents, so the
    | clones never reach the host app's git history) and prunes folders that
    | have left the catalog. The clones are real git checkouts — edit one in
    | place, then commit and push from inside the folder.
    |
    | Templates are additive: the seven built into the package stay
    | available whether or not anything is ever synced.
    |
    */
    'templates' => [
        'path' => resource_path('studio-templates'),

        // Where an imported template's support components are copied so the
        // host app can render them without the clone being present.
        'components_path' => resource_path('views/components/studio-templates'),

        // Where an imported template's images, fonts, and scripts are
        // published, relative to public/.
        'assets_path' => 'studio-templates',

        // Nothing is fetched until `studio:templates:sync` runs, and
        // `--template=` limits a run to one. Trim this list, point it at
        // your own repositories, or leave it be.
        'catalog' => [
            'amber' => 'https://github.com/site-templates/amber',
            'aria' => 'https://github.com/site-templates/aria',
            'atlas' => 'https://github.com/site-templates/atlas',
            'base' => 'https://github.com/site-templates/base',
            'binary' => 'https://github.com/site-templates/binary',
            'blank' => 'https://github.com/site-templates/blank',
            'blog' => 'https://github.com/site-templates/blog',
            'box' => 'https://github.com/site-templates/box',
            'chrome' => 'https://github.com/site-templates/chrome',
            'chronicle' => 'https://github.com/site-templates/chronicle',
            'commodore' => 'https://github.com/site-templates/commodore',
            'crema' => 'https://github.com/site-templates/crema',
            'draft' => 'https://github.com/site-templates/draft',
            'folio' => 'https://github.com/site-templates/folio',
            'forge' => 'https://github.com/site-templates/forge',
            'halo' => 'https://github.com/site-templates/halo',
            'harlow' => 'https://github.com/site-templates/harlow',
            'index' => 'https://github.com/site-templates/index',
            'juno' => 'https://github.com/site-templates/juno',
            'kernel' => 'https://github.com/site-templates/kernel',
            'lumen' => 'https://github.com/site-templates/lumen',
            'monarch' => 'https://github.com/site-templates/monarch',
            'newspaper' => 'https://github.com/site-templates/newspaper',
            'node' => 'https://github.com/site-templates/node',
            'norden' => 'https://github.com/site-templates/norden',
            'onyx' => 'https://github.com/site-templates/onyx',
            'origin' => 'https://github.com/site-templates/origin',
            'pilot' => 'https://github.com/site-templates/pilot',
            'render' => 'https://github.com/site-templates/render',
            'reply' => 'https://github.com/site-templates/reply',
            'sand' => 'https://github.com/site-templates/sand',
            'signal' => 'https://github.com/site-templates/signal',
            'silver' => 'https://github.com/site-templates/silver',
            'slate' => 'https://github.com/site-templates/slate',
            'stack' => 'https://github.com/site-templates/stack',
            'stone' => 'https://github.com/site-templates/stone',
            'strata' => 'https://github.com/site-templates/strata',
            'vale' => 'https://github.com/site-templates/vale',
            'volt' => 'https://github.com/site-templates/volt',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Assistant
    |--------------------------------------------------------------------------
    |
    | The Assistant panel drives an AI CLI installed on the developer's own
    | machine — Claude Code (`claude`) or Codex (`codex`) — with the CLI's
    | own file tools over this application directory. It is available only
    | when dev mode is on (see `dev_mode`) and a binary can be found.
    | Pin a binary path per engine when it is not on PATH, and optionally a
    | model name to pass through.
    |
    */
    'assistant' => [
        'default' => 'claude',
        'engines' => [
            'claude' => [
                'bin' => env('STUDIO_CLAUDE_BIN'),
                'model' => env('STUDIO_CLAUDE_MODEL'),
            ],
            'codex' => [
                'bin' => env('STUDIO_CODEX_BIN'),
                'model' => env('STUDIO_CODEX_MODEL'),
            ],
        ],
        // Seconds a single turn may run before it is stopped
        'timeout' => 900,
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
