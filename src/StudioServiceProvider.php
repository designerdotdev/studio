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
                $storage->ensureDirectoryExists('components/library');
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
