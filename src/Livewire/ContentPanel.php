<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\CollectionRepository;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The Content panel: collections → rows → one row's fields. Every save
 * refreshes the canvas so sections bound to the collection update.
 */
class ContentPanel extends Component
{
    /** list | table | entry | schema | create */
    public string $view = 'list';

    public ?string $collection = null;

    public ?string $rowId = null;

    /** The row being edited (entry view) */
    public array $row = [];

    /** Schema editor state: [{key, type, label, options}] */
    public array $schemaFields = [];

    public string $schemaTitle = '';

    public string $filter = '';

    public function boot(): void
    {
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    protected function repo(): CollectionRepository
    {
        return app(CollectionRepository::class);
    }

    /* ------------------------------------------------------------ */
    /*  Reads for the view                                           */
    /* ------------------------------------------------------------ */

    public function getCollectionsProperty(): array
    {
        return array_values(array_map(fn ($doc) => [
            'name' => $doc['name'],
            'title' => $doc['title'],
            'count' => count($doc['rows']),
            'fields' => count($doc['fields']),
        ], $this->repo()->all()));
    }

    public function getDocProperty(): ?array
    {
        return $this->collection ? $this->repo()->find($this->collection) : null;
    }

    /** Rows for the table view, with a display title + up to 2 extra columns */
    public function getRowsProperty(): array
    {
        $doc = $this->doc;

        if (!$doc) {
            return [];
        }

        $columns = $this->columns();
        $needle = mb_strtolower(trim($this->filter));

        $rows = [];

        foreach ($doc['rows'] as $row) {
            $cells = [];

            foreach ($columns as $key) {
                $cells[$key] = Str::limit(trim(strip_tags((string) ($row[$key] ?? ''))), 60);
            }

            if ($needle !== '' && !str_contains(mb_strtolower(implode(' ', $cells)), $needle)) {
                continue;
            }

            $rows[] = ['id' => $row['id'], 'cells' => $cells];
        }

        return $rows;
    }

    /** The first three "wordy" fields make the table columns */
    public function columns(): array
    {
        $doc = $this->doc;

        if (!$doc) {
            return [];
        }

        $preferred = [];
        $rest = [];

        foreach ($doc['fields'] as $key => $config) {
            if (in_array($config['type'], ['text', 'textarea', 'select', 'number'], true)) {
                $preferred[] = $key;
            } else {
                $rest[] = $key;
            }
        }

        return array_slice([...$preferred, ...$rest], 0, 3);
    }

    /* ------------------------------------------------------------ */
    /*  Navigation                                                   */
    /* ------------------------------------------------------------ */

    public function open(string $name): void
    {
        if (!$this->repo()->exists($name)) {
            return;
        }

        $this->collection = $name;
        $this->rowId = null;
        $this->row = [];
        $this->filter = '';
        $this->view = 'table';
    }

    public function back(): void
    {
        if ($this->view === 'entry' || $this->view === 'schema') {
            $this->view = 'table';
            $this->rowId = null;
            $this->row = [];

            return;
        }

        $this->view = 'list';
        $this->collection = null;
    }

    /* ------------------------------------------------------------ */
    /*  Rows                                                         */
    /* ------------------------------------------------------------ */

    public function edit(string $id): void
    {
        $row = $this->collection ? $this->repo()->row($this->collection, $id) : null;

        if (!$row) {
            return;
        }

        $this->rowId = $id;
        $this->row = $this->withAllFields($row);
        $this->view = 'entry';
    }

    public function newRow(): void
    {
        if (!$this->doc) {
            return;
        }

        $this->rowId = null;
        $this->row = $this->withAllFields([]);
        $this->view = 'entry';
    }

    /** Persist the entry form; a new row gets an id on first save */
    public function saveRow(): void
    {
        if (!$this->collection) {
            return;
        }

        $row = $this->row;

        if ($this->rowId) {
            $row['id'] = $this->rowId;
        } else {
            unset($row['id']);
        }

        $saved = $this->repo()->saveRow($this->collection, $row);

        if (!$saved) {
            return;
        }

        $this->rowId = $saved['id'];
        $this->row = $this->withAllFields($saved);

        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Saved', type: 'success');
    }

    /** Field-level autosave from the entry form (debounced in the view) */
    public function updatedRow(): void
    {
        if ($this->rowId) {
            $this->saveRow();
        }
    }

    public function deleteRow(string $id): void
    {
        if (!$this->collection) {
            return;
        }

        $this->repo()->deleteRow($this->collection, $id);

        if ($this->rowId === $id) {
            $this->back();
        }

        $this->dispatch('studio:refresh-preview');
    }

    public function reorder(array $ids): void
    {
        if ($this->collection) {
            $this->repo()->reorderRows($this->collection, array_values(array_filter($ids, 'is_string')));
            $this->dispatch('studio:refresh-preview');
        }
    }

    /* ------------------------------------------------------------ */
    /*  Collections + schema                                         */
    /* ------------------------------------------------------------ */

    public function startCreate(): void
    {
        $this->schemaTitle = '';
        $this->schemaFields = [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'options' => ''],
        ];
        $this->view = 'create';
    }

