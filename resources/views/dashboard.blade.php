<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Designer Studio</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div x-data="{
        showCreateModal: false,
        newPageTitle: '',
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
                    body: JSON.stringify({ title: this.newPageTitle }),
                });
                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (e) {
                console.error(e);
            }
            this.creating = false;
        },

        async deletePage(slug) {
            if (!confirm('Are you sure you want to delete this page?')) return;

            try {
                const response = await fetch('{{ url(config('studio.path', 'studio')) }}/api/pages/' + slug, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                }
            } catch (e) {
                console.error(e);
            }
        },

        async generateAll() {
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
        }
    }">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-900">Designer Studio</h1>
                <div class="flex gap-3">
                    <button
                        @click="generateAll()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                    >
                        Generate All Blade Files
                    </button>
                    <button
                        @click="showCreateModal = true"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        + New Page
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
            <!-- Pages Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($pages as $page)
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                        <div class="p-6">
                            <h2 class="text-xl font-semibold text-gray-900">{{ $page->title }}</h2>
                            <p class="text-gray-500 text-sm mt-1">/{{ $page->slug }}</p>
                            @if($page->description)
                                <p class="text-gray-600 mt-2">{{ $page->description }}</p>
                            @endif
                            <p class="text-gray-400 text-xs mt-4">
                                {{ count($page->components) }} component(s)
                            </p>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                            <a
                                href="{{ route('studio.page.edit', $page->slug) }}"
                                class="text-blue-600 hover:text-blue-800 font-medium"
                            >
                                Edit Page
                            </a>
                            <button
                                @click="deletePage('{{ $page->slug }}')"
                                class="text-red-500 hover:text-red-700 text-sm"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($pages->isEmpty())
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No pages</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new page.</p>
                    <div class="mt-6">
                        <button
                            @click="showCreateModal = true"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        >
                            + New Page
                        </button>
                    </div>
                </div>
            @endif

            <!-- Component Library Section -->
            @if($componentLibrary->isNotEmpty())
                <div class="mt-12">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Component Library</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        @foreach($componentLibrary as $component)
                            <div class="bg-white rounded-lg shadow p-4 text-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg mx-auto flex items-center justify-center mb-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-medium text-gray-900">{{ $component->title }}</h3>
                                <p class="text-xs text-gray-500">{{ $component->category }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </main>

        <!-- Create Page Modal -->
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
    </div>
</body>
</html>
