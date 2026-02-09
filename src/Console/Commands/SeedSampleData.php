<?php

namespace Designer\Studio\Console\Commands;

use Designer\Studio\Services\SampleDataSeeder;
use Illuminate\Console\Command;

class SeedSampleData extends Command
{
    protected $signature = 'studio:seed';

    protected $description = 'Seed sample components and a demo page';

    public function handle(SampleDataSeeder $seeder): int
    {
        $this->info('Seeding Designer Studio sample data...');

        $seeder->seed();

        $this->info('Done!');

        return 0;
    }
}
