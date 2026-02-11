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
        x-on:variable-updated.window="sendToIframe('update-variables', { componentId: $event.detail.componentId, variables: $event.detail.variables })"
        class="w-full h-screen"
    >
        <iframe
            id="preview-iframe"
            class="w-full h-full border-0"
            src="{{ route('studio.page.iframe', ['slug' => $page->slug]) }}"
        ></iframe>
    </div>

    <x-slot:sidebar>
        <div
            x-data="{
                open: false,
                showCreateModal: false,
                newPageTitle: '',
                creating: false,
                generating: false,

                async createPage() {
                    if (!this.newPageTitle.trim()) return;

                    this.creating = true;
                    try {
                        const response = await fetch('{{ route('studio.api.pages.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ title: this.newPageTitle }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = '{{ route('studio.index') }}?page=' + data.page.slug;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    this.creating = false;
                },

                async generate() {
                    this.generating = true;
                    try {
                        const response = await fetch('{{ route('studio.api.generate') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                        });
                        const data = await response.json();
                        if (data.success) {
                            alert('Generated ' + data.count + ' Blade file(s)!');
                        }
                    } catch (e) {
                        console.error(e);
                        alert('Failed to generate Blade files');
                    }
                    this.generating = false;
                }
            }"
            class="flex flex-col h-full"
        >
            {{-- Header with page switcher and generate button --}}
            <div class="p-4 border-b border-gray-200 bg-white flex items-center justify-between gap-2">
                {{-- Page Switcher Dropdown --}}
                <div class="relative" @click.outside="open = false">
                    <button
                        @click="open = !open"
                        class="flex items-center gap-1.5 font-medium text-gray-900 hover:text-blue-600 transition-colors"
                    >
                        <span>{{ $page->title }}</span>
                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    {{-- Dropdown --}}
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute left-0 top-full mt-1 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50 py-1"
                    >
                        {{-- Page list --}}
                        @foreach($pages as $p)
                            <a
                                href="{{ route('studio.index', ['page' => $p->slug]) }}"
                                class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 transition-colors {{ $p->slug === $page->slug ? 'text-blue-600 bg-blue-50 font-medium' : 'text-gray-700' }}"
                            >
                                @if($p->slug === $page->slug)
                                    <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                @else
                                    <span class="w-4"></span>
                                @endif
                                <span>{{ $p->title }}</span>
                            </a>
                        @endforeach

                        {{-- Divider --}}
                        <div class="border-t border-gray-100 my-1"></div>

                        {{-- Create Page Button --}}
                        <button
                            @click="open = false; showCreateModal = true"
                            class="flex items-center gap-2 w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition-colors"
                        >
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Create Page</span>
                        </button>
                    </div>
                </div>

                {{-- Generate Button --}}
                <button
                    @click="generate()"
                    :disabled="generating"
                    class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:opacity-50"
                >
                    <span x-show="!generating">Generate</span>
                    <span x-show="generating">Generating...</span>
                </button>
            </div>

            {{-- Component Editor --}}
            <div class="flex-1 overflow-hidden">
                <livewire:studio::component-editor :components="$components" :page-slug="$page->slug" />
            </div>

            {{-- Create Page Modal --}}
            <template x-teleport="body">
                <div
                    x-show="showCreateModal"
                    x-cloak
                    class="fixed inset-0 z-50 overflow-y-auto"
                    aria-labelledby="modal-title"
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div
                            x-show="showCreateModal"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                            @click="showCreateModal = false"
                        ></div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div
                            x-show="showCreateModal"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                            class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
                        >
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Create New Page
                                </h3>
                                <div class="mt-4">
                                    <label for="page-title" class="block text-sm font-medium text-gray-700">Page Title</label>
                                    <input
                                        type="text"
                                        id="page-title"
                                        x-model="newPageTitle"
                                        @keydown.enter="createPage()"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        placeholder="e.g., About Us"
                                    >
                                </div>
                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                                <button
                                    type="button"
                                    @click="createPage()"
                                    :disabled="creating"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
                                >
                                    <span x-show="!creating">Create Page</span>
                                    <span x-show="creating">Creating...</span>
                                </button>
                                <button
                                    type="button"
                                    @click="showCreateModal = false; newPageTitle = ''"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-slot:sidebar>
</x-studio::layouts.app>
