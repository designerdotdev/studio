<x-studio::layouts.iframe>
    {{-- Store templates in JavaScript to avoid HTML escaping issues --}}
    <script>
        window.__componentTemplates = @js(collect($components)->pluck('html', 'id')->toArray());
    </script>

    <div
        x-data="{
            componentVariables: {{ Js::from($componentVariables) }},
            selectedComponentId: null,
            templates: window.__componentTemplates,

            selectComponent(componentId, event) {
                event.stopPropagation();

                // Remove previous selection
                document.querySelectorAll('[data-component].selected').forEach(el => {
                    el.classList.remove('selected');
                });

                // Add selection to clicked component
                const el = document.querySelector('[data-component=\'' + componentId + '\']');
                if (el) {
                    el.classList.add('selected');
                }

                this.selectedComponentId = componentId;

                // Notify parent window
                window.parent.postMessage({
                    type: 'component-selected',
                    componentId: componentId
                }, '*');
            },

            deselectAll() {
                document.querySelectorAll('[data-component].selected').forEach(el => {
                    el.classList.remove('selected');
                });
                this.selectedComponentId = null;

                window.parent.postMessage({
                    type: 'component-deselected'
                }, '*');
            },

            updateVariables(componentId, newVariables) {
                if (this.componentVariables[componentId]) {
                    this.componentVariables[componentId] = { ...this.componentVariables[componentId], ...newVariables };
                }
                this.renderComponent(componentId);
            },

            renderComponent(componentId) {
                const el = document.querySelector('[data-component=\'' + componentId + '\']');
                if (!el) return;

                const template = this.templates[componentId];
                const vars = this.componentVariables[componentId] || {};
                if (template && typeof blade !== 'undefined') {
                    el.innerHTML = blade.renderBladeTemplate(template, vars);
                }
            },

            renderComponents() {
                const previouslySelected = this.selectedComponentId;

                document.querySelectorAll('[data-component]').forEach(el => {
                    const componentId = el.dataset.component;
                    const template = this.templates[componentId];
                    const vars = this.componentVariables[componentId] || {};
                    if (template && typeof blade !== 'undefined') {
                        el.innerHTML = blade.renderBladeTemplate(template, vars);
                    }
                });

                // Re-apply selection
                if (previouslySelected) {
                    const el = document.querySelector('[data-component=\'' + previouslySelected + '\']');
                    if (el) {
                        el.classList.add('selected');
                    }
                }
            },

            init() {
                // Listen for messages from parent
                window.addEventListener('message', (event) => {
                    if (event.data.type === 'update-variables') {
                        this.updateVariables(event.data.componentId, event.data.variables);
                    }
                });
            }
        }"
        x-on:click="deselectAll()"
    >
        @if(count($components) > 0)
            @foreach($components as $component)
                @php
                    $renderedHtml = \Illuminate\Support\Facades\Blade::render($component['html'], $componentVariables[$component['id']] ?? []);
                @endphp

                <div class="group relative">
                    <button
                        class="absolute left-1/2 top-0 z-[10000] -translate-x-1/2 rounded-b-md bg-blue-500 px-4 py-1.5 text-sm font-medium text-white whitespace-nowrap border-none cursor-pointer opacity-0 pointer-events-none group-hover:opacity-70 group-hover:pointer-events-auto hover:!opacity-100 transition-opacity duration-150"
                        @click.stop="window.parent.postMessage({ type: 'add-section', insertAtIndex: {{ $loop->index }} }, '*')"
                    >+ Add Section</button>

                    <div
                        data-component="{{ $component['id'] }}"
                        x-on:click="selectComponent('{{ $component['id'] }}', $event)"
                    >{!! $renderedHtml !!}</div>

                    {{-- Component action buttons (bottom-right on hover) --}}
                    <div class="absolute bottom-2 right-2 z-[10000] flex items-center gap-0.5 rounded-md bg-gray-900/90 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-opacity duration-150">
                        @if(!$loop->first)
                            <button
                                class="p-1.5 text-white/70 hover:text-white cursor-pointer border-none bg-transparent transition-colors"
                                @click.stop="window.parent.postMessage({ type: 'move-component', componentId: '{{ $component['id'] }}', direction: 'up' }, '*')"
                                title="Move up"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                            </button>
                        @endif
                        @if(!$loop->last)
                            <button
                                class="p-1.5 text-white/70 hover:text-white cursor-pointer border-none bg-transparent transition-colors"
                                @click.stop="window.parent.postMessage({ type: 'move-component', componentId: '{{ $component['id'] }}', direction: 'down' }, '*')"
                                title="Move down"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </button>
                        @endif
                        <button
                            class="p-1.5 text-red-400/70 hover:text-red-400 cursor-pointer border-none bg-transparent transition-colors"
                            @click.stop="if(confirm('Delete this section?')) window.parent.postMessage({ type: 'delete-component', componentId: '{{ $component['id'] }}' }, '*')"
                            title="Delete section"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                        </button>
                    </div>

                    <button
                        class="absolute left-1/2 bottom-0 z-[10000] -translate-x-1/2 rounded-t-md bg-blue-500 px-4 py-1.5 text-sm font-medium text-white whitespace-nowrap border-none cursor-pointer opacity-0 pointer-events-none group-hover:opacity-70 group-hover:pointer-events-auto hover:!opacity-100 transition-opacity duration-150"
                        @click.stop="window.parent.postMessage({ type: 'add-section', insertAtIndex: {{ $loop->index + 1 }} }, '*')"
                    >+ Add Section</button>
                </div>
            @endforeach
        @else
            <div class="flex items-center justify-center min-h-screen">
                <div class="text-center px-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">No sections yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Add your first section to start building this page.</p>
                    <button
                        type="button"
                        @click.stop="window.parent.postMessage({ type: 'add-section' }, '*')"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Section
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-studio::layouts.iframe>
