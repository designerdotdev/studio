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
    | Dev mode adds Code mode and an "Edit code" button to the editor: the
    | site's source files (resources/designer — sections, layouts, data)
    | open in a code editor and save straight back. Because it edits
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
    | Where the editor keeps its working data: the draft, the section
    | library, and downloaded templates. The site itself is not here — it is
    | installed into resources/designer and public/designer and served by
    | app/Providers/DesignerServiceProvider.php, so it keeps working if
    | Studio is removed. Deleting this folder loses only unpublished drafts.
    |
    */
    'storage_path' => storage_path('studio'),

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
    | Whole starter sites, each living in its own git repository in the
    | DevDojo site-templates format. Picking one (in onboarding, or with
    | `php artisan studio:templates:import pilot`) copies the files it uses
    | into your app — its `files/resources` tree into resources/designer and
    | its `files/public` tree into public/designer — and nothing else.
    |
    | `catalog` is the authority on which templates are offered, in picker
    | order: slug => repository URL, or an array with a `repo` plus the
    | `name`, `description` and `category` (landing | business) the picker
    | shows before the template is downloaded. Repositories are cloned on
    | first use into `path`, a cache inside Studio's storage
    | (`php artisan studio:templates:sync` refreshes it).
    |
    */
    'templates' => [
        'path' => storage_path('studio/templates'),

        'catalog' => [
            // Landing pages — products, SaaS, and the studios that build them
            'pilot' => [
                'repo' => 'https://github.com/site-templates/pilot',
                'name' => 'Pilot',
                'category' => 'landing',
                'description' => 'An off-white, monochrome site for an AI agent framework — dark dropdown menus, a three-pane agent console standing on a painted landscape, and a guides library.',
            ],
            'amber' => [
                'repo' => 'https://github.com/site-templates/amber',
                'name' => 'Amber',
                'category' => 'landing',
                'description' => 'A warm, serif-led studio landing page — an art-directed hero, numbered services, featured work, retainer pricing, an accordion FAQ, and a news section with full posts.',
            ],
            'draft' => [
                'repo' => 'https://github.com/site-templates/draft',
                'name' => 'Draft',
                'category' => 'landing',
                'description' => 'A dark, Linear-grade SaaS site — glass nav with a mega-dropdown, a CSS-built product mock, bento features, an Updates journal, a changelog, and a mini docs hub.',
            ],
            'signal' => [
                'repo' => 'https://github.com/site-templates/signal',
                'name' => 'Signal',
                'category' => 'landing',
                'description' => 'A warm, canvas-styled SaaS marketing site — floating pill nav, a giant cursor-bar headline, a CSS-built product window, bento features, pricing, a changelog, and a blog.',
            ],
            'reply' => [
                'repo' => 'https://github.com/site-templates/reply',
                'name' => 'Reply',
                'category' => 'landing',
                'description' => 'A cream-canvas SaaS site for a help desk with an AI teammate — an inbox floating on painterly wildflower fields, a live resolution ticker, a pricing toggle, and customer stories.',
            ],
            'lumen' => [
                'repo' => 'https://github.com/site-templates/lumen',
                'name' => 'Lumen',
                'category' => 'landing',
                'description' => 'A polished, deep-green landing page for studios and consultancies — editorial serif headlines, numbered services, big stats, a case-study spotlight, and a journal.',
            ],

            // Businesses — studios, hospitality, and property
            'monarch' => [
                'repo' => 'https://github.com/site-templates/monarch',
                'name' => 'Monarch',
                'category' => 'business',
                'description' => 'A bone-and-black product studio site built around an oversized menu capsule — split hero, services bento, a dark testimonial, a studio journal, and a dated changelog, lit by an electric lime accent.',
            ],
            'stone' => [
                'repo' => 'https://github.com/site-templates/stone',
                'name' => 'Stone',
                'category' => 'business',
                'description' => 'A calm, confident studio site on warm paper — serif display headlines, a numbered fullscreen menu, an ink-dark pricing tier, and a remembered light/dark mode.',
            ],
            'strata' => [
                'repo' => 'https://github.com/site-templates/strata',
                'name' => 'Strata',
                'category' => 'business',
                'description' => 'An elegant luxury real-estate site — a full-bleed dusk hero, serif-display property pages with stat cards, a curated portfolio grid, and warm editorial storytelling.',
            ],
            'crema' => [
                'repo' => 'https://github.com/site-templates/crema',
                'name' => 'Crema',
                'category' => 'business',
                'description' => 'A specialty coffee roaster and neighbourhood bar, built around a hand-set counter board, a priced menu, and structured single-origin pages.',
            ],
            'norden' => [
                'repo' => 'https://github.com/site-templates/norden',
                'name' => 'Norden',
                'category' => 'business',
                'description' => 'A calm, photographic coffee house — a split hero under a rotating seal, a full drinks menu with a page for every cup, a drawn neighbourhood map, and a journal.',
            ],
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
