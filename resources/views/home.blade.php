<x-studio::layouts.app>
    <div
        x-data="{
            iframe: null,
            showAddSectionModal: false,
            addingSectionRef: null,

            init() {
                this.iframe = document.getElementById('preview-iframe');

                // Listen for messages from iframe
                window.addEventListener('message', (event) => {
                    if (event.data.type === 'component-selected') {
                        Livewire.dispatch('component-selected', { componentId: event.data.componentId });
                    } else if (event.data.type === 'component-deselected') {
                        Livewire.dispatch('component-deselected');
                    } else if (event.data.type === 'add-section') {
                        this.showAddSectionModal = true;
                    }
                });
            },

            // Track current variables per component for sending full set to iframe
            componentVariables: {{ Js::from(collect($components)->mapWithKeys(fn($comp) => [$comp['id'] => $comp['variables'] ?? []])->toArray()) }},

            sendToIframe(type, data) {
                if (this.iframe && this.iframe.contentWindow) {
                    this.iframe.contentWindow.postMessage({ type, ...data }, '*');
                }
            },

            async addSection(componentRef) {
                this.addingSectionRef = componentRef;
                try {
                    const response = await fetch('{{ route('studio.api.pages.components.add', ['slug' => $page->slug]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ component_ref: componentRef }),
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.showAddSectionModal = false;
                        window.location.reload();
                    }
                } catch (e) {
                    console.error(e);
                }
                this.addingSectionRef = null;
            }
        }"
        x-on:preview-variable-changed.window="
            const { componentId, key, value } = $event.detail;
            if (!componentVariables[componentId]) componentVariables[componentId] = {};
            componentVariables[componentId][key] = value;
            sendToIframe('update-variables', { componentId, variables: JSON.parse(JSON.stringify(componentVariables[componentId])) });
        "
        class="w-full h-screen"
    >
        <iframe
            id="preview-iframe"
            class="w-full h-full border-0"
            src="{{ route('studio.page.iframe', ['slug' => $page->slug]) }}"
        ></iframe>

        {{-- Add Section Modal --}}
        <template x-if="showAddSectionModal">
            <div
                style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;display:flex;align-items:center;justify-content:center;"
                aria-labelledby="add-section-title"
                role="dialog"
                aria-modal="true"
            >
                {{-- Backdrop --}}
                <div
                    style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);"
                    @click="showAddSectionModal = false"
                ></div>

                {{-- Modal Panel --}}
                <div
                    style="position:relative;z-index:10;width:100%;max-width:672px;"
                    class="bg-white rounded-lg text-left shadow-xl"
                >
                <div class="px-6 pt-5 pb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="add-section-title">
                            Add Section
                        </h3>
                        <button
                            @click="showAddSectionModal = false"
                            class="text-gray-400 hover:text-gray-500 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div style="max-height:384px;overflow-y:auto;">
                        @php
                            $grouped = $componentLibrary->groupBy('category');
                        @endphp

                        @foreach($grouped as $category => $categoryComponents)
                            <div class="mb-4">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ $category }}</h4>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($categoryComponents as $comp)
                                        <button
                                            @click="addSection('{{ $comp->name }}')"
                                            :disabled="addingSectionRef === '{{ $comp->name }}'"
                                            class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition-colors text-left disabled:opacity-50"
                                        >
                                            <div class="flex-shrink-0 mt-0.5">
                                                <div class="w-8 h-8 rounded-md bg-blue-100 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900" x-text="addingSectionRef === '{{ $comp->name }}' ? 'Adding...' : '{{ $comp->title }}'"></p>
                                                @if($comp->description)
                                                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $comp->description }}</p>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-end rounded-b-lg">
                    <button
                        @click="showAddSectionModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </template>
    </div>

    <x-slot:sidebar>
        <div
            x-data="{
                open: false,
                showCreateModal: false,
                newPageTitle: '',
                newPageSlug: '',
                slugManuallyEdited: false,
                creating: false,

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
                            body: JSON.stringify({ title: this.newPageTitle, slug: this.newPageSlug }),
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

            }"
            class="flex flex-col h-full"
        >
            {{-- Header with page switcher --}}
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

                <div class="flex items-center gap-1.5">
                    {{-- Preview Button --}}
                    <a
                        href="{{ $page->slug === config('studio.page_routing.home_slug', 'home') ? '/' : '/' . $page->slug }}"
                        target="_blank"
                        class="px-3 py-1.5 text-xs font-medium bg-gray-900 text-white rounded-md hover:bg-black transition-colors inline-flex items-center gap-1"
                    >
                        Preview
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
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
                            class="fixed inset-0 bg-black/50 transition-opacity"
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
                            class="relative z-10 inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6"
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
                                        @input="if (!slugManuallyEdited) { newPageSlug = newPageTitle.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, ''); }"
                                        @keydown.enter="createPage()"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        placeholder="e.g., About Us"
                                    >
                                </div>
                                <div class="mt-3">
                                    <label for="page-slug" class="block text-sm font-medium text-gray-700">URL Slug</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">/</span>
                                        <input
                                            type="text"
                                            id="page-slug"
                                            x-model="newPageSlug"
                                            @input="slugManuallyEdited = true"
                                            @keydown.enter="createPage()"
                                            class="flex-1 min-w-0 block w-full border-gray-300 rounded-none rounded-r-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                            placeholder="about-us"
                                        >
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">This will be the URL where the page lives.</p>
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
                                    @click="showCreateModal = false; newPageTitle = ''; newPageSlug = ''; slugManuallyEdited = false"
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
