<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\DesignSyncService;
use Designer\Studio\Services\Site\SiteMirror;
use Designer\Studio\Support\SitePaths;
use Illuminate\Console\Command;

class SyncDesigns extends Command
{
    protected $signature = 'studio:sync';

    protected $description = 'Re-read the installed site (resources/designer) into the editor';

    public function handle(DesignSyncService $designSync, SiteMirror $mirror): int
    {
        if (!SitePaths::installed()) {
            $this->warn('No site is installed yet — run php artisan studio:templates:import <template>.');

            return self::SUCCESS;
        }

        $this->info('Syncing the section library and the site\'s pages...');

        $result = $designSync->syncAll();
        $changed = $mirror->refresh();

        $this->info("Sections: {$result['created']} added, {$result['updated']} updated, {$result['removed']} removed.");
        $this->line($changed ? 'Pages and data re-read from the site\'s files.' : 'Pages and data were already up to date.');

        return self::SUCCESS;
    }
}
