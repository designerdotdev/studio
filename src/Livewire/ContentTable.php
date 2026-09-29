<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Content on the stage: one collection as a table, and a drawer that slides
 * over it to edit an entry, the collection's fields, or to start a new
 * collection. The sidebar's `ContentPanel` lists the collections; this owns
 * which one is open (`$store.studio.collection` mirrors it for that list).
 *
 * The drawer saves when asked to — Save changes — and Cancel leaves the entry
 * as it was. Every save refreshes the canvas so sections bound to the
 * collection are up to date when the page comes back.
 */
class ContentTable extends Component
{
    public ?string $collection = null;

    /** data | structure */
    public string $tab = 'data';

    public string $search = '';

    /** The field the table is sorted by; '' is the collection's own order */
    public string $sort = '';

    public string $dir = 'asc';

    /** null | entry | schema | create */
    public ?string $drawer = null;

    /** Bumped each time the drawer opens, so its fields mount afresh */
    public int $drawerToken = 0;

    public ?string $rowId = null;

    /** The entry in the drawer */
    public array $row = [];

    /** Schema editor state: [{key, type, label, options}] */
    public array $schemaFields = [];

    public string $schemaTitle = '';

    public function boot(): void
    {
        if (config('studio.draft_mode', true)) {
            app(StudioStorage::class)->useDraft();
        }
    }

    public function mount(): void
    {
        $this->collection = $this->firstCollection();
    }

    protected function repo(): CollectionRepository
    {
        return app(CollectionRepository::class);
    }

    /** The first collection in the sidebar's list */
    protected function firstCollection(): ?string
    {
        return array_key_first($this->repo()->all());
    }

    /* ------------------------------------------------------------ */
    /*  Reads for the view                                           */
    /* ------------------------------------------------------------ */

    public function getDocProperty(): ?array
    {
        return $this->collection ? $this->repo()->find($this->collection) : null;
    }

    /** Where the collection lives on disk, for developer mode's toolbar */
    public function getFileProperty(): ?string
    {
        $doc = $this->doc;

        return $doc ? 'resources/designer/data/collections/' . ($doc['source'] ?? $doc['name']) . '.json' : null;
    }

    /**
     * The table: every field a column, every entry a row — searched across
     * all of its fields and sorted by one of them.
     *
     * @return list<array{id: string, position: int, cells: array<string, array{type: string, text: string}>}>
     */
    public function getRowsProperty(): array
    {
        $doc = $this->doc;

        if (!$doc) {
            return [];
        }

        $needle = mb_strtolower(trim($this->search));
        $rows = [];

        foreach ($doc['rows'] as $index => $row) {
            $cells = [];

            foreach ($doc['fields'] as $key => $config) {
                $cells[$key] = ['type' => $config['type'], 'text' => $this->cellText($row[$key] ?? '', $config)];
            }

            if ($needle !== '' && !str_contains(mb_strtolower(implode(' ', array_column($cells, 'text'))), $needle)) {
                continue;
            }

            $rows[] = ['id' => $row['id'], 'position' => $index + 1, 'cells' => $cells];
        }

        if ($this->sort !== '' && isset($doc['fields'][$this->sort])) {
            $key = $this->sort;

            usort($rows, fn ($a, $b) => strnatcasecmp($a['cells'][$key]['text'], $b['cells'][$key]['text']));

            if ($this->dir === 'desc') {
                $rows = array_reverse($rows);
            }
        }

        return $rows;
    }

    /** Rows can be dragged into a new order only while the table shows that order */
    public function getReorderableProperty(): bool
    {
        return $this->sort === '' && trim($this->search) === '';
    }

