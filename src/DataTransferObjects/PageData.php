<?php

namespace Designer\Studio\DataTransferObjects;

class PageData
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $layout,
        public readonly ?string $layout_ref,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly array $meta,
        public readonly array $components,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            slug: $data['slug'],
            title: $data['title'] ?? 'Untitled',
            description: $data['description'] ?? '',
            layout: $data['layout'] ?? null,
            layout_ref: $data['layout_ref'] ?? null,
            created_at: $data['created_at'] ?? now()->toIso8601String(),
            updated_at: $data['updated_at'] ?? now()->toIso8601String(),
            meta: $data['meta'] ?? [],
            components: $data['components'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'layout' => $this->layout,
            'layout_ref' => $this->layout_ref,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'meta' => $this->meta,
            'components' => $this->components,
        ];
    }
}
