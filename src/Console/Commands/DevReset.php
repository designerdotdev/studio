<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Site\RuntimeInstaller;
use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DevReset extends Command
{
    protected $signature = 'studio:dev-reset
                            {--template= : Install this template afterwards, skipping onboarding}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Reset Studio to a fresh install: delete the site, its runtime, and all editor data (dev only)';

    public function handle(StudioStorage $storage, RuntimeInstaller $runtime): int
    {
        if (!$this->option('force') && !$this->confirm('This deletes resources/designer, public/designer, app/Providers/DesignerServiceProvider.php, and the editor data in ' . SitePaths::relative($storage->getBasePath()) . '. Continue?')) {
            return self::SUCCESS;
        }

        $this->info('Resetting Designer Studio to a fresh install...');

        $this->line('  Removing the installed site...');
        File::deleteDirectory(SitePaths::resources());
        File::deleteDirectory(SitePaths::public());

        $this->line('  Removing the site runtime...');
        $this->removeRuntime($runtime);

        // Folders earlier versions of Studio generated in the app (never the
        // old template clones in resources/studio-templates — those are git
        // checkouts that may hold someone's unpushed work)
        foreach ([public_path('studio-templates'), resource_path('views/components/studio-templates')] as $legacy) {
            if (is_dir($legacy)) {
                $this->line('  Removing ' . SitePaths::relative($legacy) . ' (left by an earlier Studio)...');
                File::deleteDirectory($legacy);
            }
        }

        $this->line('  Purging editor data (keeping downloaded templates)...');
        $storage->purge(keep: ['templates']);
        $storage->ensureDirectoryExists();
        $storage->ensureDirectoryExists('components/library');

        $this->info('Done.');

        if ($template = $this->option('template')) {
            return $this->call('studio:templates:import', ['template' => $template]);
        }

        $this->line('Visit /' . trim(config('studio.path', 'studio'), '/') . ' to see the onboarding flow.');

        return self::SUCCESS;
    }

    /** Delete the provider file and take it out of the provider list. */
    protected function removeRuntime(RuntimeInstaller $runtime): void
    {
        File::delete($runtime->path());

        foreach ([base_path('bootstrap/providers.php'), config_path('app.php')] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            $cleaned = preg_replace('/^[ \t]*\\\\?' . preg_quote($runtime->providerClass(), '/') . '::class,?[ \t]*\R/m', '', $contents);

            if ($cleaned !== null && $cleaned !== $contents) {
                File::put($file, $cleaned);
            }
        }
    }
}
