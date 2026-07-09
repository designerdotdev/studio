<?php

namespace Designer\Studio\Services;

use Designer\Studio\Services\Storage\ComponentRepository;
use Symfony\Component\Yaml\Yaml;

class DesignSyncService
{
    public function __construct(
        protected ComponentRepository $components
    ) {}

    public function getDesignsPath(): string
    {
        $appPath = resource_path('views/designer');

        if (is_dir($appPath)) {
            return $appPath;
        }

        return dirname(__DIR__, 2) . '/resources/views/designer';
    }

    public function discoverDesigns(): array
    {
        $basePath = $this->getDesignsPath();
        $designs = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'yml') {
                $relativePath = str_replace($basePath . '/', '', $file->getPathname());
                $designs[] = preg_replace('/\.yml$/', '', $relativePath);
            }
        }

        sort($designs);

        return $designs;
    }

    /**
     * Relative design path (e.g. "heroes/basic-hero-04") for a component
     * name. Filenames match the yml `name` key per the authoring contract.
     */
    public function findDesignPath(string $name): ?string
    {
        foreach ($this->discoverDesigns() as $relative) {
            if (basename($relative) === $name) {
                return $relative;
            }
        }

        return null;
    }

    public function loadDesign(string $relativePath): array
    {
        $basePath = $this->getDesignsPath() . '/' . $relativePath;

        $data = Yaml::parseFile($basePath . '.yml');
        $data['html'] = file_get_contents($basePath . '.html');

        return $data;
    }

    /** Image files that may sit beside section designs */
    protected const ASSET_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'avif'];

    public function syncAll(): array
    {
        $this->publishAssets();

        $designs = $this->discoverDesigns();
        $created = 0;
        $updated = 0;

        foreach ($designs as $design) {
            $data = $this->loadDesign($design);
            $name = $data['name'] ?? null;

            if (!$name) {
                continue;
            }

            $existing = $this->components->find($name);

            if ($existing) {
                // Only write when the design file actually changed
                if ($this->isDirty($existing, $data)) {
                    $this->components->update($name, $data);
                    $updated++;
                }
            } else {
                $this->components->create($data);
                $created++;
            }
        }

        return [
            'total' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Publish image files that live beside section designs into
     * public/studio-uploads/designer/ so field defaults can reference
     * them as /studio-uploads/designer/<filename> in every render path.
     */
    public function publishAssets(): void
    {
        $basePath = $this->getDesignsPath();

        if (!is_dir($basePath)) {
            return;
        }

        $target = public_path('studio-uploads/designer');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!in_array(strtolower($file->getExtension()), self::ASSET_EXTENSIONS, true)) {
                continue;
            }

            $destination = $target . '/' . $file->getFilename();

            // Copy once; re-copy only when the source file changed size
            if (file_exists($destination) && filesize($destination) === $file->getSize()) {
                continue;
            }

            if (!is_dir($target)) {
                @mkdir($target, 0755, true);
            }

            @copy($file->getPathname(), $destination);
        }
    }

    protected function isDirty(\Designer\Studio\DataTransferObjects\ComponentData $existing, array $data): bool
    {
        return $existing->html !== ($data['html'] ?? '')
            || $existing->title !== ($data['title'] ?? 'Untitled Component')
            || $existing->description !== ($data['description'] ?? '')
            || $existing->category !== ($data['category'] ?? 'general')
            || $existing->fields != ($data['fields'] ?? [])
            || $existing->preview_variables != ($data['preview_variables'] ?? [])
            || $existing->tags != ($data['tags'] ?? []);
    }
}
