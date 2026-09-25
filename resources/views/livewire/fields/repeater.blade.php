@php
    $items = is_array($value) ? $value : [];
    $subFields = $field['sub_fields'] ?? [];
    $nestable = !empty($field['nestable']);
    $addLabel = $field['add_button_label'] ?? 'Add item';
    $firstSubKey = array_key_first($subFields);
@endphp

@php
    $bound = $this->bindings[$sectionId][$key] ?? null;
    $boundName = \Designer\Studio\Services\CollectionBinder::collectionName($bound);
    $collectionOptions = $this->collections;
@endphp

<div class="flex items-center justify-between">
    <span class="s-label !mb-0">{{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}</span>
    @if(!$boundName)
        <span class="text-[10.5px] text-faint">{{ count($items) }}</span>
    @endif
</div>

@if($boundName)
    {{-- Bound to a collection: its rows are edited right here. The list
         is the collection; a row opens as a form in place, Save writes the
         collection and refreshes the canvas. The canvas deep-links into
         this (studio:open-collection-row) when a rendered value is clicked. --}}
    @php
        $doc = $this->boundCollection($sectionId, $key);
        $rows = $doc['rows'] ?? [];
        $title = $doc['title'] ?? ($collectionOptions[$boundName] ?? \Illuminate\Support\Str::headline($boundName));
        $editing = $doc && $collectionEditing && ($collectionEditing['name'] ?? null) === $doc['name'];
        $editingId = $editing ? ($collectionEditing['id'] ?? null) : null;

        // Which fields name a row in the list — text-like first, like the Content panel
        $preview = [];
        $rest = [];
        foreach ($doc['fields'] ?? [] as $fk => $config) {
            if (in_array($config['type'] ?? 'text', ['text', 'textarea', 'select', 'number'], true)) {
                $preview[] = $fk;
            } elseif (($config['type'] ?? '') !== 'image') {
                $rest[] = $fk;
            }
        }
        $preview = array_slice([...$preview, ...$rest], 0, 2);
    @endphp

    <div class="mt-1.5 overflow-hidden rounded-lg border border-line bg-raised" wire:key="bound-{{ $sectionId }}-{{ $key }}">
        {{-- Header --}}
        <div class="flex h-8 shrink-0 items-center gap-1.5 border-b border-line pl-2.5 pr-1">
            @if($editing)
                <button type="button" class="s-icon-btn -ml-1.5 !h-6 !w-6" wire:click="closeCollectionRow" title="Back to {{ $title }}">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                </button>
                <span class="min-w-0 flex-1 truncate text-[11.5px] font-medium text-ink">{{ $editingId ? 'Edit row' : 'New row' }} <span class="font-normal text-faint">· {{ $title }}</span></span>
                @if($editingId)
                    <button type="button" class="s-icon-btn !h-6 !w-6 hover:!text-danger" wire:click="deleteCollectionRow" wire:confirm="Delete this row?" title="Delete row">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 0 0 6 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 1 0 .23 1.482l.149-.022.841 10.518A2.75 2.75 0 0 0 7.596 19h4.807a2.75 2.75 0 0 0 2.742-2.53l.841-10.52.149.023a.75.75 0 0 0 .23-1.482A41.03 41.03 0 0 0 14 4.193V3.75A2.75 2.75 0 0 0 11.25 1h-2.5ZM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4Z" clip-rule="evenodd"/></svg>
                    </button>
                @endif
            @else
                <svg class="h-3.5 w-3.5 shrink-0 text-accent" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1c3.866 0 7 1.79 7 4s-3.134 4-7 4-7-1.79-7-4 3.134-4 7-4Zm5.694 8.13c.464-.264.91-.583 1.306-.952V10c0 2.21-3.134 4-7 4s-7-1.79-7-4V8.178c.396.37.842.688 1.306.953C5.838 10.006 7.854 10.5 10 10.5s4.162-.494 5.694-1.37ZM3 13.179V15c0 2.21 3.134 4 7 4s7-1.79 7-4v-1.822c-.396.37-.842.688-1.306.953-1.532.875-3.548 1.369-5.694 1.369s-4.162-.494-5.694-1.37A7.009 7.009 0 0 1 3 13.179Z"/></svg>
                <span class="min-w-0 flex-1 truncate text-[11.5px] font-medium text-ink">{{ $title }}</span>
                <span class="text-[10.5px] tabular-nums text-faint">{{ count($rows) }}</span>
                @if($doc)
                    <button type="button" class="s-icon-btn !h-6 !w-6" wire:click="newCollectionRow('{{ $doc['name'] }}')" title="Add row">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    </button>
                    <button
                        type="button"
                        class="s-icon-btn !h-6 !w-6"
                        x-data
                        @click="$store.studio.setRail('content', true); window.dispatchEvent(new CustomEvent('studio:open-collection', { detail: { name: @js($doc['name']) } }))"
                        title="Open in Content — fields, reorder"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Zm7.5-2a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0V5.56l-6.22 6.22a.75.75 0 1 1-1.06-1.06L15.44 4.5H12.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>
                    </button>
                @endif
            @endif
        </div>

        @if($editing)
            {{-- Row form --}}
            <div
                class="space-y-3 p-2.5"
                wire:key="crow-{{ $sectionId }}-{{ $key }}-{{ $editingId ?? 'new' }}"
                x-data
                @keydown.enter="if ($event.target.tagName === 'INPUT') { $event.preventDefault(); $wire.saveCollectionRow(); }"
                @keydown.escape.stop="$wire.closeCollectionRow()"
            >
                @foreach($doc['fields'] as $fk => $config)
                    <div wire:key="crow-field-{{ $fk }}">
                        @include('studio::livewire.content.field', ['key' => $fk, 'config' => $config, 'model' => 'collectionRow.' . $fk])
                    </div>
                @endforeach
            </div>
            <div class="flex items-center gap-1.5 border-t border-line p-2">
                <button type="button" class="s-btn-ghost !h-7 !text-[11px]" wire:click="closeCollectionRow">Cancel</button>
                <button type="button" class="s-btn-primary !h-7 flex-1 !justify-center !text-[11px]" wire:click="saveCollectionRow">{{ $editingId ? 'Save' : 'Add row' }}</button>
            </div>
        @else
            {{-- Rows --}}
            <div class="max-h-72 overflow-y-auto p-1">
                @forelse($rows as $row)
                    @php
                        $cells = [];
                        foreach ($preview as $fk) {
                            $cell = $row[$fk] ?? '';
                            $cell = is_bool($cell) ? ($cell ? 'Yes' : 'No') : trim(strip_tags((string) $cell));
                            $cells[] = \Illuminate\Support\Str::limit($cell, 48);
                        }
                        $primary = $cells[0] ?? '';
                        $secondary = $cells[1] ?? '';
                    @endphp
                    <button
                        type="button"
                        wire:key="brow-{{ $sectionId }}-{{ $key }}-{{ $row['id'] }}"
                        wire:click="openCollectionRow('{{ $doc['name'] }}', '{{ $row['id'] }}')"
                        class="group flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-wash"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] text-ink/90">{{ $primary !== '' ? $primary : '—' }}</span>
                            @if($secondary !== '')
                                <span class="block truncate text-[10.5px] text-faint">{{ $secondary }}</span>
                            @endif
                        </span>
                        <svg class="h-3 w-3 shrink-0 text-faint opacity-0 transition-opacity group-hover:opacity-100" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                    </button>
                @empty
                    <p class="px-2 py-4 text-center text-[11.5px] text-faint">{{ $doc ? 'No rows yet.' : 'This collection no longer exists.' }}</p>
                @endforelse
            </div>
            <div class="flex items-center gap-2 border-t border-line px-2.5 py-1.5">
                <p class="min-w-0 flex-1 truncate text-[10.5px] text-faint">Shared everywhere {{ $title }} is used.</p>
                <button type="button" x-data x-show="$store.studio.developer" x-cloak class="shrink-0 text-[10.5px] text-soft underline-offset-2 hover:text-ink hover:underline" wire:click="unbindRepeater('{{ $sectionId }}', '{{ $key }}')" title="Copy the rows into this section and edit them here instead">Unbind</button>
            </div>
        @endif
    </div>
