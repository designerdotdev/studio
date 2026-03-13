<x-studio::layouts.app>
    <div class="w-full h-full flex items-center justify-center bg-gray-100">
        <p class="text-gray-400 text-sm">Complete the setup to get started.</p>
    </div>

    <x-slot:sidebar>
        <div class="flex flex-col h-full">
            <div class="p-4 border-b border-gray-200 bg-white">
                <span class="font-medium text-gray-900">Designer Studio</span>
            </div>
        </div>
    </x-slot:sidebar>

    {{-- Onboarding Modal --}}
    <div
        x-data="{
            step: 1,
            selectedTemplate: null,
            applying: false,

            async apply() {
                if (!this.selectedTemplate || this.applying) return;

                this.applying = true;
                try {
                    const response = await fetch('{{ route('studio.api.onboarding.apply') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ template: this.selectedTemplate }),
                    });
                    const data = await response.json();
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                    }
                } catch (e) {
                    console.error(e);
                    this.applying = false;
                }
            }
        }"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="onboarding-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-center min-h-screen px-4">
            {{-- Backdrop --}}
            <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);"></div>

            {{-- Modal --}}
            <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-8">
                {{-- Step 1: Welcome --}}
                <div x-show="step === 1">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-blue-100 mb-5">
                            <svg class="h-7 w-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                            </svg>
                        </div>
                        <h2 id="onboarding-title" class="text-2xl font-bold text-gray-900">
                            Welcome to Designer
                        </h2>
                        <p class="mt-3 text-gray-600 leading-relaxed">
                            Build beautiful pages visually with a drag-and-drop editor. Choose a starting template and you'll be up and running in seconds.
                        </p>
                    </div>
                    <div class="mt-8">
                        <x-katana.button
                            @click="step = 2"
                            size="lg"
                            class="w-full"
                        >
                            Next
                        </x-katana.button>
                    </div>
                </div>

                {{-- Step 2: Template Selection --}}
                <div x-show="step === 2" x-cloak>
                    <div class="text-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">
                            Choose a Template
                        </h2>
                        <p class="mt-2 text-gray-600">
                            Pick a starting point for your first page.
                        </p>
                    </div>

                    <div class="space-y-3">
                        @foreach($templates as $key => $template)
                            <button
                                @click="selectedTemplate = '{{ $key }}'"
                                :class="selectedTemplate === '{{ $key }}'
                                    ? 'border-blue-500 ring-2 ring-blue-500 bg-blue-50'
                                    : 'border-gray-200 hover:border-gray-300 bg-white'"
                                class="w-full text-left p-4 rounded-lg border-2 transition-all"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-semibold text-gray-900">{{ $template['title'] }}</h3>
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $template['description'] }}</p>
                                    </div>
                                    <div
                                        x-show="selectedTemplate === '{{ $key }}'"
                                        class="flex-shrink-0 ml-3"
                                    >
                                        <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                        </svg>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-8 flex gap-3">
                        <x-katana.button
                            variant="outline"
                            @click="step = 1"
                            size="lg"
                            class="flex-1"
                        >
                            Back
                        </x-katana.button>
                        <x-katana.button
                            @click="apply()"
                            x-bind:disabled="!selectedTemplate || applying"
                            size="lg"
                            class="flex-1"
                        >
                            <span x-show="!applying">Get Started</span>
                            <span x-show="applying">Setting up...</span>
                        </x-katana.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-studio::layouts.app>
