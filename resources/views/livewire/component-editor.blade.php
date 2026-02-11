<div class="h-full flex flex-col">
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
                            wire:model.live.debounce.300ms="variables.{{ $selectedComponentId }}.{{ $key }}"
                        ></textarea>
                    @elseif($fieldType === 'select')
                        <select
                            id="field-{{ $key }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            wire:model.live="variables.{{ $selectedComponentId }}.{{ $key }}"
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
                        />
                    @else
                        <input
                            type="text"
                            id="field-{{ $key }}"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            wire:model.live.debounce.300ms="variables.{{ $selectedComponentId }}.{{ $key }}"
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