@else
<div class="mt-1.5 space-y-1.5">
    @foreach($items as $itemIndex => $item)
        @php
            $itemTitle = trim(strip_tags((string) ($item[$firstSubKey] ?? '')));
            $itemTitle = $itemTitle !== '' ? \Illuminate\Support\Str::limit($itemTitle, 28) : 'Item ' . ($itemIndex + 1);
        @endphp

        <div
            wire:key="rep-{{ $sectionId }}-{{ $key }}-{{ $itemIndex }}"
            x-data="{ open: {{ count($items) <= 2 ? 'true' : 'false' }} }"
            class="overflow-hidden rounded-lg border border-line bg-raised"
        >
            {{-- Item header --}}
            <div
                class="flex cursor-pointer items-center gap-1.5 py-1.5 pl-2.5 pr-1.5 transition-colors hover:bg-wash"
                x-on:click="open = !open"
            >
                <svg class="h-3 w-3 shrink-0 text-faint transition-transform duration-150" :class="open && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/>
                </svg>
                <span class="min-w-0 flex-1 truncate text-xs font-medium text-ink">{{ $itemTitle }}</span>

                <span class="flex items-center" x-on:click.stop>
                    @if($itemIndex > 0)
                        <button type="button" class="s-icon-btn !h-6 !w-6" wire:click="moveRepeaterItem('{{ $sectionId }}', '{{ $key }}', {{ $itemIndex }}, {{ $itemIndex - 1 }})" title="Move up">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 1 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd"/></svg>
                        </button>
                    @endif
                    @if($itemIndex < count($items) - 1)
                        <button type="button" class="s-icon-btn !h-6 !w-6" wire:click="moveRepeaterItem('{{ $sectionId }}', '{{ $key }}', {{ $itemIndex }}, {{ $itemIndex + 1 }})" title="Move down">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.53 13.53a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 1.06-1.06L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25Z" clip-rule="evenodd"/></svg>
                        </button>
                    @endif
                    @if($nestable && $itemIndex > 0)
                        <button type="button" class="s-icon-btn !h-6 !w-6" wire:click="indentRepeaterItem('{{ $sectionId }}', '{{ $key }}', {{ $itemIndex }})" title="Nest under the item above">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm5 5A.75.75 0 0 1 7.75 9h9.5a.75.75 0 0 1 0 1.5h-9.5A.75.75 0 0 1 7 9.75Zm0 5a.75.75 0 0 1 .75-.75h9.5a.75.75 0 0 1 0 1.5h-9.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>
                        </button>
                    @endif
                    <button type="button" class="s-icon-btn !h-6 !w-6 hover:!text-danger" wire:click="removeRepeaterItem('{{ $sectionId }}', '{{ $key }}', {{ $itemIndex }})" title="Remove">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </span>
            </div>

            {{-- Sub-fields --}}
            <div x-show="open" x-collapse.duration.150ms x-cloak>
                <div class="space-y-2.5 border-t border-line p-2.5">
                    @foreach($subFields as $subKey => $subConfig)
                        @include('studio::livewire.fields.sub-input', [
                            'sectionId' => $sectionId,
                            'key' => $key,
                            'subKey' => $subKey,
                            'subConfig' => $subConfig,
                            'currentValue' => $item[$subKey] ?? $subConfig['default'] ?? '',
                            'method' => "updateRepeaterSubField('{$sectionId}', '{$key}', {$itemIndex}, '{$subKey}', \$event.target.value)",
                        ])
                    @endforeach
                </div>

                {{-- Nested children --}}
                @if($nestable && !empty($item['children']))
                    <div class="space-y-1.5 border-t border-line bg-shell/40 p-2.5 pl-4">
                        <p class="s-microlabel">Nested items</p>
                        @foreach($item['children'] as $childIndex => $child)
                            @php
                                $childTitle = trim(strip_tags((string) ($child[$firstSubKey] ?? ''))) ?: 'Item ' . ($childIndex + 1);
                            @endphp
                            <div wire:key="rep-{{ $sectionId }}-{{ $key }}-{{ $itemIndex }}-{{ $childIndex }}" class="rounded-lg border border-line bg-raised">
                                <div class="flex items-center gap-1.5 py-1 pl-2.5 pr-1.5">
                                    <span class="min-w-0 flex-1 truncate text-[11px] font-medium text-soft">{{ \Illuminate\Support\Str::limit($childTitle, 26) }}</span>
                                    <button type="button" class="s-icon-btn !h-5.5 !w-5.5" wire:click="outdentRepeaterItem('{{ $sectionId }}', '{{ $key }}', {{ $itemIndex }}, {{ $childIndex }})" title="Move to top level">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17 4.75a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1 0-1.5h12.5a.75.75 0 0 1 .75.75Zm-5 5a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1 0-1.5h7.5a.75.75 0 0 1 .75.75Zm0 5a.75.75 0 0 1-.75.75H3.75a.75.75 0 0 1 0-1.5h7.5a.75.75 0 0 1 .75.75Z" clip-rule="evenodd"/></svg>
                                    </button>
                                    <button type="button" class="s-icon-btn !h-5.5 !w-5.5 hover:!text-danger" wire:click="removeRepeaterChild('{{ $sectionId }}', '{{ $key }}', {{ $itemIndex }}, {{ $childIndex }})" title="Remove">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                                    </button>
                                </div>
                                <div class="space-y-2 border-t border-line p-2">
                                    @foreach($subFields as $subKey => $subConfig)
                                        @include('studio::livewire.fields.sub-input', [
                                            'sectionId' => $sectionId,
                                            'key' => $key,
                                            'subKey' => $subKey,
                                            'subConfig' => $subConfig,
                                            'currentValue' => $child[$subKey] ?? $subConfig['default'] ?? '',
                                            'method' => "updateRepeaterChildSubField('{$sectionId}', '{$key}', {$itemIndex}, {$childIndex}, '{$subKey}', \$event.target.value)",
                                        ])
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <button
        type="button"
        class="s-btn-outline w-full !border-dashed"
        wire:click="addRepeaterItem('{{ $sectionId }}', '{{ $key }}')"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        {{ $addLabel }}
    </button>

    @if(!empty($collectionOptions) && $bound === null)
        {{-- Binding is wiring, not content: developer mode only --}}
        <div class="flex items-center gap-1.5 pt-0.5" x-data="{ pick: '' }" x-show="$store.studio.developer" x-cloak>
            <select class="s-input !h-7 flex-1 !text-[11px]" x-model="pick" @change="if (pick) { $wire.bindRepeater('{{ $sectionId }}', '{{ $key }}', pick); pick = '' }">
                <option value="">Bind to a collection…</option>
                @foreach($collectionOptions as $name => $title)
                    <option value="{{ $name }}">{{ $title }}</option>
                @endforeach
            </select>
        </div>
    @endif
</div>
@endif
