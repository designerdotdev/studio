<?php

namespace Designer\Studio;

use Designer\Studio\Console\Commands\DevReset;
use Designer\Studio\Console\Commands\PublishAssets;
use Designer\Studio\Console\Commands\SyncDesigns;
use Designer\Studio\Console\Commands\TemplatesImport;
use Designer\Studio\Console\Commands\TemplatesSync;
use Designer\Studio\Console\Commands\Uninstall;
use Designer\Studio\Livewire\EditorPanel;
use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Designer\Studio\Support\StudioAssets;
use Designer\Studio\View\Components\Layouts\App;
use Designer\Studio\View\Components\Layouts\Iframe;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * The editor. The site itself is served by the runtime provider installed
 * into the app (app/Providers/DesignerServiceProvider.php), which is why
 * nothing here registers a public route: remove this package and the site
 * keeps working.
 */
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
        $this->app->singleton(\Designer\Studio\Services\PublishService::class);
        $this->app->singleton(\Designer\Studio\Services\Storage\SiteRepository::class);
        $this->app->singleton(\Designer\Studio\Services\SectionRenderer::class);
        $this->app->singleton(\Designer\Studio\Services\RenderContext::class);
        $this->app->singleton(\Designer\Studio\Services\Storage\CollectionRepository::class);
        $this->app->singleton(\Designer\Studio\Services\CollectionBinder::class);
        $this->app->singleton(\Designer\Studio\Services\MediaLibrary::class);
        $this->app->singleton(\Designer\Studio\Support\SiteChrome::class);
        $this->app->singleton(\Designer\Studio\Services\Templates\TemplateSync::class);
        $this->app->singleton(\Designer\Studio\Services\Templates\TemplateCatalog::class);
        $this->app->singleton(\Designer\Studio\Services\Templates\TemplateChrome::class);
        $this->app->singleton(\Designer\Studio\Services\Site\SiteReader::class);
        $this->app->singleton(\Designer\Studio\Services\Site\SiteWriter::class);
        $this->app->singleton(SiteMirror::class);
        $this->app->singleton(\Designer\Studio\Services\Site\SiteInstaller::class);
        $this->app->singleton(\Designer\Studio\Services\Site\RuntimeInstaller::class);
        $this->app->singleton(\Designer\Studio\Support\WelcomeRoutePruner::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'studio');

        // Sections compose the site's other components (<x-nav>, an icon…).
        // The runtime provider registers the same path for the live site;
        // Studio needs it for the canvas even before that provider exists.
        if (is_dir(SitePaths::components())) {
            Blade::anonymousComponentPath(SitePaths::components());
        }

        // NOTE: No migrations - we use JSON file storage!

        // Register Blade components
        Blade::component('studio::layouts.app', App::class);
        Blade::component('studio::layouts.iframe', Iframe::class);

        // Register Livewire components
        Livewire::component('studio::editor-panel', EditorPanel::class);
        Livewire::component('studio::pages-panel', \Designer\Studio\Livewire\PagesPanel::class);
        Livewire::component('studio::media-panel', \Designer\Studio\Livewire\MediaPanel::class);
        Livewire::component('studio::content-panel', \Designer\Studio\Livewire\ContentPanel::class);
        Livewire::component('studio::assistant-panel', \Designer\Studio\Livewire\AssistantPanel::class);

        // Register Blade directives for self-contained assets
        $this->registerAssetDirectives();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DevReset::class,
                PublishAssets::class,
                SyncDesigns::class,
                TemplatesImport::class,
                TemplatesSync::class,
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

            $publishableAssets = [];
            foreach (StudioAssets::FILES as $file) {
                $publishableAssets[__DIR__ . '/../dist/' . $file] = public_path(StudioAssets::PUBLISH_PATH . '/' . $file);
            }
            $this->publishes($publishableAssets, 'studio-assets');
        }

        // Initialize storage directories on first request
        $this->app->booted(function () {
            if (!$this->app->runningInConsole()) {
                $storage = $this->app->make(StudioStorage::class);
                $storage->ensureDirectoryExists();
                $storage->ensureDirectoryExists('components/library');

                if (config('studio.draft_mode', true)) {
                    $this->app->make(\Designer\Studio\Services\PublishService::class)->ensureDraftSeeded();
                }
            }
        });

        // With draft mode off, edits go straight to the live documents —
        // which mirror the site's files, so write them back once the
        // request is done.
        $this->app->terminating(function () {
            if ($this->app->resolved(StudioStorage::class) && $this->app->make(StudioStorage::class)->consumeLiveChanges()) {
                $this->app->make(SiteMirror::class)->flush();
            }
        });
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
}
