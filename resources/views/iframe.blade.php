<x-studio::layouts.iframe>
    {{-- Store templates in JavaScript to avoid HTML escaping issues --}}
    <script>
        window.__componentTemplates = @js(collect($components)->pluck('html', 'id')->toArray());
    </script>

    <div
        x-data="{
            variables: {{ Js::from($variables) }},
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

            updateVariables(newVariables) {
                this.variables = { ...this.variables, ...newVariables };
                this.renderComponents();
            },

            renderComponents() {
                const previouslySelected = this.selectedComponentId;

                document.querySelectorAll('[data-component]').forEach(el => {
                    const componentId = el.dataset.component;
                    const template = this.templates[componentId];
                    if (template && typeof blade !== 'undefined') {
                        el.innerHTML = blade.renderBladeTemplate(template, this.variables);
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
                        this.updateVariables(event.data.variables);
                    }
                });
            }
        }"
        x-on:click="deselectAll()"
    >
        @foreach($components as $component)
            @php
                $renderedHtml = \Illuminate\Support\Facades\Blade::render($component['html'], $variables);
            @endphp

            <div
                data-component="{{ $component['id'] }}"
                x-on:click="selectComponent({{ $component['id'] }}, $event)"
            >{!! $renderedHtml !!}</div>
        @endforeach
    </div>
</x-studio::layouts.iframe>
