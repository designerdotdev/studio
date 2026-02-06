<?php

namespace Designer\Studio\Services\Storage;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class StudioStorage
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = config('studio.storage_path', storage_path('studio'));
    }

    public function ensureDirectoryExists(string $subPath = ''): string
    {
        $path = $this->basePath . ($subPath ? '/' . $subPath : '');

        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        return $path;
    }

    public function read(string $path): ?array
    {
        $fullPath = $this->basePath . '/' . $path;

        if (!File::exists($fullPath)) {
            return null;
        }

        $content = File::get($fullPath);

        return json_decode($content, true);
    }

    public function write(string $path, array $data): bool
    {
        $fullPath = $this->basePath . '/' . $path;
        $directory = dirname($fullPath);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        return File::put(
            $fullPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->basePath . '/' . $path;

        if (File::exists($fullPath)) {
            return File::delete($fullPath);
        }

        return true;
    }

    public function exists(string $path): bool
    {
        return File::exists($this->basePath . '/' . $path);
    }

    public function list(string $directory, string $extension = 'json'): array
    {
        $fullPath = $this->basePath . '/' . $directory;

        if (!File::isDirectory($fullPath)) {
            return [];
        }

        return collect(File::files($fullPath))
            ->filter(fn($file) => $file->getExtension() === $extension)
            ->map(fn($file) => $file->getFilenameWithoutExtension())
            ->values()
            ->toArray();
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Remove all studio data (for clean uninstall)
     */
    public function purge(): bool
    {
        if (File::isDirectory($this->basePath)) {
            return File::deleteDirectory($this->basePath);
        }

        return true;
    }
}