    public function createCollection(): void
    {
        $title = trim($this->schemaTitle);

        if ($title === '' || $this->schemaFields === []) {
            $this->dispatch('studio:toast', message: 'Give the collection a title and at least one field.', type: 'error');

            return;
        }

        $doc = $this->repo()->create($title, $this->fieldsFromEditor());

        $this->open($doc['name']);
        $this->dispatch('studio:toast', message: 'Collection created', type: 'success');
    }

    public function startSchema(): void
    {
        $doc = $this->doc;

        if (!$doc) {
            return;
        }

        $this->schemaTitle = $doc['title'];
        $this->schemaFields = [];

        foreach ($doc['fields'] as $key => $config) {
            $this->schemaFields[] = [
                'key' => $key,
                'type' => $config['type'],
                'label' => $config['label'] ?? Str::headline($key),
                'options' => implode(', ', $config['options'] ?? []),
            ];
        }

        $this->view = 'schema';
    }

    public function saveSchema(): void
    {
        if (!$this->collection) {
            return;
        }

        $this->repo()->updateSchema($this->collection, $this->fieldsFromEditor(), $this->schemaTitle);

        $this->view = 'table';
        $this->dispatch('studio:toast', message: 'Fields saved', type: 'success');
    }

    public function addSchemaField(): void
    {
        $this->schemaFields[] = ['key' => '', 'type' => 'text', 'label' => '', 'options' => ''];
    }

    public function removeSchemaField(int $index): void
    {
        array_splice($this->schemaFields, $index, 1);
    }

    public function deleteCollection(): void
    {
        if (!$this->collection) {
            return;
        }

        $this->repo()->delete($this->collection);
        $this->back();
        $this->view = 'list';
        $this->collection = null;

        $this->dispatch('studio:refresh-preview');
        $this->dispatch('studio:toast', message: 'Collection deleted', type: 'success');
    }

    /* ------------------------------------------------------------ */

    /** The editor rows → the repository's field map */
    protected function fieldsFromEditor(): array
    {
        $fields = [];

        foreach ($this->schemaFields as $field) {
            $key = trim((string) ($field['key'] ?? ''));

            if ($key === '') {
                $key = Str::camel(Str::slug((string) ($field['label'] ?? ''), ' '));
            }

            if ($key === '') {
                continue;
            }

            $options = array_values(array_filter(array_map('trim', explode(',', (string) ($field['options'] ?? '')))));

            $fields[$key] = [
                'type' => $field['type'] ?? 'text',
                'label' => trim((string) ($field['label'] ?? '')) ?: Str::headline($key),
                'options' => $options,
            ];
        }

        return $fields;
    }

    /** Every schema field present on the form row, with sensible blanks */
    protected function withAllFields(array $row): array
    {
        $doc = $this->doc;

        foreach ($doc['fields'] ?? [] as $key => $config) {
            if (!array_key_exists($key, $row)) {
                $row[$key] = $config['type'] === 'toggle' ? false : '';
            }
        }

        return $row;
    }

    public function render()
    {
        return view('studio::livewire.content-panel');
    }
}
