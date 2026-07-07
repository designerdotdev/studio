@php
    $items = is_array($value) ? $value : [];
    $subFields = $field['sub_fields'] ?? [];
    $nestable = !empty($field['nestable']);
    $addLabel = $field['add_button_label'] ?? 'Add item';
    $firstSubKey = array_key_first($subFields);
@endphp

<div class="flex items-center justify-between">
    <span class="s-label !mb-0">{{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}</span>
    <span class="text-[10.5px] text-faint">{{ count($items) }}</span>
</div>

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
                class="flex cursor-pointer items-center gap-1.5 py-1.5 pl-2.5 pr-1.5 transition-colors hover:bg-white/4"
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
</div>
