<?php

namespace Designer\Studio\Services;

use Designer\Studio\DataTransferObjects\PageData;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Support\Str;

class SampleDataSeeder
{
    public function __construct(
        protected DesignSyncService $designSync,
        protected PageRepository $pages,
        protected TemplateRegistry $templates
    ) {}

    /**
     * Seed using the starter template (backward compatibility for studio:seed command).
     */
    public function seed(): void
    {
        $this->seedFromTemplate('starter');
    }

    /**
     * Sync designs and create all pages defined by the given template.
     *
     * @return PageData[]
     */
    public function seedFromTemplate(string $templateName): array
    {
        $template = $this->templates->find($templateName);

        if (!$template) {
            return [];
        }

        // Always sync designs so component refs resolve
        $this->designSync->syncAll();

        $createdPages = [];

        foreach ($template['pages'] as $pageDef) {
            // Skip if page already exists
            if ($this->pages->find($pageDef['slug'])) {
                continue;
            }

            // Generate UUIDs for components that don't have one
            $components = array_map(function ($comp) {
                return array_merge($comp, [
                    'id' => $comp['id'] ?? Str::uuid()->toString(),
                ]);
            }, $pageDef['components'] ?? []);

            $createdPages[] = $this->pages->create([
                'slug' => $pageDef['slug'],
                'title' => $pageDef['title'],
                'description' => $pageDef['description'] ?? '',
                'components' => $components,
            ]);
        }

        return $createdPages;
    }
}
