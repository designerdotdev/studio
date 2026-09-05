{{-- Shared by the create and manage-fields views: edits $schemaFields --}}
<div>
    <div class="flex items-center justify-between">
        <span class="s-label !mb-0">Fields</span>
        <span class="text-[10.5px] text-faint">{{ count($schemaFields) }}</span>
    </div>

    <div class="mt-1.5 space-y-1.5">
        @foreach($schemaFields as $index => $field)
            <div wire:key="schema-field-{{ $index }}" class="rounded-lg border border-line bg-raised p-2 space-y-1.5">
                <div class="flex gap-1.5">
                    <input type="text" class="s-input !h-7 min-w-0 flex-1 !text-xs" placeholder="Label" wire:model="schemaFields.{{ $index }}.label">
                    <select class="s-input !h-7 w-[104px] shrink-0 !text-xs" wire:model="schemaFields.{{ $index }}.type">
                        @foreach(\Designer\Studio\Services\Storage\CollectionRepository::FIELD_TYPES as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="s-icon-btn !h-7 !w-7 shrink-0 hover:!text-danger" wire:click="removeSchemaField({{ $index }})" title="Remove field">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>
                <div class="flex gap-1.5">
                    <input type="text" class="s-input !h-7 min-w-0 flex-1 !font-mono !text-[11px]" placeholder="key (auto from label)" wire:model="schemaFields.{{ $index }}.key">
                    @if(($field['type'] ?? '') === 'select')
                        <input type="text" class="s-input !h-7 min-w-0 flex-1 !text-[11px]" placeholder="Options, comma separated" wire:model="schemaFields.{{ $index }}.options">
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" class="s-btn-outline mt-1.5 w-full !border-dashed" wire:click="addSchemaField">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        Add field
    </button>
</div>
