<label class="s-label">
    {{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}
</label>

<div
    x-data="{
        uploading: false,

        async pick(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;

            this.uploading = true;
            try {
                const url = await window.Studio.upload(file, {
                    url: @js(route('studio.api.upload')),
                    csrf: document.querySelector('meta[name=csrf-token]').content,
                });
                preview('{{ $sectionId }}', '{{ $key }}', url);
                await $wire.setVariable('{{ $sectionId }}', '{{ $key }}', url);
            } catch (e) {
                window.Studio.toast(e.message || 'Upload failed', 'error');
            }
            this.uploading = false;
        }
    }"
>
    @if($value)
        <div class="group relative mb-2 overflow-hidden rounded-lg border border-line bg-shell">
            <img src="{{ $value }}" alt="" class="block h-24 w-full object-cover">
            <button
                type="button"
                class="absolute right-1.5 top-1.5 flex h-6 w-6 cursor-pointer items-center justify-center rounded-md bg-black/60 text-white/80 opacity-0 backdrop-blur transition-all hover:bg-black/80 hover:text-white group-hover:opacity-100"
                title="Remove image"
                x-on:click="preview('{{ $sectionId }}', '{{ $key }}', ''); $wire.setVariable('{{ $sectionId }}', '{{ $key }}', '')"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
            </button>
        </div>
    @endif

    <div class="flex gap-1.5">
        <input
            type="text"
            class="s-input !font-mono !text-xs"
            placeholder="https://… or upload"
            wire:model.blur="variables.{{ $sectionId }}.{{ $key }}"
            x-on:input="preview('{{ $sectionId }}', '{{ $key }}', $event.target.value)"
        >
        <label class="s-btn-outline relative shrink-0 cursor-pointer !px-2.5" :class="uploading && 'pointer-events-none opacity-60'" title="Upload an image">
            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" class="sr-only" x-on:change="pick($event)">
            <svg x-show="!uploading" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.636l2.955 3.129a.75.75 0 0 0 1.09-1.03l-4.25-4.5a.75.75 0 0 0-1.09 0l-4.25 4.5a.75.75 0 1 0 1.09 1.03L9.25 4.636v8.614Z" clip-rule="evenodd"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
            <svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
        </label>
    </div>
</div>
