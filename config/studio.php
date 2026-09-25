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
    | Edit Badge
    |--------------------------------------------------------------------------
    |
    | A small "designer studio · Edit" badge at the bottom of every page the
    | site serves, linking to that page in the editor. It follows the gate
    | above when one is set. The default (null) shows it only in the local
    | environment; set true/false to force it on or off.
    |
    */
    'badge' => env('STUDIO_BADGE'),

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
    | Template Preview (local development)
    |--------------------------------------------------------------------------
    |
    | A folder of template repositories (each with a template.json). When the
    | app runs locally and this is set, every template can be browsed without
    | installing it: /template lists them and /template/{slug} renders one
    | straight from its working tree — edit a file, refresh. Off everywhere
    | else, and off when unset.
    |
    */

    'template_preview' => [
        'path' => env('STUDIO_TEMPLATE_PREVIEW_PATH'),
        'prefix' => 'template',
    ],

    /*
    |--------------------------------------------------------------------------
    | Site templates
    |--------------------------------------------------------------------------
    |
    | Whole starter sites, each living in its own git repository in the
    | Designer template repository format. Picking one (in onboarding, or with
    | `php artisan studio:templates:import starter`) copies the files it uses
    | into your app — its `files/resources` tree into resources/designer and
    | its `files/public` tree into public/designer — and nothing else.
    |
    | `catalog` is the authority on which templates are offered, in picker
    | order: slug => repository URL, or an array with a `repo` plus the
    | `name`, `description` and `category` (starter | landing | business) the
    | picker shows before the template is downloaded. Repositories are cloned on
    | first use into `path`, a cache inside Studio's storage
    | (`php artisan studio:templates:sync` refreshes it).
    |
    | `active` (here, or in the template's own template.json) is what the picker
    | offers: an entry that says false is held back — still installable by slug
    | with `studio:templates:import`, but not shown. An entry that says nothing
    | is active. Locally, where the template folder is the catalog, the picker
    | keeps the inactive ones behind a "show inactive" toggle instead.
    |
    */
    'templates' => [
        'path' => storage_path('studio/templates'),

        'catalog' => [
            // Starting points — a blank site, and a neutral kit of sections
            'blank' => [
                'repo' => 'https://github.com/designer-templates/blank',
                'name' => 'Blank',
                'category' => 'starter',
                'active' => false,
                'description' => 'An empty site: the token layer, a nav and a footer, a home page with nothing on it, and a 404 — build every section yourself.',
            ],
            'starter' => [
                'repo' => 'https://github.com/designer-templates/starter',
                'name' => 'Starter',
                'category' => 'starter',
                'description' => 'A neutral, light starter site with a library of 20 general-purpose sections — four heroes, three feature layouts, three calls to action, pricing, FAQ, stats, team, testimonials, newsletter, contact, prose and a banner — composed into four pages: Home, Pricing, About and Contact.',
            ],

            // Landing pages
            'aisle' => [
                'repo' => 'https://github.com/designer-templates/aisle',
                'name' => 'Aisle',
                'category' => 'landing',
                'description' => 'A sage-grey landing page for hardware-store inventory: a two-tone headline over three tall tiles where a stock count dips under its reorder point and the supplier card stamps PO sent, then a low-stock bento, a phone cycle count, a green-black results band and a paint-drawdown close.',
            ],
            'ascent' => [
                'repo' => 'https://github.com/designer-templates/ascent',
                'name' => 'Ascent',
                'category' => 'landing',
                'description' => 'App Store analytics landing page for indie iOS developers: a drawn dashboard in a soft silk-ribbon frame whose downloads chart draws and review feed updates, digest, keyword-rank and trial-cohort rows, a plum stats band, a review inbox with replies, and plans with a lifted Indie tier.',
            ],
            'bramble' => [
                'repo' => 'https://github.com/designer-templates/bramble',
                'name' => 'Bramble',
                'category' => 'landing',
                'description' => 'A warm, playful SaaS site for mobile pet groomers — sleepy drawn characters that wake when a booking lands, an app-card bento, a route day that draws itself, a quote wall and plan cards.',
            ],
            'canary' => [
                'repo' => 'https://github.com/designer-templates/canary',
                'name' => 'Canary',
                'category' => 'landing',
                'description' => 'A warm-black preview-environments landing page in Host Grotesk: a coral horizon over three environment windows where Staging and Dev angle away from a glowing Production window that runs its build log and turns Live, a PR-comment workflow split, a seeded-data bento, a canary.yml code window, an editorial pull quote, and a per-preview-hour pricing teaser.',
            ],
            'pacer' => [
                'repo' => 'https://github.com/designer-templates/pacer',
                'name' => 'Pacer',
                'category' => 'landing',
                'description' => 'A honey-yellow landing site for a running-club app: a poster headline with route, pace and RSVP cards fanning out beneath it, drawn run cards, a route map that draws itself, an ink stats band and a sunlit photograph close.',
            ],
            'pinnacle' => [
                'repo' => 'https://github.com/designer-templates/pinnacle',
                'name' => 'Pinnacle',
                'category' => 'landing',
                'description' => 'A blue-hour treasury site for a US contractor-payouts product: a full-bleed alpine lake with a live payout run settling across the states, a graphite bento of USD accounts, ACH, RTP and FedNow rails and 1099 filing, a readable cost-and-speed table, a dot-matrix map of all 50 states and DC, and a quiet onyx-and-cobalt palette in Onest.',
            ],
            'pioneer' => [
                'repo' => 'https://github.com/designer-templates/pioneer',
                'name' => 'Pioneer',
                'category' => 'landing',
                'description' => 'A sand-and-canyon-orange landing site for a travel-and-expense agent: a gouache desert morning in a rounded stage where an itinerary books itself row by row, a step rail, a live-budget bento, a torn hotel folio matched to its card charge, an espresso stats band and a dusk close, set in Figtree and Instrument Sans.',
            ],
            'quill' => [
                'repo' => 'https://github.com/designer-templates/quill',
                'name' => 'Quill',
                'category' => 'landing',
                'description' => 'A warm cream knowledge-base landing site — a hand-drawn rope illustration, a wiki on a laptop with a phone overlapping, a Discussions split on sand dunes, a tilted ink ellipse, a testimonials wall, a template carousel, pricing with a yearly switch, a blog, and sign-in and sign-up pages.',
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
