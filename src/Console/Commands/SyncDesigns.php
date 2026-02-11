<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\DesignSyncService;
use Illuminate\Console\Command;

class SyncDesigns extends Command
{
    protected $signature = 'studio:sync';

    protected $description = 'Sync component designs from resource files to storage';

    public function handle(DesignSyncService $designSync): int
    {
        $this->info('Syncing component designs...');

        $result = $designSync->syncAll();

        $this->info("Synced {$result['total']} components: {$result['created']} created, {$result['updated']} updated");

        return 0;
    }
}
