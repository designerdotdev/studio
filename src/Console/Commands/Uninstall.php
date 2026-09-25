<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Support\SitePaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Uninstall extends Command
{
    protected $signature = 'studio:uninstall
                            {--force : Skip confirmation prompts}
                            {--keep-data : Keep the editor\'s data (drafts, the section library, template downloads)}';

    protected $description = 'Remove Designer Studio from your application — your site keeps working';

    public function handle(StudioStorage $storage): int
    {
        $this->info('Designer Studio Uninstall');
        $this->line('');

        if (SitePaths::installed()) {
            $this->line('Your site stays: ' . SitePaths::relative(SitePaths::resources()) . ' and ' . SitePaths::relative(SitePaths::public()) . ',');
            $this->line('served by app/Providers/DesignerServiceProvider.php, which does not need Studio.');
            $this->comment('Anything still in the draft (unpublished) is not part of the site and will be lost.');
            $this->line('');
        }

        if (!$this->option('force') && !$this->confirm('Remove Designer Studio\'s editor data?', true)) {
            $this->line('Uninstall cancelled.');

            return self::SUCCESS;
        }

        if (!$this->option('keep-data')) {
            $this->line('Removing editor data (' . SitePaths::relative($storage->getBasePath()) . ')...');
            $storage->purge();
            $this->info('  Done.');
        } else {
            $this->line('Keeping editor data.');
        }

        // Drop legacy database tables if they exist
        if (Schema::hasTable('template_settings')) {
            if ($this->option('force') || $this->confirm('Drop legacy template_settings table?', true)) {
                Schema::dropIfExists('template_settings');
                $this->info('  Dropped template_settings table.');
            }
        }

        $this->line('');
        $this->info('Designer Studio has been uninstalled.');
        $this->line('');
        $this->comment('To complete removal:');
        $this->line('1. Remove the package: composer remove designer/studio');
        $this->line('2. Remove published config (if any): rm config/studio.php');
        $this->line('3. Remove published views and assets (if any): rm -rf resources/views/vendor/studio public/vendor/studio');

        return self::SUCCESS;
    }
}
