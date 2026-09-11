<?php

namespace Designer\Studio\Services\Site;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * Installs the provider that serves the site — `app/Providers/
 * DesignerServiceProvider.php` — and registers it with the application.
 *
 * The provider is written into the app (not loaded from the package) so the
 * site keeps working after Studio is removed. Once written it belongs to the
 * app: an existing file is never overwritten, only registered if it is not.
 */
class RuntimeInstaller
{
    public function namespace(): string
    {
        return rtrim(app()->getNamespace(), '\\') . '\\Providers';
    }

    public function providerClass(): string
    {
        return $this->namespace() . '\\DesignerServiceProvider';
    }

    public function path(): string
    {
        return app_path('Providers/DesignerServiceProvider.php');
    }

    public function installed(): bool
    {
        return is_file($this->path()) && $this->registered();
    }

    /**
     * Write the provider if it is missing and make sure the app loads it.
     *
     * @return array{written: bool, registered: bool}
     */
    public function install(): array
    {
        $written = false;

        if (!is_file($this->path())) {
            $stub = (string) file_get_contents(dirname(__DIR__, 3) . '/stubs/DesignerServiceProvider.php.stub');

            File::ensureDirectoryExists(dirname($this->path()));
            File::put($this->path(), str_replace('{{ namespace }}', $this->namespace(), $stub));
            $written = true;
        }

        return ['written' => $written, 'registered' => $this->register()];
    }

    /** Whether the app's provider list names the runtime provider. */
    public function registered(): bool
    {
        $class = $this->providerClass();

        foreach ([$this->bootstrapFile(), config_path('app.php')] as $file) {
            if (is_file($file) && str_contains((string) file_get_contents($file), $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add the provider to bootstrap/providers.php (Laravel 11+), falling back
     * to the `providers` array of config/app.php on an older app skeleton.
     */
    protected function register(): bool
    {
        if ($this->registered()) {
            return true;
        }

        if (is_file($this->bootstrapFile())) {
            ServiceProvider::addProviderToBootstrapFile($this->providerClass(), $this->bootstrapFile());

            return $this->registered();
        }

        $config = config_path('app.php');

        if (is_file($config) && is_writable($config)) {
            $contents = (string) file_get_contents($config);
            $line = '        ' . $this->providerClass() . '::class,';
            $updated = preg_replace(
                '/(\n(\s*)' . preg_quote($this->namespace(), '/') . '\\\\AppServiceProvider::class,)/',
                "$1\n" . $line,
                $contents,
                1,
                $count
            );

            if ($count === 1 && $updated !== null) {
                File::put($config, $updated);
            }
        }

        return $this->registered();
    }

    protected function bootstrapFile(): string
    {
        return app()->getBootstrapProvidersPath();
    }
}
