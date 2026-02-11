<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Support\Str;

class SampleDataSeeder
{
    public function __construct(
        protected DesignSyncService $designSync,
        protected PageRepository $pages
    ) {}

    public function seed(): void
    {
        $this->seedComponents();
        $this->seedHomePage();
    }

    protected function seedComponents(): void
    {
        $this->designSync->syncAll();
    }

    protected function seedHomePage(): void
    {
        if (!$this->pages->find('home')) {
            $this->pages->create([
                'slug' => 'home',
                'title' => 'Home Page',
                'description' => 'Sample home page',
                'components' => [
                    [
                        'id' => Str::uuid()->toString(),
                        'component_ref' => 'hero-basic',
                        'order' => 0,
                        'variables' => [
                            'title' => 'Welcome to Designer Studio',
                            'subtitle' => 'Build beautiful pages visually',
                            'cta_text' => 'Get Started',
                            'cta_link' => '#features',
                        ],
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'component_ref' => 'features-grid',
                        'order' => 1,
                        'variables' => [],
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'component_ref' => 'cta-section',
                        'order' => 2,
                        'variables' => [],
                    ],
                ],
            ]);
        }
    }
}
