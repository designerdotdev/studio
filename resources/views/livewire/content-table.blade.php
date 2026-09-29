{{--
    One collection on the stage: a toolbar, the table, and the drawer that
    slides over both. Data is the entries; Structure (developer mode) is the
    collection's fields. See ContentTable for what each action does.
--}}
@php
    $doc = $this->doc;
    $rows = $doc ? $this->rows : [];
    $total = $doc ? count($doc['rows']) : 0;
    $reorderable = $this->reorderable;
    $position = $rowId && $doc ? array_search($rowId, array_column($doc['rows'], 'id'), true) : false;
@endphp
<div
    class="flex h-full min-h-0 flex-col"
    x-data
    x-effect="
        $store.studio.collection = $wire.collection;
        // Structure and the fields drawers are developer surfaces: turning the switch off puts them away
        if (!$store.studio.developer && ($wire.tab === 'structure' || ['schema', 'create'].includes($wire.drawer))) { $wire.drawer = null; $wire.setTab('data') }
    "
    @studio:open-collection.window="$wire.open($event.detail.name)"
>
    @if($doc)
        {{-- ============================ Toolbar ============================ --}}
        <div class="flex h-11 shrink-0 items-center gap-3 border-b border-line px-4">
            <h2 class="truncate text-[13px] font-semibold text-ink">{{ $doc['title'] }}</h2>
            <span class="shrink-0 text-[11.5px] text-faint tabular-nums">{{ $total }} {{ Str::plural('entry', $total) }}</span>
            <span x-show="$store.studio.developer" x-cloak class="min-w-0 truncate font-mono text-[10.5px] text-faint">{{ $this->file }}</span>
            <span class="flex-1"></span>

            {{-- Search leads the cluster: it is the only control that leaves
                 in Structure, so the buttons to its right never shift --}}
            @if($tab === 'data')
                <label class="s-search">
                    <svg class="h-3.5 w-3.5 shrink-0 text-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    <input type="search" placeholder="Search entries…" aria-label="Search entries" spellcheck="false" wire:model.live.debounce.250ms="search">
                </label>
                <button type="button" class="s-btn-accent" wire:click="newRow">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Add entry
                </button>
            @else
                <button type="button" class="s-btn-outline" wire:click="startSchema">Edit fields</button>
            @endif

            <div class="s-seg" x-show="$store.studio.developer" x-cloak role="tablist" aria-label="View">
                <button type="button" role="tab" class="s-seg-btn !w-auto px-2.5 text-[11.5px] font-medium {{ $tab === 'data' ? 'is-active' : '' }}" aria-selected="{{ $tab === 'data' ? 'true' : 'false' }}" wire:click="setTab('data')">Data</button>
                <button type="button" role="tab" class="s-seg-btn !w-auto px-2.5 text-[11.5px] font-medium {{ $tab === 'structure' ? 'is-active' : '' }}" aria-selected="{{ $tab === 'structure' ? 'true' : 'false' }}" wire:click="setTab('structure')">Structure</button>
            </div>

            <button type="button" class="s-icon-btn" title="Refresh" aria-label="Refresh" wire:click="$refresh">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" wire:loading.class="animate-spin" wire:target="$refresh"><path d="M4.5 12a7.5 7.5 0 0 1 13.15-4.95M19.5 12a7.5 7.5 0 0 1-13.15 4.95M17.65 3v4.05H13.6M6.35 21v-4.05h4.05"/></svg>
            </button>
        </div>

        {{-- ============================ Data ============================ --}}
        @if($tab === 'data')
            <div class="relative min-h-0 flex-1 overflow-auto" wire:key="data-{{ $collection }}">
                <table class="s-table">
                    <thead>
                        <tr>
                            <th class="w-14">#</th>
                            @foreach($doc['fields'] as $key => $config)
                                <th wire:key="head-{{ $key }}" aria-sort="{{ $sort === $key ? ($dir === 'desc' ? 'descending' : 'ascending') : 'none' }}">
                                    <button type="button" class="s-table-sort" wire:click="sortBy(@js($key))" title="Sort by {{ $config['label'] }}">
                                        <span>{{ $config['label'] }}</span>
                                        @if($sort === $key)
                                            <svg class="h-3 w-3 {{ $dir === 'desc' ? 'rotate-180' : '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                                        @endif
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    {{-- Dragging a row by its number reorders the collection —
                         only while the table shows the collection's own order --}}
                    <tbody
                        wire:key="body-{{ $collection }}-{{ $reorderable ? 'own' : 'view' }}"
                        wire:ignore.self
                        @if($reorderable) x-init="window.Studio?.sortable($el, '.s-table-handle', (ids) => $wire.reorder(ids))" @endif
                    >
                        @foreach($rows as $row)
                            <tr wire:key="row-{{ $row['id'] }}" data-section-id="{{ $row['id'] }}" tabindex="0" wire:click="edit(@js($row['id']))" @keydown.enter.self="$wire.edit(@js($row['id']))">
                                <td class="s-table-num">
                                    <span class="s-table-pos">{{ $row['position'] }}</span>
                                    @if($reorderable)
                                        <span class="s-table-handle" title="Drag to reorder" @click.stop>
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm8-12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>
                                        </span>
                                    @endif
                                </td>
                                @foreach($row['cells'] as $key => $cell)
                                    <td wire:key="cell-{{ $row['id'] }}-{{ $key }}" @if($cell['type'] !== 'image') title="{{ $cell['text'] }}" @endif>
                                        @if($cell['text'] === '')
                                            <span class="text-faint">—</span>
                                        @elseif($cell['type'] === 'image')
                                            <img src="{{ $cell['text'] }}" alt="" loading="lazy" class="s-table-thumb">
                                        @else
                                            {{ $cell['text'] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($rows === [])
                    <div class="flex flex-col items-center px-6 py-16 text-center">
                        @if(trim($search) !== '')
                            <p class="text-[13px] font-medium text-soft">No entries match</p>
                            <p class="mt-1 text-[12px] text-faint">Nothing in {{ $doc['title'] }} matches “{{ $search }}”.</p>
                        @else
                            <p class="text-[13px] font-medium text-soft">No entries in {{ $doc['title'] }} yet</p>
                            <p class="mt-1 text-[12px] text-faint">Every section bound to this collection shows what you add here.</p>
                            <button type="button" class="s-btn-outline mt-4" wire:click="newRow">Add the first entry</button>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- ============================ Structure ============================ --}}
        @if($tab === 'structure')
            <div class="relative min-h-0 flex-1 overflow-auto" wire:key="structure-{{ $collection }}">
                <table class="s-table is-static">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Label</th>
                            <th>Type</th>
                            <th>Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($doc['fields'] as $key => $config)
                            <tr wire:key="field-{{ $key }}">
                                <td class="font-mono !text-[12px]">{{ $key }}</td>
                                <td>{{ $config['label'] }}</td>
                                <td class="font-mono !text-[12px] !text-accent">{{ $config['type'] }}</td>
                                <td>
                                    @if(!empty($config['options']))
                                        {{ implode(', ', $config['options']) }}
                                    @else
                                        <span class="text-faint">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @else
        {{-- ============================ Nothing yet ============================ --}}
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center px-6 text-center">
            <p class="text-[14px] font-medium text-ink">No collections yet</p>
            <p class="mt-1.5 max-w-sm text-[12.5px] leading-relaxed text-soft">A collection is a list of entries — posts, team members, FAQs — that any repeater on a page can bind to.</p>
            <button type="button" x-show="$store.studio.developer" x-cloak class="s-btn-accent mt-5" wire:click="startCreate">New collection</button>
        </div>
    @endif

    {{-- ============================ The drawer ============================ --}}
    {{-- A full-height slide-over above the whole editor. It opens when the
         server says so and closes at once on the client ($wire.drawer = null
         travels with the next request), so closing never waits on a round trip. --}}
    <div
        class="s-drawer"
        x-show="$wire.drawer"
        x-cloak
        @keydown.escape.window="if ($wire.drawer) $wire.drawer = null"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="s-drawer-scrim"
            x-show="$wire.drawer"
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="$wire.drawer = null"
        ></div>

        <div
            class="s-drawer-panel"
            x-show="$wire.drawer"
            x-transition:enter="transition-transform ease-[cubic-bezier(0.22,1,0.36,1)] duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <div class="flex h-11 shrink-0 items-center gap-2 border-b border-line pr-2.5 pl-5">
                <p class="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink">
                    @if($drawer === 'entry' && $doc)
                        {{ $rowId ? 'Edit' : 'New' }} <span class="font-normal text-soft">{{ Str::singular($doc['title']) }}</span>
                        @if($position !== false) <span class="font-mono font-normal text-faint">#{{ $position + 1 }}</span> @endif
                    @elseif($drawer === 'schema' && $doc)
                        Fields <span class="font-normal text-soft">{{ $doc['title'] }}</span>
                    @elseif($drawer === 'create')
                        New collection
                    @endif
                </p>
                <button type="button" class="s-icon-btn" aria-label="Close" @click="$wire.drawer = null">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- The scroll region is the drawer's full width (the scrollbar
                 hugs its edge); the fields sit in a centered reading column.
                 Keyed by the open token so every field mounts afresh. --}}
            <div class="min-h-0 flex-1 overflow-y-auto" wire:key="drawer-{{ $drawer ?? 'shut' }}-{{ $drawerToken }}">
                <div class="mx-auto w-full max-w-2xl space-y-5 px-6 py-8">
                    @if($drawer === 'entry' && $doc)
                        @foreach($doc['fields'] as $key => $config)
                            <div wire:key="entry-field-{{ $key }}">
                                @include('studio::livewire.content.field', ['key' => $key, 'config' => $config])
                                @if(!empty($config['description']))
                                    <p class="mt-1.5 text-[11px] leading-relaxed text-faint">{{ $config['description'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    @if($drawer === 'create')
                        <div>
                            <label class="s-label" for="studio-collection-title">Title</label>
                            <input id="studio-collection-title" type="text" class="s-input" placeholder="Team members" wire:model="schemaTitle" x-init="$nextTick(() => $el.focus())">
                        </div>
                        @include('studio::livewire.content.schema-editor')
                    @endif

                    @if($drawer === 'schema' && $doc)
                        <div>
                            <label class="s-label" for="studio-collection-title">Title</label>
                            <input id="studio-collection-title" type="text" class="s-input" wire:model="schemaTitle">
                        </div>
                        @include('studio::livewire.content.schema-editor')
                        <p class="text-[11px] leading-relaxed text-faint">Field keys are what sections read (<code class="font-mono">$item->{{ array_key_first($doc['fields']) ?? 'title' }}</code>). Renaming a key hides its existing values from sections until entries are updated.</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2 border-t border-line px-4 py-3">
                @if($drawer === 'entry' && $rowId)
                    <button type="button" class="s-btn-ghost hover:!bg-danger/10 hover:!text-danger" wire:click="deleteRow" wire:confirm="Delete this entry? This cannot be undone.">Delete entry</button>
                @elseif($drawer === 'schema' && $doc)
                    <button type="button" class="s-btn-ghost hover:!bg-danger/10 hover:!text-danger" wire:click="deleteCollection" wire:confirm="Delete “{{ $doc['title'] }}” and all of its entries? Sections bound to it will show nothing.">Delete collection</button>
                @endif
                <span class="flex-1"></span>
                <button type="button" class="s-btn-ghost" @click="$wire.drawer = null">Cancel</button>
                @if($drawer === 'entry')
                    <button type="button" class="s-btn-accent" wire:click="saveRow" wire:loading.attr="disabled" wire:target="saveRow">{{ $rowId ? 'Save changes' : 'Add entry' }}</button>
                @elseif($drawer === 'schema')
                    <button type="button" class="s-btn-accent" wire:click="saveSchema" wire:loading.attr="disabled" wire:target="saveSchema">Save fields</button>
                @elseif($drawer === 'create')
                    <button type="button" class="s-btn-accent" wire:click="createCollection" wire:loading.attr="disabled" wire:target="createCollection">Create collection</button>
                @endif
            </div>
        </div>
    </div>
</div>
