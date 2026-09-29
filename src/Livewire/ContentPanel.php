<?php

namespace Designer\Studio\Livewire;

use Designer\Studio\Services\Storage\CollectionRepository;
use Designer\Studio\Services\Storage\StudioStorage;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The sidebar's Content tab: the collections, and nothing else. Choosing one
 * shows its entries on the stage (`ContentTable`), which owns the selection
 * and everything that edits — this list only names what there is.
 */
class ContentPanel extends Component
{
    public function boot(): void
    {
        if (config('studio.draft_mode', true)) {
            app(StudioStorage::class)->useDraft();
        }
    }

    /** @return list<array{name: string, title: string, count: int}> */
    public function getCollectionsProperty(): array
    {
        return array_values(array_map(fn ($doc) => [
            'name' => $doc['name'],
            'title' => $doc['title'],
            'count' => count($doc['rows']),
        ], app(CollectionRepository::class)->all()));
    }

    /**
     * Entries or collections changed — on the stage (`studio:content-changed`)
     * or in the canvas's collection card (`studio:collection-changed`). The
     * list reads the collections at render, so re-rendering is the refresh.
     */
    #[On('studio:content-changed')]
    #[On('studio:collection-changed')]
    public function refresh(): void {}

    public function render()
    {
        return view('studio::livewire.content-panel');
    }
}
