<?php

namespace Designer\Studio\Http\Controllers;

use Designer\Studio\Services\Storage\CollectionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * JSON endpoints behind the canvas's collection card: a collection-bound
 * repeater opens its rows in place, beside the list on the page, and the
 * card reads and writes them here. The Content panel stays the full editor
 * (schema, create, delete a collection) and goes through Livewire; this is
 * only rows.
 *
 * A name is whatever the binding carries — the Studio name or the data
 * file's (`collections.serviceDetails`) — so every action resolves it first.
 */
class CollectionController extends Controller
{
    /** A row value is text, a flag or a number — never a structure */
    protected const MAX_VALUE = 20000;

    public function __construct(protected CollectionRepository $collections)
    {
        // The canvas edits the draft site, so the rows must be the draft's
        if (config('studio.draft_mode', true)) {
            app(\Designer\Studio\Services\Storage\StudioStorage::class)->useDraft();
        }
    }

    public function show(string $name): JsonResponse
    {
        return $this->respond($name);
    }

    public function storeRow(Request $request, string $name): JsonResponse
    {
        $doc = $this->find($name);

        if (!$doc) {
            return $this->missing();
        }

        $row = $this->collections->saveRow($doc['name'], $this->values($request, $doc));

        return $this->respond($doc['name'], ['row' => $row]);
    }

    public function updateRow(Request $request, string $name, string $id): JsonResponse
    {
        $doc = $this->find($name);
        $existing = $doc ? $this->collections->row($doc['name'], $id) : null;

        if (!$existing) {
            return $this->missing();
        }

        // Merged over the stored row: a key the schema does not list (one
        // the template's data file carries) is kept, never dropped
        $row = $this->collections->saveRow($doc['name'], [...$existing, ...$this->values($request, $doc), 'id' => $id]);

        return $this->respond($doc['name'], ['row' => $row]);
    }

    public function destroyRow(string $name, string $id): JsonResponse
    {
        $doc = $this->find($name);

        if (!$doc) {
            return $this->missing();
        }

        $this->collections->deleteRow($doc['name'], $id);

        return $this->respond($doc['name']);
    }

    public function reorder(Request $request, string $name): JsonResponse
    {
        $doc = $this->find($name);

        if (!$doc) {
            return $this->missing();
        }

        $data = $request->validate(['ids' => 'required|array|max:2000', 'ids.*' => 'string|max:64']);

        $this->collections->reorderRows($doc['name'], $data['ids']);

        return $this->respond($doc['name']);
    }

    /* ---------------------------------------------------------------- */

    protected function find(string $name): ?array
    {
        $resolved = $this->collections->resolveName($name);

        return $resolved ? $this->collections->find($resolved) : null;
    }

    /** Only the schema's own keys, each cast to what its type stores */
    protected function values(Request $request, array $doc): array
    {
        $given = (array) $request->input('values', []);
        $values = [];

        foreach ($doc['fields'] as $key => $config) {
            if (!array_key_exists($key, $given)) {
                continue;
            }

            $value = $given[$key];
            $type = $config['type'] ?? 'text';

            if ($type === 'toggle') {
                $values[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_scalar($value) || $value === null) {
                $values[$key] = mb_substr((string) ($value ?? ''), 0, self::MAX_VALUE);
            }
        }

        return $values;
    }

    protected function respond(string $name, array $extra = []): JsonResponse
    {
        $doc = $this->find($name);

        if (!$doc) {
            return $this->missing();
        }

        return response()->json([
            'success' => true,
            'collection' => [
                'name' => $doc['name'],
                'title' => $doc['title'],
                'fields' => $doc['fields'],
                'rows' => $doc['rows'],
            ],
            ...$extra,
        ]);
    }

    protected function missing(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'That collection or row no longer exists.'], 404);
    }
}
