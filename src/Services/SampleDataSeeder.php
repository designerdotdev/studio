<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\ComponentRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Support\Str;

class SampleDataSeeder
{
    public function __construct(
        protected ComponentRepository $components,
        protected PageRepository $pages
    ) {}

    public function seed(): void
    {
        $this->seedComponents();
        $this->seedHomePage();
    }

    protected function seedComponents(): void
    {
        $sampleComponents = [
            [
                'name' => 'hero-basic',
                'title' => 'Basic Hero Section',
                'description' => 'A simple hero with title, subtitle, and optional CTA',
                'category' => 'heroes',
                'tags' => ['hero', 'landing', 'header'],
                'html' => '<section class="bg-gradient-to-r from-blue-600 to-indigo-700 py-20">
    <div class="container mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-white">{{ $title ?? "Welcome" }}</h1>
        <p class="mt-4 text-xl text-blue-100">{{ $subtitle ?? "Your subtitle here" }}</p>
        @if($cta_text ?? false)
            <a href="{{ $cta_link ?? "#" }}" class="mt-8 inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition">{{ $cta_text }}</a>
        @endif
    </div>
</section>',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Welcome', 'required' => true],
                    'subtitle' => ['type' => 'textarea', 'label' => 'Subtitle', 'default' => 'Your subtitle here'],
                    'cta_text' => ['type' => 'text', 'label' => 'Button Text', 'default' => ''],
                    'cta_link' => ['type' => 'text', 'label' => 'Button Link', 'default' => '#'],
                ],
            ],
            [
                'name' => 'features-grid',
                'title' => 'Features Grid',
                'description' => 'A 3-column features section',
                'category' => 'features',
                'tags' => ['features', 'grid', 'benefits'],
                'html' => '<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center text-gray-900">{{ $heading ?? "Features" }}</h2>
        <p class="mt-4 text-center text-gray-600 max-w-2xl mx-auto">{{ $subheading ?? "Everything you need" }}</p>
        <div class="mt-12 grid md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h3 class="mt-4 text-lg font-semibold">{{ $feature1_title ?? "Fast" }}</h3>
                <p class="mt-2 text-gray-600">{{ $feature1_desc ?? "Lightning fast performance" }}</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <h3 class="mt-4 text-lg font-semibold">{{ $feature2_title ?? "Secure" }}</h3>
                <p class="mt-2 text-gray-600">{{ $feature2_desc ?? "Enterprise-grade security" }}</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                </div>
                <h3 class="mt-4 text-lg font-semibold">{{ $feature3_title ?? "Loved" }}</h3>
                <p class="mt-2 text-gray-600">{{ $feature3_desc ?? "Trusted by thousands" }}</p>
            </div>
        </div>
    </div>
</section>',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Features'],
                    'subheading' => ['type' => 'textarea', 'label' => 'Subheading', 'default' => 'Everything you need'],
                    'feature1_title' => ['type' => 'text', 'label' => 'Feature 1 Title', 'default' => 'Fast'],
                    'feature1_desc' => ['type' => 'text', 'label' => 'Feature 1 Description', 'default' => 'Lightning fast performance'],
                    'feature2_title' => ['type' => 'text', 'label' => 'Feature 2 Title', 'default' => 'Secure'],
                    'feature2_desc' => ['type' => 'text', 'label' => 'Feature 2 Description', 'default' => 'Enterprise-grade security'],
                    'feature3_title' => ['type' => 'text', 'label' => 'Feature 3 Title', 'default' => 'Loved'],
                    'feature3_desc' => ['type' => 'text', 'label' => 'Feature 3 Description', 'default' => 'Trusted by thousands'],
                ],
            ],
            [
                'name' => 'cta-section',
                'title' => 'Call to Action',
                'description' => 'A simple CTA section',
                'category' => 'cta',
                'tags' => ['cta', 'action', 'conversion'],
                'html' => '<section class="py-16 bg-gray-900">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-white">{{ $cta_heading ?? "Ready to get started?" }}</h2>
        <p class="mt-4 text-gray-400 max-w-xl mx-auto">{{ $cta_text ?? "Join thousands of satisfied customers today." }}</p>
        <a href="{{ $cta_button_link ?? "#" }}" class="mt-8 inline-block bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">{{ $cta_button_text ?? "Get Started" }}</a>
    </div>
</section>',
                'fields' => [
                    'cta_heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Ready to get started?'],
                    'cta_text' => ['type' => 'textarea', 'label' => 'Description', 'default' => 'Join thousands of satisfied customers today.'],
                    'cta_button_text' => ['type' => 'text', 'label' => 'Button Text', 'default' => 'Get Started'],
                    'cta_button_link' => ['type' => 'text', 'label' => 'Button Link', 'default' => '#'],
                ],
            ],
        ];

        foreach ($sampleComponents as $data) {
            if (!$this->components->find($data['name'])) {
                $this->components->create($data);
            }
        }
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
