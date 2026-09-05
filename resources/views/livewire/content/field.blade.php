{{--
    One collection field on the entry form. Bound to $row.<key> on ContentPanel.
    Receives: $key, $config
--}}
@php
    $type = $config['type'] ?? 'text';
    $label = $config['label'] ?? \Illuminate\Support\Str::headline($key);
    $model = 'row.' . $key;
@endphp

@if($type === 'toggle')
    <div class="flex items-center justify-between gap-3">
        <span class="s-label !mb-0">{{ $label }}</span>
        <input type="checkbox" class="h-4 w-4 accent-[var(--color-accent)]" wire:model="{{ $model }}">
    </div>
@else
    <label class="s-label">{{ $label }}</label>

    @if($type === 'textarea')
        <textarea rows="4" class="s-input !min-h-0" wire:model="{{ $model }}"></textarea>

    @elseif($type === 'richtext')
        <div
            class="s-richtext"
            x-data="{
                exec(cmd, arg = null) { document.execCommand(cmd, false, arg); this.$refs.editor.focus(); this.sync(); },
                block(tag) { document.execCommand('formatBlock', false, tag); this.$refs.editor.focus(); this.sync(); },
                link() {
                    const sel = window.getSelection();
                    if (!sel || sel.isCollapsed) { window.Studio.toast('Select some text to link first', 'info'); return; }
                    this.linkRange = sel.getRangeAt(0); this.linkOpen = true; this.linkUrl = '';
                    this.$nextTick(() => this.$refs.linkInput.focus());
                },
                applyLink() {
                    if (this.linkRange) { const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(this.linkRange); }
                    if (this.linkUrl) document.execCommand('createLink', false, this.linkUrl); else document.execCommand('unlink');
                    this.linkOpen = false; this.sync();
                },
                linkOpen: false, linkUrl: '', linkRange: null,
                sync() { $wire.set(@js($model), this.$refs.editor.innerHTML, false); },
            }"
            x-init="$refs.editor.innerHTML = $wire.get(@js($model)) || ''"
        >
            <div class="s-richtext-bar">
                <button type="button" title="Bold" @click="exec('bold')"><strong>B</strong></button>
                <button type="button" title="Italic" @click="exec('italic')"><em>I</em></button>
                <span class="s-richtext-sep"></span>
                <button type="button" title="Paragraph" @click="block('p')">¶</button>
                <button type="button" title="Heading 2" @click="block('h2')">H2</button>
                <button type="button" title="Heading 3" @click="block('h3')">H3</button>
                <span class="s-richtext-sep"></span>
                <button type="button" title="Bulleted list" @click="exec('insertUnorderedList')">•</button>
                <button type="button" title="Numbered list" @click="exec('insertOrderedList')">1.</button>
                <button type="button" title="Quote" @click="block('blockquote')">❝</button>
                <button type="button" title="Link" @click="link()">🔗</button>
            </div>
            <div x-show="linkOpen" x-cloak class="flex gap-1.5 border-b border-line p-1.5">
                <input type="url" class="s-input !h-7 !font-mono !text-[11px]" placeholder="https://…" x-ref="linkInput" x-model="linkUrl" @keydown.enter.prevent="applyLink()" @keydown.escape="linkOpen = false">
                <button type="button" class="s-btn-primary !h-7 !px-2.5 !text-[11px]" @click="applyLink()">Link</button>
            </div>
            <div
                class="s-richtext-body"
                contenteditable="true"
                x-ref="editor"
                @input.debounce.400ms="sync()"
                @blur="sync()"
            ></div>
        </div>

    @elseif($type === 'image')
        <div x-data="{ uploading: false }">
            <template x-if="$wire.get(@js($model))">
                <img :src="$wire.get(@js($model))" alt="" class="mb-2 block h-24 w-full rounded-lg border border-line object-cover">
            </template>
            <div class="flex gap-1.5">
                <input type="text" class="s-input !font-mono !text-xs" placeholder="https://… or pick" wire:model.blur="{{ $model }}">
                <button type="button" class="s-btn-outline shrink-0 !px-2.5" title="Choose from the media library" @click="window.Studio.mediaPick().then((url) => { if (url) $wire.set(@js($model), url) })">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 0 1 3.25 3h13.5A2.25 2.25 0 0 1 19 5.25v9.5A2.25 2.25 0 0 1 16.75 17H3.25A2.25 2.25 0 0 1 1 14.75v-9.5Zm1.5 5.81v3.69c0 .414.336.75.75.75h13.5a.75.75 0 0 0 .75-.75v-2.69l-2.22-2.219a.75.75 0 0 0-1.06 0l-1.91 1.909.47.47a.75.75 0 1 1-1.06 1.06l-6.97-6.97a.75.75 0 0 0-1.06 0l-3.69 3.69v.001ZM12 7a1 1 0 1 1 2 0 1 1 0 0 1-2 0Z" clip-rule="evenodd"/></svg>
                </button>
                <label class="s-btn-outline relative shrink-0 cursor-pointer !px-2.5" :class="uploading && 'pointer-events-none opacity-60'" title="Upload an image">
                    <input type="file" accept="image/*" class="sr-only" @change="
                        const file = $event.target.files[0]; $event.target.value = ''; if (!file) return;
                        uploading = true;
                        window.Studio.upload(file, { url: @js(route('studio.api.upload')), csrf: document.querySelector('meta[name=csrf-token]').content })
                            .then((url) => $wire.set(@js($model), url))
                            .catch((e) => window.Studio.toast(e.message || 'Upload failed', 'error'))
                            .finally(() => uploading = false);
                    ">
                    <svg x-show="!uploading" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.25 13.25a.75.75 0 0 0 1.5 0V4.636l2.955 3.129a.75.75 0 0 0 1.09-1.03l-4.25-4.5a.75.75 0 0 0-1.09 0l-4.25 4.5a.75.75 0 1 0 1.09 1.03L9.25 4.636v8.614Z" clip-rule="evenodd"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                    <svg x-show="uploading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                </label>
            </div>
        </div>

    @elseif($type === 'select')
        <select class="s-input" wire:model="{{ $model }}">
            <option value="">—</option>
            @foreach($config['options'] ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>

    @elseif($type === 'number')
        <input type="number" class="s-input" wire:model="{{ $model }}">

    @elseif($type === 'url')
        <input type="text" class="s-input !font-mono !text-xs" placeholder="https://… or /page" wire:model="{{ $model }}">

    @else
        <input type="text" class="s-input" wire:model="{{ $model }}">
    @endif
@endif
