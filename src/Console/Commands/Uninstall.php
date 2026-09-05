<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\Storage\StudioStorage;
use Designer\Studio\Services\BladeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Uninstall extends Command
{
    protected $signature = 'studio:uninstall
                            {--force : Skip confirmation prompts}
                            {--keep-generated : Keep generated Blade files}
                            {--keep-data : Keep JSON data files}';

    protected $description = 'Cleanly remove Designer Studio from your application';

    public function handle(StudioStorage $storage, BladeGenerator $generator): int
    {
        $this->info('Designer Studio Uninstall');
        $this->line('');

        if (!$this->option('force')) {
            if (!$this->confirm('This will remove Designer Studio data. Continue?')) {
                $this->line('Uninstall cancelled.');

                return 0;
            }
        }

        // 1. Remove generated Blade files
        if (!$this->option('keep-generated')) {
            $this->line('Removing generated Blade files...');
            $generator->purge();
            $this->info('  Done.');
        } else {
            $this->line('Keeping generated Blade files.');
        }

        // 2. Remove JSON data
        if (!$this->option('keep-data')) {
            $this->line('Removing JSON data files...');
            $storage->purge();
            $this->info('  Done.');

            // Template imports also write outside the storage tree
            $this->line('Removing imported template files...');
            app(\Designer\Studio\Services\Templates\TemplateImporter::class)->purgeInstalled();
            $this->info('  Done.');
        } else {
            $this->line('Keeping JSON data files.');
        }

        // 3. Drop legacy database tables if they exist
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
        $this->line('1. Remove from composer.json: composer remove designer/studio');
        $this->line('2. Remove published config: rm config/studio.php');
        $this->line('3. Remove published views (if any): rm -rf resources/views/vendor/studio');

        return 0;
    }
}
