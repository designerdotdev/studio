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

    public function loadDesign(string $relativePath): array
    {
        $basePath = $this->getDesignsPath() . '/' . $relativePath;

        $data = Yaml::parseFile($basePath . '.yml');
        $data['html'] = file_get_contents($basePath . '.html');

        return $data;
    }

    public function syncAll(): array
    {
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
                $this->components->update($name, $data);
                $updated++;
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
}
