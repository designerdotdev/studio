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

                <div
                    data-component="{{ $component['id'] }}"
                    x-on:click="selectComponent('{{ $component['id'] }}', $event)"
                >{!! $renderedHtml !!}</div>
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
