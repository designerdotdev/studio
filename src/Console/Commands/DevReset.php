<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\BladeGenerator;
use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Console\Command;

class DevReset extends Command
{
    protected $signature = 'studio:dev-reset
                            {--seed : Also seed the starter template so you skip onboarding}';

    protected $description = 'Reset studio to a fresh install state (dev only)';

    public function handle(
        StudioStorage $storage,
        BladeGenerator $generator,
        DesignSyncService $designSync
    ): int {
        $this->info('Resetting Designer Studio to fresh install state...');
        $this->line('');

        // 1. Remove generated Blade files
        $this->line('  Removing generated Blade files...');
        $generator->purge();
        $this->info('  Done.');

        // 2. Purge all JSON data (pages + components)
        $this->line('  Purging all stored data...');
        $storage->purge();
        $this->info('  Done.');

        // 3. Re-create storage directories
        $this->line('  Re-creating storage directories...');
        $storage->ensureDirectoryExists();
        $storage->ensureDirectoryExists('pages');
        $storage->ensureDirectoryExists('components/library');
        $this->info('  Done.');

        // 4. Sync component designs from source YAML/HTML files
        $this->line('  Syncing component designs...');
        $result = $designSync->syncAll();
        $this->info("  Done. ({$result['created']} created, {$result['updated']} updated)");

        // 5. Optionally seed starter data (skips onboarding)
        if ($this->option('seed')) {
            $this->line('  Seeding starter template...');
            $this->call('studio:seed');
        }

        $this->line('');
        $this->info('Studio has been reset to a fresh state.');

        if (!$this->option('seed')) {
            $this->line('Visit /studio to see the onboarding flow.');
            $this->line('Use --seed to pre-populate with the starter template.');
        }

        return 0;
    }
}
