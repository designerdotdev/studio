<x-studio::layouts.app>
    <div
        x-data="{
            iframe: null,

            init() {
                this.iframe = document.getElementById('preview-iframe');

                // Listen for messages from iframe
                window.addEventListener('message', (event) => {
                    if (event.data.type === 'component-selected') {
                        Livewire.dispatch('component-selected', { componentId: event.data.componentId });
                    } else if (event.data.type === 'component-deselected') {
                        Livewire.dispatch('component-deselected');
                    }
                });
            },

            sendToIframe(type, data) {
                if (this.iframe && this.iframe.contentWindow) {
                    this.iframe.contentWindow.postMessage({ type, ...data }, '*');
                }
            }
        }"
        x-on:variable-updated.window="sendToIframe('update-variables', { variables: $event.detail.variables })"
        class="w-full h-screen"
    >
        <iframe
            id="preview-iframe"
            class="w-full h-full border-0"
            src="{{ route('studio.iframe', ['project' => $project->id]) }}"
        ></iframe>
    </div>

    <x-slot:sidebar>
        <livewire:studio::component-editor :components="$components" />
    </x-slot:sidebar>
</x-studio::layouts.app>
