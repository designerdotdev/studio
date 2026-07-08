<x-studio::layouts.app>
    <x-slot:title>Welcome — Designer Studio</x-slot:title>

    <style>
        @keyframes onboard-up {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .onboard-up {
            opacity: 0;
            animation: onboard-up 600ms cubic-bezier(0.21, 1.02, 0.73, 1) forwards;
        }
    </style>

    <div
        class="s-canvas relative h-full w-full overflow-y-auto"
        x-data="{
            step: 1,
            selected: 'starter',
            applying: false,

            showTemplates() {
                this.step = 2;
                this.$nextTick(() => {
                    this.$root.querySelectorAll('[data-template-preview]').forEach((el) => {
                        const frame = el.querySelector('iframe');
                        if (!frame) return;
                        const scale = el.offsetWidth / 1280;
                        frame.style.transform = `scale(${scale})`;
                        frame.style.height = `${Math.ceil(el.offsetHeight / scale)}px`;
                    });
                });
            },

            async apply() {
                if (!this.selected || this.applying) return;
                this.applying = true;
                try {
                    const response = await fetch(@js(route('studio.api.onboarding.apply')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ template: this.selected }),
                    });
                    const data = await response.json();
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                } catch (e) {
                    window.Studio?.toast('Something went wrong — please try again', 'error');
                }
                this.applying = false;
            }
        }"
    >
        {{-- Step 1 — Welcome --}}
        <div x-show="step === 1" class="flex min-h-full flex-col items-center justify-center px-6 py-16 text-center">
            <div class="onboard-up flex h-16 w-16 items-center justify-center rounded-2xl bg-white/[0.04] ring-1 ring-white/10" style="animation-delay: 60ms">
                <svg class="h-8 w-auto text-neutral-100" viewBox="0 0 72 75" fill="none">
                    <path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/>
                </svg>
            </div>

            <h1 class="onboard-up mt-8 text-4xl font-semibold tracking-tight text-ink" style="animation-delay: 140ms">
                Welcome to Designer Studio
            </h1>
            <p class="onboard-up mt-4 max-w-md text-[15px] leading-relaxed text-soft" style="animation-delay: 220ms">
                The visual editor for your Laravel site. Developers define the sections — anyone on the team edits the pages.
            </p>

            <div class="onboard-up mt-10" style="animation-delay: 300ms">
                <button @click="showTemplates()" class="s-btn-primary !h-11 !px-7 !text-sm">
                    Choose a template
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
                </button>
            </div>

            <p class="onboard-up mt-16 text-xs text-faint" style="animation-delay: 380ms">
                Everything can be changed later — templates are just a starting point.
            </p>
        </div>

        {{-- Step 2 — Template picker --}}
        <div x-show="step === 2" x-cloak class="mx-auto w-full max-w-6xl px-6 py-12 lg:py-16">
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="s-microlabel">Step 2 of 2</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">Pick a starting point</h1>
                    <p class="mt-1.5 text-[13.5px] text-soft">Live previews, built from the section library. You can add, remove, and rewrite everything.</p>
                </div>
                <button @click="step = 1" class="s-btn-ghost shrink-0">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.78 5.22a.75.75 0 0 1 0 1.06L9.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                    Back
                </button>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($templates as $key => $template)
                    <button
                        type="button"
                        @click="selected = @js($key)"
                        @dblclick="selected = @js($key); apply()"
                        class="group relative flex flex-col overflow-hidden rounded-2xl border bg-raised text-left transition-all duration-150"
                        :class="selected === @js($key)
                            ? 'border-accent shadow-[0_0_0_3px_color-mix(in_srgb,#4c7dfa_25%,transparent)] -translate-y-0.5'
                            : 'border-line hover:border-line-strong hover:-translate-y-0.5'"
                    >
                        {{-- Preview --}}
                        <span
                            data-template-preview
                            class="pointer-events-none relative block w-full overflow-hidden bg-white"
                            style="aspect-ratio: 16/11"
                        >
                            @if(($template['pages'][0]['components'] ?? []) === [])
                                <span class="absolute inset-0 flex items-center justify-center bg-[#fafafa]">
                                    <span class="flex h-full w-full items-center justify-center" style="background-image: radial-gradient(circle, rgba(0,0,0,0.06) 1px, transparent 1px); background-size: 18px 18px;">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-xl border-2 border-dashed border-neutral-300 text-neutral-300">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                                        </span>
                                    </span>
                                </span>
                            @else
                                <iframe
                                    src="{{ route('studio.preview.template', ['name' => $key]) }}"
                                    loading="lazy"
                                    tabindex="-1"
                                    title="{{ $template['title'] }} preview"
                                    class="absolute left-0 top-0 origin-top-left border-0 bg-white"
                                    style="width: 1280px; height: 880px; transform: scale(0.264)"
                                ></iframe>
                                <span class="absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-black/5 to-transparent"></span>
                            @endif

                            {{-- Selected check --}}
                            <span
                                x-show="selected === @js($key)"
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-75"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-2.5 top-2.5 flex h-6 w-6 items-center justify-center rounded-full bg-accent text-white shadow-lg"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                            </span>
                        </span>

                        {{-- Meta --}}
                        <span class="flex flex-1 flex-col border-t border-line p-4">
                            <span class="flex items-center gap-2">
                                <span class="text-[13.5px] font-semibold text-ink">{{ $template['title'] }}</span>
                                @if(count($template['pages']) > 1)
                                    <span class="s-chip">{{ count($template['pages']) }} pages</span>
                                @endif
                            </span>
                            <span class="mt-1 text-xs leading-relaxed text-soft">{{ $template['description'] }}</span>
                        </span>
                    </button>
                @endforeach
            </div>

        </div>

        {{-- Step 2 — pinned action bar (always visible while browsing templates) --}}
        <div
            x-show="step === 2"
            x-cloak
            class="sticky bottom-0 z-20 border-t border-line bg-shell/85 shadow-[0_-16px_40px_-16px_rgba(0,0,0,0.65)] backdrop-blur-xl"
        >
            <div class="mx-auto flex w-full max-w-6xl items-center gap-4 px-6 py-3.5">
                <p class="hidden text-xs text-faint sm:block">Tip: double-click a template to jump straight in.</p>

                <div class="ml-auto flex items-center gap-3">
                    <p class="text-[13px] text-soft">
                        <span class="text-faint">Selected:</span>
                        <span class="font-medium text-ink" x-text="({ @foreach($templates as $key => $template)@js($key): @js($template['title']),@endforeach })[selected] ?? '—'"></span>
                    </p>
                    <button
                        @click="apply()"
                        :disabled="!selected || applying"
                        class="s-btn-primary !h-10 !px-6"
                    >
                        <span x-show="!applying">Use this template</span>
                        <span x-show="applying" x-cloak class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                            Setting up your site…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-studio::layouts.app>
