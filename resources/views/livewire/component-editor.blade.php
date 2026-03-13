<div
    class="h-full flex flex-col"
    x-data="{
        notifyIframe(componentId, key, value) {
            window.dispatchEvent(new CustomEvent('preview-variable-changed', {
                detail: { componentId, key, value }
            }));
        },
        notifyIframeRepeater(componentId, key) {
            // Gather current repeater values from Livewire state and send full array
            const component = @this;
            const items = component.variables[componentId]?.[key] ?? [];
            window.dispatchEvent(new CustomEvent('preview-variable-changed', {
                detail: { componentId, key, value: JSON.parse(JSON.stringify(items)) }
            }));
        },
        collapsedItems: {}
    }"
>
    @if($selectedComponent)
        <div class="p-4 border-b border-gray-200 bg-white">
            <h2 class="text-lg font-semibold text-gray-900">{{ $selectedComponent['title'] ?? 'Edit Component' }}</h2>
            @if(!empty($selectedComponent['description']))
                <p class="text-sm text-gray-500">{{ $selectedComponent['description'] }}</p>
            @endif
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-4">
            @foreach($selectedComponent['fields'] ?? [] as $key => $field)
                <div>
                    <label for="field-{{ $key }}" class="block text-sm font-medium text-gray-700 mb-1">
                        {{ $field['label'] ?? ucfirst($key) }}
                        @if(!empty($field['required']))
                            <span class="text-red-500">*</span>
                        @endif
                    </label>

                    @php $fieldType = $field['type'] ?? 'text'; @endphp

                    @if($fieldType === 'textarea')
                        <textarea
                            id="field-{{ $key }}"
                            rows="4"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            wire:model.blur="variables.{{ $selectedComponentId }}.{{ $key }}"
                            x-on:input="notifyIframe('{{ $selectedComponentId }}', '{{ $key }}', $event.target.value)"
                        ></textarea>
                    @elseif($fieldType === 'select')
                        <select
                            id="field-{{ $key }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            wire:model.live="variables.{{ $selectedComponentId }}.{{ $key }}"
                            x-on:change="notifyIframe('{{ $selectedComponentId }}', '{{ $key }}', $event.target.value)"
                        >
                            @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @elseif($fieldType === 'toggle')
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 {{ ($variables[$selectedComponentId][$key] ?? false) ? 'bg-indigo-600' : 'bg-gray-200' }}"
                            wire:click="$set('variables.{{ $selectedComponentId }}.{{ $key }}', {{ ($variables[$selectedComponentId][$key] ?? false) ? 'false' : 'true' }})"
                            x-on:click="notifyIframe('{{ $selectedComponentId }}', '{{ $key }}', {{ ($variables[$selectedComponentId][$key] ?? false) ? 'false' : 'true' }})"
                        >
                            <span class="sr-only">Toggle {{ $field['label'] ?? $key }}</span>
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ ($variables[$selectedComponentId][$key] ?? false) ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    @elseif($fieldType === 'colorpicker')
                        <input
                            type="color"
                            id="field-{{ $key }}"
                            class="block h-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            wire:model.live="variables.{{ $selectedComponentId }}.{{ $key }}"
                            x-on:input="notifyIframe('{{ $selectedComponentId }}', '{{ $key }}', $event.target.value)"
                        />
                    @elseif($fieldType === 'repeater')
                        @php
                            $repeaterItems = $variables[$selectedComponentId][$key] ?? [];
                            $subFields = $field['sub_fields'] ?? [];
                            $nestable = !empty($field['nestable']);
                            $addLabel = $field['add_button_label'] ?? 'Add Item';
                        @endphp

                        <div class="space-y-2">
                            @foreach($repeaterItems as $itemIndex => $item)
                                <div class="border border-gray-200 rounded-md bg-gray-50">
                                    {{-- Item header --}}
                                    <div class="flex items-center justify-between px-3 py-2 bg-gray-100 rounded-t-md">
                                        <span class="text-xs font-medium text-gray-600">#{{ $itemIndex + 1 }}</span>
                                        <div class="flex items-center space-x-1">
                                            {{-- Move up --}}
                                            @if($itemIndex > 0)
                                                <button type="button" class="p-1 text-gray-400 hover:text-gray-600" wire:click="moveRepeaterItem('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }}, {{ $itemIndex - 1 }})" x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))" title="Move up">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                                                </button>
                                            @endif
                                            {{-- Move down --}}
                                            @if($itemIndex < count($repeaterItems) - 1)
                                                <button type="button" class="p-1 text-gray-400 hover:text-gray-600" wire:click="moveRepeaterItem('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }}, {{ $itemIndex + 1 }})" x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))" title="Move down">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                                </button>
                                            @endif
                                            {{-- Indent (nestable only) --}}
                                            @if($nestable && $itemIndex > 0)
                                                <button type="button" class="p-1 text-gray-400 hover:text-indigo-600" wire:click="indentMenuItem('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }})" x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))" title="Make child of above">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                                </button>
                                            @endif
                                            {{-- Remove --}}
                                            <button type="button" class="p-1 text-gray-400 hover:text-red-500" wire:click="removeRepeaterItem('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }})" x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))" title="Remove">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Sub-fields --}}
                                    <div class="p-3 space-y-2">
                                        @foreach($subFields as $subKey => $subConfig)
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-0.5">{{ $subConfig['label'] ?? ucfirst($subKey) }}</label>
                                                <input
                                                    type="text"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                    value="{{ $item[$subKey] ?? $subConfig['default'] ?? '' }}"
                                                    x-on:input.debounce.400ms="
                                                        $wire.updateRepeaterSubField('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }}, '{{ $subKey }}', $event.target.value).then(() => {
                                                            notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}');
                                                        })
                                                    "
                                                />
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Nested children (nestable only) --}}
                                    @if($nestable && !empty($item['children']))
                                        <div class="ml-4 border-l-2 border-indigo-200 pl-2 pb-2 space-y-2">
                                            @foreach($item['children'] as $childIndex => $child)
                                                <div class="border border-gray-200 rounded-md bg-white">
                                                    <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 rounded-t-md">
                                                        <span class="text-xs font-medium text-indigo-500">↳ #{{ $childIndex + 1 }}</span>
                                                        <div class="flex items-center space-x-1">
                                                            {{-- Outdent --}}
                                                            <button type="button" class="p-1 text-gray-400 hover:text-indigo-600" wire:click="outdentMenuItem('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }}, {{ $childIndex }})" x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))" title="Move to top level">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                                                            </button>
                                                            {{-- Remove child --}}
                                                            <button type="button" class="p-1 text-gray-400 hover:text-red-500" wire:click="removeRepeaterChild('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }}, {{ $childIndex }})" x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))" title="Remove">
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="p-2 space-y-2">
                                                        @foreach($subFields as $subKey => $subConfig)
                                                            <div>
                                                                <label class="block text-xs font-medium text-gray-500 mb-0.5">{{ $subConfig['label'] ?? ucfirst($subKey) }}</label>
                                                                <input
                                                                    type="text"
                                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                                                    value="{{ $child[$subKey] ?? $subConfig['default'] ?? '' }}"
                                                                    x-on:input.debounce.400ms="
                                                                        $wire.updateRepeaterChildSubField('{{ $selectedComponentId }}', '{{ $key }}', {{ $itemIndex }}, {{ $childIndex }}, '{{ $subKey }}', $event.target.value).then(() => {
                                                                            notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}');
                                                                        })
                                                                    "
                                                                />
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Add button --}}
                            <button
                                type="button"
                                class="flex items-center justify-center w-full px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 border border-dashed border-indigo-300 rounded-md hover:bg-indigo-100 transition-colors"
                                wire:click="addRepeaterItem('{{ $selectedComponentId }}', '{{ $key }}')"
                                x-on:click.debounce.300ms="$nextTick(() => notifyIframeRepeater('{{ $selectedComponentId }}', '{{ $key }}'))"
                            >
                                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                {{ $addLabel }}
                            </button>
                        </div>
                    @else
                        <x-katana.input
                            type="text"
                            id="field-{{ $key }}"
                            wire:model.blur="variables.{{ $selectedComponentId }}.{{ $key }}"
                            x-on:input="notifyIframe('{{ $selectedComponentId }}', '{{ $key }}', $event.target.value)"
                        />
                    @endif

                    @if(!empty($field['description']))
                        <p class="mt-1 text-xs text-gray-500">{{ $field['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No component selected</h3>
                <p class="mt-1 text-sm text-gray-500">Click on a component in the canvas to edit it.</p>
            </div>
        </div>
    @endif
</div>
