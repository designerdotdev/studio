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
            src="{{ route('studio.page.iframe', ['slug' => $page->slug]) }}"
        ></iframe>
    </div>

    <x-slot:sidebar>
        <div class="p-4 border-b border-gray-200 bg-white flex items-center justify-between">
            <a href="{{ route('studio.index') }}" class="text-blue-600 hover:text-blue-800">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <span class="font-medium text-gray-900">{{ $page->title }}</span>
            <span></span>
        </div>
        <livewire:studio::component-editor :components="$components" :page-slug="$page->slug" />
    </x-slot:sidebar>
</x-studio::layouts.app>