    /** What a value reads as in a cell: plain text, whatever the field holds */
    protected function cellText(mixed $value, array $config): string
    {
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($config['type'] === 'toggle') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        $value = (string) $value;

        if ($config['type'] === 'select') {
            $options = $config['options'] ?? [];

            return !array_is_list($options) && isset($options[$value]) ? (string) $options[$value] : $value;
        }

        if ($config['type'] === 'image') {
            return $value;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), 140);
    }

    /* ------------------------------------------------------------ */
    /*  The table                                                    */
    /* ------------------------------------------------------------ */

    /**
     * The sidebar's list (`studio:content-open`), and "Edit in Content" from
     * the inspector or the canvas's collection card (the view relays the
     * `studio:open-collection` window event here) — which name a collection
     * the way a binding does, so the name is resolved first.
     */
    #[On('studio:content-open')]
    public function open(string $name): void
    {
        $name = $this->repo()->resolveName($name) ?? $name;

        if (!$this->repo()->exists($name)) {
            return;
        }

        if ($name !== $this->collection) {
            $this->search = '';
            $this->sort = '';
            $this->dir = 'asc';
        }

        $this->collection = $name;
        $this->tab = 'data';
        $this->closeDrawer();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'structure' ? 'structure' : 'data';
    }

    /** A header click: ascending, then descending, then the collection's own order */
    public function sortBy(string $key): void
    {
        if ($this->sort !== $key) {
            $this->sort = $key;
            $this->dir = 'asc';

            return;
        }

        if ($this->dir === 'asc') {
            $this->dir = 'desc';

            return;
        }

        $this->sort = '';
        $this->dir = 'asc';
    }

    public function reorder(array $ids): void
    {
        if ($this->collection && $this->reorderable) {
            $this->repo()->reorderRows($this->collection, array_values(array_filter($ids, 'is_string')));
            $this->dispatch('studio:refresh-preview');
        }
    }

    /**
     * Rows were edited in the canvas's collection card. The table reads the
     * collection at render; an open entry holds a copy, so it takes the
     * newer values rather than saving stale ones over them.
     */
    #[On('studio:collection-changed')]
    public function refreshAfterCanvasEdit(string $name = ''): void
    {
        if ($this->drawer === 'entry' && $this->rowId && $this->collection === $name && ($row = $this->repo()->row($name, $this->rowId))) {
            $this->row = [...$this->row, ...$row];
        }
    }

    /* ------------------------------------------------------------ */
    /*  The drawer: an entry                                         */
    /* ------------------------------------------------------------ */

    public function edit(string $id): void
    {
        $row = $this->collection ? $this->repo()->row($this->collection, $id) : null;

        if (!$row) {
            return;
        }

        $this->rowId = $id;
        $this->row = $this->withAllFields($row);
        $this->openDrawer('entry');
    }

    /** A blank entry; it joins the collection when it is first saved */
    public function newRow(): void
    {
        if (!$this->doc) {
            return;
        }

        $this->rowId = null;
        $this->row = $this->withAllFields([]);
        $this->openDrawer('entry');
    }

    public function saveRow(): void
    {
        if (!$this->collection || $this->drawer !== 'entry') {
            return;
        }

        $row = $this->row;

        if ($this->rowId) {
            // Over the stored entry: a key the fields don't list stays as it is
            $row = [...($this->repo()->row($this->collection, $this->rowId) ?? []), ...$row, 'id' => $this->rowId];
        } else {
            unset($row['id']);
        }

        if (!$this->repo()->saveRow($this->collection, $row)) {
            $this->dispatch('studio:toast', message: 'That entry could not be saved.', type: 'error');

            return;
        }

        $created = $this->rowId === null;

        $this->closeDrawer();
        $this->changed();
        $this->dispatch('studio:toast', message: $created ? 'Entry added' : 'Saved', type: 'success');
    }

    public function deleteRow(): void
    {
        if (!$this->collection || !$this->rowId) {
            return;
        }

        $this->repo()->deleteRow($this->collection, $this->rowId);

        $this->closeDrawer();
        $this->changed();
        $this->dispatch('studio:toast', message: 'Entry deleted', type: 'success');
    }

    /* ------------------------------------------------------------ */
    /*  The drawer: collections + their fields                       */
    /* ------------------------------------------------------------ */

    #[On('studio:content-create')]
    public function startCreate(): void
    {
        $this->schemaTitle = '';
        $this->schemaFields = [
            ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'options' => ''],
        ];
        $this->openDrawer('create');
    }

    public function createCollection(): void
    {
        $title = trim($this->schemaTitle);

        if ($title === '' || $this->fieldsFromEditor() === []) {
            $this->dispatch('studio:toast', message: 'Give the collection a title and at least one field.', type: 'error');

            return;
        }

        $doc = $this->repo()->create($title, $this->fieldsFromEditor());

        $this->open($doc['name']);
        $this->changed();
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
                'options' => $this->optionsToText($config['options'] ?? []),
            ];
        }

        $this->openDrawer('schema');
    }

    public function saveSchema(): void
    {
        if (!$this->collection || $this->drawer !== 'schema') {
            return;
        }

        $this->repo()->updateSchema($this->collection, $this->fieldsFromEditor(), $this->schemaTitle);

        $this->closeDrawer();
        $this->changed();
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

        $this->closeDrawer();
        $this->collection = $this->firstCollection();
        $this->tab = 'data';
        $this->search = '';
        $this->sort = '';

        $this->changed();
        $this->dispatch('studio:toast', message: 'Collection deleted', type: 'success');
    }

    /* ------------------------------------------------------------ */

    protected function openDrawer(string $name): void
    {
        $this->drawer = $name;
        $this->drawerToken++;
    }

    public function closeDrawer(): void
    {
        $this->drawer = null;
        $this->rowId = null;
        $this->row = [];
    }

    /** Tell the sidebar's list and the canvas that the content is different now */
    protected function changed(): void
    {
        $this->dispatch('studio:content-changed');
        $this->dispatch('studio:refresh-preview');
    }

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

            $fields[$key] = [
                'type' => $field['type'] ?? 'text',
                'label' => trim((string) ($field['label'] ?? '')) ?: Str::headline($key),
                'options' => $this->optionsFromText((string) ($field['options'] ?? '')),
            ];
        }

        return $fields;
    }

    /**
     * A select's options as the schema editor shows them: `Yes, No` for a
     * plain list, `yes: Yes, no: No` when the stored value and its label
     * differ — and back again, so saving the fields keeps the values
     * sections compare against.
     */
    protected function optionsToText(array $options): string
    {
        if (array_is_list($options)) {
            return implode(', ', $options);
        }

        return implode(', ', array_map(fn ($value, $label) => $value . ': ' . $label, array_keys($options), $options));
    }

    protected function optionsFromText(string $text): array
    {
        $options = [];
        $keyed = false;

        foreach (array_filter(array_map('trim', explode(',', $text)), fn ($part) => $part !== '') as $part) {
            if (preg_match('/^([^:]+):\s*(.+)$/', $part, $match)) {
                $options[trim($match[1])] = trim($match[2]);
                $keyed = true;
            } else {
                $options[$part] = $part;
            }
        }

        return $keyed ? $options : array_values($options);
    }

    /** Every schema field present on the form row, with sensible blanks */
    protected function withAllFields(array $row): array
    {
        foreach ($this->doc['fields'] ?? [] as $key => $config) {
            if (!array_key_exists($key, $row)) {
                $row[$key] = $config['type'] === 'toggle' ? false : '';
            }
        }

        return $row;
    }

    public function render()
    {
        // The open collection is gone (deleted, or renamed in its file): the
        // first one in the list takes its place
        if (!$this->collection || !$this->repo()->exists($this->collection)) {
            $this->collection = $this->firstCollection();
        }

        return view('studio::livewire.content-table');
    }
}
