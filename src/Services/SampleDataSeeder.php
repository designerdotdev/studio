<?php

namespace Designer\Studio\Services;

use Designer\Studio\DataTransferObjects\PageData;
use Designer\Studio\Services\Storage\LayoutRepository;
use Designer\Studio\Services\Storage\PageRepository;
use Illuminate\Support\Str;

class SampleDataSeeder
{
    public function __construct(
        protected DesignSyncService $designSync,
        protected PageRepository $pages,
        protected LayoutRepository $layouts,
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

        // Only pages that don't exist yet will be created
        $pageDefs = array_filter(
            $template['pages'],
            fn ($pageDef) => !$this->pages->find($pageDef['slug'])
        );

        if (empty($pageDefs)) {
            return [];
        }

        // Shared layout (header/footer sections) applied to every page
        $layoutRef = !empty($template['layout'])
            ? $this->createLayout($template['layout'])
            : null;

        $createdPages = [];

        foreach ($pageDefs as $pageDef) {
            $createdPages[] = $this->pages->create([
                'slug' => $pageDef['slug'],
                'title' => $pageDef['title'],
                'description' => $pageDef['description'] ?? '',
                'layout_ref' => $layoutRef,
                'components' => $this->withIds($pageDef['components'] ?? []),
            ]);
        }

        // A freshly seeded site starts published: mirror draft/live so the
        // template is immediately visible on both, whichever tree we wrote.
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\PublishService::class)->syncAfterSeed();
        }

        return $createdPages;
    }

    /**
     * Create the template's layout: header sections, content slot, footer
     * sections. Returns its slug for the pages' layout_ref.
     */
    protected function createLayout(array $def): string
    {
        $layout = $this->layouts->create($def['name'] ?? 'Main');

        $components = [
            ...$this->withIds($def['before'] ?? []),
            [
                'id' => LayoutRepository::CONTENT_ID,
                'component_ref' => LayoutRepository::CONTENT_REF,
                'order' => 0,
                'variables' => [],
            ],
            ...$this->withIds($def['after'] ?? []),
        ];

        foreach ($components as $i => &$comp) {
            $comp['order'] = $i;
        }
        unset($comp);

        $this->layouts->update($layout['slug'], ['components' => $components]);

        return $layout['slug'];
    }

    /** Generate UUIDs for component instances that don't have one */
    protected function withIds(array $components): array
    {
        return array_map(fn ($comp) => array_merge($comp, [
            'id' => $comp['id'] ?? Str::uuid()->toString(),
        ]), $components);
    }
}
