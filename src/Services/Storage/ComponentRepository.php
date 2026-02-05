<?php

namespace Designer\Studio\Services\Storage;

use Designer\Studio\DataTransferObjects\ComponentData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ComponentRepository
{
    public function __construct(
        protected StudioStorage $storage
    ) {}

    public function all(): Collection
    {
        $names = $this->storage->list('components/library');

        return collect($names)->map(fn($name) => $this->find($name))->filter();
    }

    public function find(string $name): ?ComponentData
    {
        $data = $this->storage->read("components/library/{$name}.json");

        if (!$data) {
            return null;
        }

        return ComponentData::fromArray($data);
    }

    public function byCategory(string $category): Collection
    {
        return $this->all()->filter(fn(ComponentData $comp) => $comp->category === $category);
    }

    public function create(array $data): ComponentData
    {
        $name = $data['name'] ?? Str::slug($data['title'] ?? 'component');

        $componentData = [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'title' => $data['title'] ?? 'Untitled Component',
            'description' => $data['description'] ?? '',
            'category' => $data['category'] ?? 'general',
            'tags' => $data['tags'] ?? [],
            'version' => '1.0.0',
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'html' => $data['html'] ?? '',
            'fields' => $data['fields'] ?? [],
            'preview_variables' => $data['preview_variables'] ?? [],
            'source' => 'local',
        ];

        $this->storage->write("components/library/{$name}.json", $componentData);

        return ComponentData::fromArray($componentData);
    }

    public function update(string $name, array $data): ?ComponentData
    {
        $existing = $this->storage->read("components/library/{$name}.json");

        if (!$existing) {
            return null;
        }

        $updated = array_merge($existing, $data, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->storage->write("components/library/{$name}.json", $updated);

        return ComponentData::fromArray($updated);
    }

    public function delete(string $name): bool
    {
        return $this->storage->delete("components/library/{$name}.json");
    }

    public function categories(): array
    {
        return $this->all()
            ->pluck('category')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Import component from remote source (Pro feature prep)
     */
    public function importFromRemote(array $data): ComponentData
    {
        $data['source'] = 'remote';
        $data['imported_at'] = now()->toIso8601String();

        return $this->create($data);
    }
}
