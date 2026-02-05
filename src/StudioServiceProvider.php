<?php

namespace Designer\Studio;

use Designer\Studio\Livewire\ComponentEditor;
use Designer\Studio\Livewire\TemplateEditor;
use Designer\Studio\View\Components\Layouts\App;
use Designer\Studio\View\Components\Layouts\Iframe;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class StudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/studio.php', 'studio');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'studio');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register Blade components
        Blade::component('studio::layouts.app', App::class);
        Blade::component('studio::layouts.iframe', Iframe::class);

        // Register Livewire components
        Livewire::component('studio::component-editor', ComponentEditor::class);
        Livewire::component('studio::template-editor', TemplateEditor::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/studio.php' => config_path('studio.php'),
            ], 'studio-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/studio'),
            ], 'studio-views');

            $this->publishes([
                __DIR__ . '/../resources/js' => resource_path('js/vendor/studio'),
            ], 'studio-assets');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'studio-migrations');
        }
    }
}
