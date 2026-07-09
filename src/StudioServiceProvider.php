<?php

namespace Designer\Studio;

use Designer\Studio\Console\Commands\DevReset;
use Designer\Studio\Console\Commands\PublishAssets;
use Designer\Studio\Console\Commands\SeedSampleData;
use Designer\Studio\Console\Commands\SyncDesigns;
use Designer\Studio\Console\Commands\Uninstall;
use Designer\Studio\Livewire\EditorPanel;
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
        $this->app->singleton(\Designer\Studio\Services\Storage\LayoutRepository::class);
        $this->app->singleton(\Designer\Studio\Services\Storage\BlockRepository::class);
        $this->app->singleton(ComponentRepository::class);
        $this->app->singleton(BladeGenerator::class);
        $this->app->singleton(\Designer\Studio\Services\PublishService::class);
        $this->app->singleton(\Designer\Studio\Services\TemplateRegistry::class);
        $this->app->singleton(\Designer\Studio\Support\WelcomeRoutePruner::class);

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

        // Register the page catch-all in a booted callback so it lands AFTER
        // every app route (package boot() runs before app routes load — the
        // app's own routes must always win over Studio pages). When routes
        // are cached the cached copy already contains these static routes,
        // so re-registering is skipped — pages are resolved from storage at
        // request time, making the whole thing route:cache-safe.
        $this->app->booted(function () {
            if (!$this->app->routesAreCached()) {
                $this->registerPageRoutes();
            }
        });

        // NOTE: No migrations - we use JSON file storage!

        // Register Blade components
        Blade::component('studio::layouts.app', App::class);
        Blade::component('studio::layouts.iframe', Iframe::class);

        // Register Livewire components
        Livewire::component('studio::editor-panel', EditorPanel::class);

        // Register Blade directives for self-contained assets
        $this->registerAssetDirectives();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DevReset::class,
                PublishAssets::class,
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

            $this->publishes([
                __DIR__ . '/../dist/studio.js' => public_path('vendor/studio/studio.js'),
                __DIR__ . '/../dist/studio-css.css' => public_path('vendor/studio/studio-css.css'),
            ], 'studio-assets');
        }

        // Auto-publish design files on first boot if not already present
        $this->publishDesignsOnInstall();

        // Initialize storage directories on first request
        $this->app->booted(function () {
            if (!$this->app->runningInConsole()) {
                $storage = $this->app->make(StudioStorage::class);
                $storage->ensureDirectoryExists();
                $storage->ensureDirectoryExists('pages');
                $storage->ensureDirectoryExists('layouts');
                $storage->ensureDirectoryExists('blocks');
                $storage->ensureDirectoryExists('components/library');

                if (config('studio.draft_mode', true)) {
                    $this->app->make(\Designer\Studio\Services\PublishService::class)->ensureDraftSeeded();
                }
            }
        });
    }

    /**
     * Copy the packaged design files into resources/views/designer.
     *
     * Existing files are never overwritten (they belong to the app once
     * published), but new sections shipped in package updates are added.
     * Runs only for studio requests to keep application boot free of
     * filesystem scans.
     */
    protected function publishDesignsOnInstall(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $prefix = trim(config('studio.path', 'studio'), '/');
        $request = $this->app['request'] ?? null;

        if (!$request || (!$request->is($prefix) && !$request->is($prefix . '/*'))) {
            return;
        }

        $destination = resource_path('views/designer');
        $source = __DIR__ . '/../resources/views/designer';

        if (!is_dir($source)) {
            return;
        }

        $filesystem = new Filesystem;

        if (!is_dir($destination)) {
            $filesystem->ensureDirectoryExists($destination);
            $filesystem->copyDirectory($source, $destination);

            return;
        }

        // Merge-copy: add files that don't exist locally yet
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($source) + 1);
            $target = $destination . '/' . $relative;

            if (!file_exists($target)) {
                $filesystem->ensureDirectoryExists(dirname($target));
                $filesystem->copy($file->getPathname(), $target);
            }
        }
    }

    protected function registerAssetDirectives(): void
    {
        Blade::directive('studioStyles', function () {
            return '<?php echo \'<link rel="stylesheet" href="\' . \Designer\Studio\Support\StudioAssets::url("studio-css.css") . \'">\'; ?>';
        });

        Blade::directive('studioScripts', function () {
            return '<?php echo \'<script src="\' . \Designer\Studio\Support\StudioAssets::url("studio.js") . \'" defer></script>\'; ?>';
        });

        Blade::directive('studioIframeCore', function () {
            return '<?php echo \'<script src="\' . \Designer\Studio\Support\StudioAssets::url("studio.js") . \'" defer></script>\'; ?>';
        });
    }

    /**
     * Two STATIC routes serve every published page — which pages exist is
     * decided at request time by looking in storage, never at registration
     * time. Route definitions that don't depend on content survive
     * `route:cache` and pick up newly published pages instantly.
     */
    protected function registerPageRoutes(): void
    {
        if (!config('studio.page_routing.enabled', true)) {
            return;
        }

        $middleware = config('studio.page_routing.middleware', ['web']);
        $homeSlug = config('studio.page_routing.home_slug', 'home');

        $pruner = $this->app->make(\Designer\Studio\Support\WelcomeRoutePruner::class);

        Route::middleware($middleware)->group(function () use ($homeSlug, $pruner) {
            // The home page — only when the app hasn't claimed '/' itself.
            // (The stock welcome route is auto-removed by WelcomeRoutePruner
            // when the site is seeded, published, or the editor loads.)
            if (!$pruner->appDefinesRootRoute()) {
                Route::get('/', [\Designer\Studio\Http\Controllers\PageController::class, 'show'])
                    ->defaults('slug', $homeSlug)
                    ->name('studio.page.home');
            }

            if (config('studio.page_routing.sitemap', true)) {
                Route::get('/sitemap.xml', [\Designer\Studio\Http\Controllers\PageController::class, 'sitemap'])
                    ->name('studio.sitemap');
            }

            // Every other page: a single-segment catch-all, registered after
            // all app routes so it can never shadow them. Unknown slugs 404
            // in the controller.
            Route::get('/{slug}', [\Designer\Studio\Http\Controllers\PageController::class, 'show'])
                ->where('slug', '[a-z0-9-]+')
                ->name('studio.page.show');
        });
    }
}
