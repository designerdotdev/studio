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
            componentVariables: {{ Js::from(collect($components)->mapWithKeys(fn($comp) => [$comp['id'] => !empty($comp['variables']) ? $comp['variables'] : new \stdClass()])->toArray()) }},

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
            if (!componentVariables[componentId] || Array.isArray(componentVariables[componentId])) componentVariables[componentId] = {};
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
                    style="position:relative;z-index:10;width:100%;max-width:560px;border-radius:16px;overflow:hidden;"
                    class="bg-white text-left shadow-2xl"
                >
                    <div style="padding:24px 24px 20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                            <h3 style="font-size:18px;font-weight:600;color:#111827;" id="add-section-title">
                                Add Section
                            </h3>
                            <button
                                @click="showAddSectionModal = false"
                                style="color:#9ca3af;padding:4px;border-radius:6px;transition:color 0.15s;"
                                onmouseover="this.style.color='#6b7280'"
                                onmouseout="this.style.color='#9ca3af'"
                            >
                                <svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div style="max-height:400px;overflow-y:auto;">
                            @php
                                $grouped = $componentLibrary->groupBy('category');
                            @endphp

                            @foreach($grouped as $category => $categoryComponents)
                                <div style="margin-bottom:20px;">
                                    <h4 style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">{{ $category }}</h4>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                        @foreach($categoryComponents as $comp)
                                            <button
                                                @click="addSection('{{ $comp->name }}')"
                                                :disabled="addingSectionRef === '{{ $comp->name }}'"
                                                style="display:flex;align-items:flex-start;gap:12px;padding:14px;border-radius:12px;border:1px solid #e5e7eb;background:white;text-align:left;cursor:pointer;transition:all 0.15s;"
                                                onmouseover="this.style.borderColor='#93c5fd';this.style.background='#eff6ff'"
                                                onmouseout="this.style.borderColor='#e5e7eb';this.style.background='white'"
                                            >
                                                <div style="flex-shrink:0;width:36px;height:36px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;">
                                                    <svg style="width:18px;height:18px;color:#2563eb;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                                                    </svg>
                                                </div>
                                                <div style="min-width:0;">
                                                    <p style="font-size:14px;font-weight:500;color:#111827;" x-text="addingSectionRef === '{{ $comp->name }}' ? 'Adding...' : '{{ $comp->title }}'"></p>
                                                    @if($comp->description)
                                                        <p style="font-size:12px;color:#6b7280;margin-top:2px;">{{ $comp->description }}</p>
                                                    @endif
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div style="padding:12px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;">
                        <button
                            @click="showAddSectionModal = false"
                            style="padding:8px 16px;font-size:14px;font-weight:500;color:#374151;background:white;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;transition:background 0.15s;"
                            onmouseover="this.style.background='#f9fafb'"
                            onmouseout="this.style.background='white'"
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
                    style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;"
                    aria-labelledby="modal-title"
                    role="dialog"
                    aria-modal="true"
                >
                    <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem;">
                        <div
                            x-show="showCreateModal"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);"
                            @click="showCreateModal = false"
                        ></div>

                        <div
                            x-show="showCreateModal"
                            x-transition:enter="ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="ease-in duration-200"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            style="position:relative;z-index:10;width:100%;max-width:512px;"
                            class="bg-white rounded-xl text-left shadow-xl p-6"
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
