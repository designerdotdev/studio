<div
    class="flex h-full min-h-0 flex-col"
    x-data
    @studio:open-collection.window="$wire.open($event.detail.name)"
>
    @php
        $doc = $this->doc;
        $types = \Designer\Studio\Services\Storage\CollectionRepository::FIELD_TYPES;
    @endphp

    {{-- ============================ List ============================ --}}
    @if($view === 'list')
        <div class="flex shrink-0 items-center gap-1 border-b border-line px-3 py-2">
            <p class="s-microlabel flex-1">Content</p>
            <button type="button" class="s-icon-btn" title="New collection" aria-label="New collection" wire:click="startCreate">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            @forelse($this->collections as $item)
                <button
                    type="button"
                    wire:key="collection-{{ $item['name'] }}"
                    wire:click="open('{{ $item['name'] }}')"
                    class="s-section-row w-full text-left"
                >
                    <svg class="h-4 w-4 shrink-0 text-faint" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1c3.866 0 7 1.79 7 4s-3.134 4-7 4-7-1.79-7-4 3.134-4 7-4Zm5.694 8.13c.464-.264.91-.583 1.306-.952V10c0 2.21-3.134 4-7 4s-7-1.79-7-4V8.178c.396.37.842.688 1.306.953C5.838 10.006 7.854 10.5 10 10.5s4.162-.494 5.694-1.37ZM3 13.179V15c0 2.21 3.134 4 7 4s7-1.79 7-4v-1.822c-.396.37-.842.688-1.306.953-1.532.875-3.548 1.369-5.694 1.369s-4.162-.494-5.694-1.37A7.009 7.009 0 0 1 3 13.179Z"/></svg>
                    <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink/90">{{ $item['title'] }}</span>
                    <span class="shrink-0 text-[10.5px] text-faint">{{ $item['count'] }} {{ Str::plural('row', $item['count']) }}</span>
                </button>
            @empty
                <div class="px-3 py-10 text-center">
                    <p class="text-[13px] text-soft">No collections yet.</p>
                    <p class="mt-1 text-[11.5px] leading-relaxed text-faint">A collection is a list of rows — posts, team members, FAQs — that any repeater can bind to.</p>
                    <button type="button" class="s-btn-outline mt-4 !text-[11.5px]" wire:click="startCreate">New collection</button>
                </div>
            @endforelse
        </div>
    @endif

    {{-- ============================ Create ============================ --}}
    @if($view === 'create')
        <div class="flex shrink-0 items-center gap-1 border-b border-line px-2 py-2">
            <button type="button" class="s-icon-btn" wire:click="back" title="Back"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg></button>
            <p class="flex-1 truncate text-[13px] font-semibold text-ink">New collection</p>
        </div>
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5">
            <div>
                <label class="s-label">Title</label>
                <input type="text" class="s-input" placeholder="Team members" wire:model="schemaTitle" x-init="$nextTick(() => $el.focus())">
            </div>
            @include('studio::livewire.content.schema-editor')
            <button type="button" class="s-btn-primary w-full" wire:click="createCollection">Create collection</button>
        </div>
    @endif

    {{-- ============================ Table ============================ --}}
    @if($view === 'table' && $doc)
        <div class="flex shrink-0 items-center gap-1 border-b border-line px-2 py-2">
            <button type="button" class="s-icon-btn" wire:click="back" title="All collections"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg></button>
            <p class="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">{{ $doc['title'] }}</p>
            <button type="button" class="s-icon-btn" wire:click="startSchema" title="Manage fields"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.929 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.992 6.992 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd"/></svg></button>
            <button type="button" class="s-icon-btn" wire:click="newRow" title="Add row"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg></button>
        </div>

        <div class="shrink-0 border-b border-line px-3 py-2">
            <input type="search" class="s-input !h-7 !text-xs" placeholder="Filter rows…" wire:model.live.debounce.200ms="filter">
        </div>

        @php $columns = $this->columns(); @endphp
        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            <div
                class="flex flex-col gap-0.5"
                x-data
                x-init="window.Studio?.sortable($el, '.s-row-handle', (ids) => $wire.reorder(ids))"
                wire:ignore.self
            >
                @forelse($this->rows as $row)
                    <div wire:key="row-{{ $row['id'] }}" data-section-id="{{ $row['id'] }}" class="s-section-row group">
                        <span class="s-drag-handle s-row-handle group-hover:opacity-100" title="Drag to reorder">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm8-12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>
                        </span>
                        <button type="button" class="flex min-w-0 flex-1 flex-col items-start text-left" wire:click="edit('{{ $row['id'] }}')">
                            @foreach($row['cells'] as $key => $cell)
                                @if($loop->first)
                                    <span class="w-full truncate text-[12.5px] text-ink/90">{{ $cell !== '' ? $cell : '—' }}</span>
                                @else
                                    <span class="w-full truncate text-[11px] text-faint">{{ $cell }}</span>
                                @endif
                            @endforeach
                        </button>
                        <button type="button" class="s-icon-btn !h-6 !w-6 opacity-0 hover:!text-danger group-hover:opacity-100" wire:click="deleteRow('{{ $row['id'] }}')" wire:confirm="Delete this row?" title="Delete row">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="px-3 py-8 text-center">
                        <p class="text-[12.5px] text-soft">{{ $filter !== '' ? 'No rows match.' : 'No rows yet.' }}</p>
                        @if($filter === '')
                            <button type="button" class="s-btn-outline mt-3 !text-[11.5px]" wire:click="newRow">Add the first row</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ============================ Entry ============================ --}}
    @if($view === 'entry' && $doc)
        <div class="flex shrink-0 items-center gap-1 border-b border-line px-2 py-2">
            <button type="button" class="s-icon-btn" wire:click="back" title="Back to {{ $doc['title'] }}"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg></button>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[13px] font-semibold text-ink">{{ $rowId ? 'Edit row' : 'New row' }}</p>
                <p class="truncate text-[10.5px] text-faint">{{ $doc['title'] }}</p>
            </div>
            @if($rowId)
                <button type="button" class="s-icon-btn hover:!text-danger" wire:click="deleteRow('{{ $rowId }}')" wire:confirm="Delete this row?" title="Delete row"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Z" clip-rule="evenodd"/></svg></button>
            @endif
        </div>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5 pb-6" wire:key="entry-{{ $collection }}-{{ $rowId ?? 'new' }}">
            @foreach($doc['fields'] as $key => $config)
                <div wire:key="entry-field-{{ $key }}">
                    @include('studio::livewire.content.field', ['key' => $key, 'config' => $config])
                    @if(!empty($config['description']))
                        <p class="mt-1.5 text-[11px] leading-relaxed text-faint">{{ $config['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="flex shrink-0 items-center gap-2 border-t border-line p-2">
            <button type="button" class="s-btn-primary flex-1" wire:click="saveRow">{{ $rowId ? 'Save' : 'Add row' }}</button>
        </div>
    @endif

    {{-- ============================ Schema ============================ --}}
    @if($view === 'schema' && $doc)
        <div class="flex shrink-0 items-center gap-1 border-b border-line px-2 py-2">
            <button type="button" class="s-icon-btn" wire:click="back" title="Back"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg></button>
            <p class="flex-1 truncate text-[13px] font-semibold text-ink">Fields</p>
            <button type="button" class="s-icon-btn hover:!text-danger" wire:click="deleteCollection" wire:confirm="Delete “{{ $doc['title'] }}” and all of its rows? Sections bound to it will show nothing." title="Delete collection"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Z" clip-rule="evenodd"/></svg></button>
        </div>
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3.5">
            <div>
                <label class="s-label">Title</label>
                <input type="text" class="s-input" wire:model="schemaTitle">
            </div>
            @include('studio::livewire.content.schema-editor')
            <p class="text-[11px] leading-relaxed text-faint">Field keys are what sections read (<code class="font-mono">$item->{{ array_key_first($doc['fields']) ?? 'title' }}</code>). Renaming a key hides its existing values from sections until rows are updated.</p>
            <button type="button" class="s-btn-primary w-full" wire:click="saveSchema">Save fields</button>
        </div>
    @endif
</div>
