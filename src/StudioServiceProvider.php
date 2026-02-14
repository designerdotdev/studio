<?php

namespace Designer\Studio;

use Designer\Studio\Console\Commands\SeedSampleData;
use Designer\Studio\Console\Commands\SyncDesigns;
use Designer\Studio\Console\Commands\Uninstall;
use Designer\Studio\Livewire\ComponentEditor;
use Designer\Studio\Livewire\TemplateEditor;
use Designer\Studio\Services\BladeGenerator;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\View\Components\Layouts\App;
use Designer\Studio\View\Components\Layouts\Iframe;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class StudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/studio.php', 'studio');

        // Register services as singletons
        $this->app->singleton(StudioStorage::class);
        $this->app->singleton(PageRepository::class);
        $this->app->singleton(ComponentRepository::class);
        $this->app->singleton(BladeGenerator::class);
        $this->app->singleton(\Designer\Studio\Services\TemplateRegistry::class);

        // Register asset version for cache busting
        $this->app->singleton('studio.asset.version', function () {
            $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';
            if (file_exists($manifestPath)) {
                return md5_file($manifestPath);
            }
            return 'dev';
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'studio');

        // Register page routes in booted callback so they override app routes
        // (package boot() runs before app routes are loaded, so registering
        // here in boot() would be overwritten by the app's GET / route)
        $this->app->booted(function () {
            $this->registerPageRoutes();
        });

        // NOTE: No migrations - we use JSON file storage!

        // Register Blade components
        Blade::component('studio::layouts.app', App::class);
        Blade::component('studio::layouts.iframe', Iframe::class);

        // Register Livewire components
        Livewire::component('studio::component-editor', ComponentEditor::class);
        Livewire::component('studio::template-editor', TemplateEditor::class);

        // Register Blade directives for self-contained assets
        $this->registerAssetDirectives();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedSampleData::class,
                SyncDesigns::class,
                Uninstall::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/studio.php' => config_path('studio.php'),
            ], 'studio-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/studio'),
            ], 'studio-views');

            $this->publishes([
                __DIR__ . '/../resources/views/components/layouts/iframe.blade.php'
                    => resource_path('views/vendor/studio/components/layouts/iframe.blade.php'),
            ], 'studio-iframe-layout');

            $this->publishes([
                __DIR__ . '/../resources/views/designer' => resource_path('views/designer'),
            ], 'studio-designs');
        }

        // Auto-publish design files on first boot if not already present
        $this->publishDesignsOnInstall();

        // Initialize storage directories on first request
        $this->app->booted(function () {
            if (!$this->app->runningInConsole()) {
                $storage = $this->app->make(StudioStorage::class);
                $storage->ensureDirectoryExists();
                $storage->ensureDirectoryExists('pages');
                $storage->ensureDirectoryExists('components/library');
            }
        });
    }

    protected function publishDesignsOnInstall(): void
    {
        $destination = resource_path('views/designer');

        if (is_dir($destination)) {
            return;
        }

        $source = __DIR__ . '/../resources/views/designer';

        $filesystem = new Filesystem;
        $filesystem->ensureDirectoryExists($destination);
        $filesystem->copyDirectory($source, $destination);
    }

    protected function registerAssetDirectives(): void
    {
        Blade::directive('studioStyles', function () {
            return '<?php
                $__studioVersion = app("studio.asset.version");
                $__studioPrefix = config("studio.path", "studio");
                echo \'<link rel="stylesheet" href="\' . url($__studioPrefix . "/assets/studio-css.css") . \'?v=\' . $__studioVersion . \'">\';
            ?>';
        });

        Blade::directive('studioScripts', function () {
            return '<?php
                $__studioVersion = app("studio.asset.version");
                $__studioPrefix = config("studio.path", "studio");
                echo \'<script src="\' . url($__studioPrefix . "/assets/studio.js") . \'?v=\' . $__studioVersion . \'" defer></script>\';
            ?>';
        });

        Blade::directive('studioIframeCore', function () {
            return '<?php
                $__studioVersion = app("studio.asset.version");
                $__studioPrefix = config("studio.path", "studio");
                echo \'<script src="\' . url($__studioPrefix . "/assets/studio.js") . \'?v=\' . $__studioVersion . \'" defer></script>\';
                echo \'<style>
                    [data-component] { cursor: pointer; position: relative; }
                    [data-component]::before { content: \\\'\\\'; position: absolute; inset: 0; pointer-events: none; z-index: 9999; transition: box-shadow 0.15s ease; }
                    [data-component]:hover::before { box-shadow: inset 0 0 0 2px #3b82f6; }
                    [data-component].selected::before { box-shadow: inset 0 0 0 3px #3b82f6; }
                </style>\';
            ?>';
        });
    }

    protected function registerPageRoutes(): void
    {
        if (!config('studio.page_routing.enabled', true)) {
            return;
        }

        $storagePath = config('studio.storage_path', storage_path('studio'));
        $pagesPath = $storagePath . '/pages';

        if (!is_dir($pagesPath)) {
            return;
        }

        $files = glob($pagesPath . '/*.json');

        if (empty($files)) {
            return;
        }

        $middleware = config('studio.page_routing.middleware', ['web']);
        $homeSlug = config('studio.page_routing.home_slug', 'home');

        Route::middleware($middleware)->group(function () use ($files, $homeSlug) {
            foreach ($files as $file) {
                $slug = basename($file, '.json');
                $uri = ($slug === $homeSlug) ? '/' : $slug;

                Route::get($uri, [\Designer\Studio\Http\Controllers\PageController::class, 'show'])
                    ->defaults('slug', $slug);
            }
        });
    }
}
