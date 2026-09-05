<?php

namespace Designer\Studio\Services\Storage;

use Illuminate\Support\Str;

/**
 * Collections: named, schema'd lists of rows that sections can bind a
 * repeater to (guides, testimonials, team members…). Stored one document
 * per collection at `collections/<name>.json`, workspaced like pages so
 * a draft edit publishes with the site.
 *
 * Document shape:
 *   { name, title, fields: {key: {type, label?, options?}}, rows: [{id, …}], created_at, updated_at }
 *
 * Field types: text, textarea, richtext, url, image, select, toggle, number.
 */
class CollectionRepository
{
    public const FIELD_TYPES = ['text', 'textarea', 'richtext', 'url', 'image', 'select', 'toggle', 'number'];

    public function __construct(
        protected StudioStorage $storage
    ) {}

    /** @return array<string, array> keyed by name, sorted by title */
    public function all(): array
    {
        $docs = [];

        foreach ($this->storage->list('collections') as $name) {
            if ($doc = $this->find($name)) {
                $docs[$name] = $doc;
            }
        }

        uasort($docs, fn ($a, $b) => strcasecmp($a['title'] ?? $a['name'], $b['title'] ?? $b['name']));

        return $docs;
    }

    public function find(string $name): ?array
    {
        $doc = $this->storage->read("collections/{$name}.json");

        if (!$doc) {
            return null;
        }

        $doc['name'] = $doc['name'] ?? $name;
        $doc['title'] = $doc['title'] ?? Str::headline($name);
        $doc['fields'] = $this->normaliseFields($doc['fields'] ?? []);
        $doc['rows'] = array_values(array_map(fn ($row) => $this->normaliseRow($row), $doc['rows'] ?? []));

        return $doc;
    }

    public function exists(string $name): bool
    {
        return $this->storage->exists("collections/{$name}.json");
    }

    /** Create a collection. The name is the slug of the title, kept unique. */
    public function create(string $title, array $fields, array $rows = [], ?string $name = null): array
    {
        $base = Str::slug($name ?: $title) ?: 'collection';
        $slug = $base;
        $counter = 2;

        while ($this->exists($slug)) {
            $slug = $base . '-' . $counter++;
        }

        $doc = [
            'name' => $slug,
            'title' => trim($title) !== '' ? trim($title) : Str::headline($slug),
            'fields' => $this->normaliseFields($fields),
            'rows' => array_values(array_map(fn ($row) => $this->normaliseRow($row), $rows)),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->storage->write("collections/{$slug}.json", $doc);

        return $doc;
    }

    public function updateSchema(string $name, array $fields, ?string $title = null): ?array
    {
        $doc = $this->find($name);

        if (!$doc) {
            return null;
        }

        $doc['fields'] = $this->normaliseFields($fields);

        if ($title !== null && trim($title) !== '') {
            $doc['title'] = trim($title);
        }

        return $this->write($doc);
    }

    public function delete(string $name): bool
    {
        return $this->storage->delete("collections/{$name}.json");
    }

    public function rows(string $name): array
    {
        return $this->find($name)['rows'] ?? [];
    }

    public function row(string $name, string $id): ?array
    {
        foreach ($this->rows($name) as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }

    /** Upsert a row by id (a missing id creates a new row at the end) */
    public function saveRow(string $name, array $row): ?array
    {
        $doc = $this->find($name);

        if (!$doc) {
            return null;
        }

        $row = $this->normaliseRow($row);
        $replaced = false;

        foreach ($doc['rows'] as $i => $existing) {
            if ($existing['id'] === $row['id']) {
                $doc['rows'][$i] = $row;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $doc['rows'][] = $row;
        }

        $this->write($doc);

        return $row;
    }

    public function deleteRow(string $name, string $id): void
    {
        $doc = $this->find($name);

        if (!$doc) {
            return;
        }

        $doc['rows'] = array_values(array_filter($doc['rows'], fn ($row) => $row['id'] !== $id));

        $this->write($doc);
    }

    public function reorderRows(string $name, array $ids): void
    {
        $doc = $this->find($name);

        if (!$doc) {
            return;
        }

        $byId = [];
        foreach ($doc['rows'] as $row) {
            $byId[$row['id']] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
                unset($byId[$id]);
            }
        }

        $doc['rows'] = [...$ordered, ...array_values($byId)];

        $this->write($doc);
    }

    /* ---------------------------------------------------------------- */

    protected function write(array $doc): array
    {
        $doc['updated_at'] = now()->toIso8601String();

        $this->storage->write("collections/{$doc['name']}.json", $doc);

        return $doc;
    }

    /** Field configs always carry a valid type and a label */
    public function normaliseFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $key => $config) {
            // Keys are section variable names — keep their case
            // (`dateFormatted`), only strip characters Blade can't reach.
            $key = preg_replace('/[^A-Za-z0-9_]/', '_', trim((string) $key));

            if ($key === '' || is_numeric($key[0])) {
                continue;
            }

            if (is_string($config)) {
                $config = ['type' => $config];
            }

            $type = $config['type'] ?? 'text';

            $out[$key] = array_filter([
                'type' => in_array($type, self::FIELD_TYPES, true) ? $type : 'text',
                'label' => $config['label'] ?? Str::headline($key),
                'options' => $type === 'select' ? (array) ($config['options'] ?? []) : null,
                'description' => $config['description'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    protected function normaliseRow(array $row): array
    {
        if (empty($row['id']) || !is_string($row['id'])) {
            $row['id'] = (string) Str::uuid();
        }

        return $row;
    }
}
