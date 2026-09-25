{{--
    Shared input for repeater sub-fields.
    Receives: $sectionId, $key, $subKey, $subConfig, $currentValue, $method
    ($method is a Livewire expression string ending in `$event.target.value`)
--}}
@php $subType = $subConfig['type'] ?? 'text'; @endphp

<div>
    <label class="mb-1 block text-[11px] font-medium text-faint">{{ $subConfig['label'] ?? \Illuminate\Support\Str::headline($subKey) }}</label>

    @if($subType === 'textarea')
        <textarea
            rows="{{ $subConfig['rows'] ?? 2 }}"
            class="s-input !min-h-0 !text-xs"
            x-on:input.debounce.400ms="$wire.{!! $method !!}"
        >{{ $currentValue }}</textarea>
    @elseif($subType === 'select')
        <select
            class="s-input !text-xs"
            x-on:change="$wire.{!! $method !!}"
        >
            @foreach($subConfig['options'] ?? [] as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $optionValue === (string) $currentValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @elseif($subType === 'image')
        <div
            class="flex gap-1.5"
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
                        this.$refs.urlInput.value = url;
                        this.$refs.urlInput.dispatchEvent(new Event('input', { bubbles: true }));
                    } catch (e) {
                        window.Studio.toast(e.message || 'Upload failed', 'error');
                    }
                    this.uploading = false;
                }
            }"
        >
            <input
                x-ref="urlInput"
                type="text"
                class="s-input !font-mono !text-[11px]"
                placeholder="https://…"
                value="{{ $currentValue }}"
                x-on:input.debounce.400ms="$wire.{!! $method !!}"
            >
            <button
                type="button"
                class="s-btn-outline h-8 shrink-0 !px-2"
                title="Choose from the media library"
                x-on:click="window.Studio.mediaPick().then((url) => { if (!url) return; $refs.urlInput.value = url; $refs.urlInput.dispatchEvent(new Event('input', { bubbles: true })); })"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06l-6.97-6.97a.75.75 0 0 0-1.06 0l-3.69 3.69v.001ZM12 7a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z" clip-rule="evenodd"/></svg>
            </button>
            <label class="s-btn-outline relative h-8 shrink-0 cursor-pointer !px-2" :class="uploading && 'pointer-events-none opacity-60'" title="Upload an image">
                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/avif" class="sr-only" x-on:change="pick($event)">
                <svg x-show="!uploading" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.636l2.955 3.129a.75.75 0 0 0 1.09-1.03l-4.25-4.5a.75.75 0 0 0-1.09 0l-4.25 4.5a.75.75 0 1 0 1.09 1.03L9.25 4.636v8.614Z" clip-rule="evenodd"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                <svg x-show="uploading" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
            </label>
        </div>
    @else
        <input
            type="text"
            class="s-input !text-xs {{ $subType === 'url' ? '!font-mono !text-[11px]' : '' }}"
            value="{{ $currentValue }}"
            x-on:input.debounce.400ms="$wire.{!! $method !!}"
        >
    @endif
</div>
