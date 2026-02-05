<?php

namespace Designer\Studio\DataTransferObjects;

class ComponentData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly string $category,
        public readonly array $tags,
        public readonly string $version,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly string $html,
        public readonly array $fields,
        public readonly array $preview_variables,
        public readonly string $source,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            title: $data['title'] ?? 'Untitled',
            description: $data['description'] ?? '',
            category: $data['category'] ?? 'general',
            tags: $data['tags'] ?? [],
            version: $data['version'] ?? '1.0.0',
            created_at: $data['created_at'] ?? now()->toIso8601String(),
            updated_at: $data['updated_at'] ?? now()->toIso8601String(),
            html: $data['html'] ?? '',
            fields: $data['fields'] ?? [],
            preview_variables: $data['preview_variables'] ?? [],
            source: $data['source'] ?? 'local',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'version' => $this->version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'html' => $this->html,
            'fields' => $this->fields,
            'preview_variables' => $this->preview_variables,
            'source' => $this->source,
        ];
    }
}
