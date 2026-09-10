{{-- Code mode's editor pane: open files as tabs above one Monaco instance
     whose model is swapped per tab, so each file keeps its own undo history
     and cursor. Full-width by default; the split hands half back to the
     live preview. All state lives in $store.code (see the dev-mode script
     block in home.blade.php) — the file tree in the sidebar drives it. --}}
<div
    x-show="$store.studio.mode === 'code'"
    x-cloak
    class="s-frame min-h-0 min-w-0 bg-panel"
    :class="$store.studio.codeSplit ? 'shrink-0' : 'flex-1'"
    :style="$store.studio.codeSplit ? { width: $store.studio.codeSize + '%' } : { width: '' }"
    {{-- Register the Monaco host without loading Monaco: the bundle is
         fetched on the first file open, not on entering Code mode. --}}
    x-init="
        $nextTick(() => {
            $store.code.host = $refs.host;
            if ($store.studio.mode === 'code') $store.code.boot();
        });
        $watch(() => $store.studio.mode, (mode) => { if (mode === 'code') $store.code.boot() });
    "
    @keydown.window="if ($store.studio.mode === 'code' && ($event.metaKey || $event.ctrlKey) && ($event.key === 's' || $event.key === 'S') && !window.Studio.codeModalOpen) { $event.preventDefault(); $store.code.save() }"
>
    {{-- Tab strip --}}
    <div class="flex h-9 shrink-0 items-stretch gap-px overflow-x-auto border-b border-line bg-raised/50">
        <template x-for="tab in $store.code.tabs" :key="tab.path">
            <div
                class="group flex shrink-0 cursor-pointer items-center gap-1.5 border-r border-line px-3 text-[11.5px] transition-colors"
                :class="$store.code.active === tab.path ? 'bg-panel text-ink' : 'text-faint hover:text-soft'"
                @click="$store.code.activate(tab.path)"
                :title="tab.display"
            >
                <span class="font-mono" x-text="tab.name"></span>
                <span x-show="$store.code.dirty[tab.path]" x-cloak class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                <button
                    type="button"
                    class="-mr-1 flex h-4 w-4 items-center justify-center rounded opacity-0 transition-opacity hover:bg-wash group-hover:opacity-100"
                    @click.stop="$store.code.closeTab(tab.path)"
                    :aria-label="`Close ${tab.name}`"
                >
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                </button>
            </div>
        </template>

        <div class="flex flex-1 items-center justify-end gap-2 px-2.5">
            <span x-show="$store.code.saving" x-cloak class="text-[11px] text-faint">Saving…</span>
            <button
                x-show="$store.code.active"
                x-cloak
                type="button"
                class="s-btn-accent !h-6 !px-2 !text-[11px]"
                @click="$store.code.save()"
                :disabled="$store.code.saving || !$store.code.dirty[$store.code.active]"
            >
                Save
                <span class="s-kbd !border-line-strong !bg-transparent !text-white/80">⌘S</span>
            </button>
        </div>
    </div>

    {{-- Editor --}}
    <div class="relative min-h-0 flex-1">
        <div x-ref="host" class="s-code-pane absolute inset-0" x-show="$store.code.active"></div>

        {{-- Empty state --}}
        <div x-show="!$store.code.active" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-8 text-center">
            <svg class="h-7 w-7 text-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 7.5 4 12l4.5 4.5M15.5 7.5 20 12l-4.5 4.5"/></svg>
            <p class="text-[13px] font-medium text-soft">Pick a file to edit</p>
            <p class="max-w-xs text-[12px] leading-relaxed text-faint">
                Section sources, imported template components, and the site's own theme CSS and head markup. Saving a section updates every page that uses it.
            </p>
        </div>

        {{-- The error line sits over the editor so a failed save can't be missed --}}
        <div x-show="$store.code.error" x-cloak class="absolute inset-x-0 bottom-0 border-t border-danger/40 bg-danger/15 px-3 py-2">
            <p class="text-[11.5px] leading-snug text-ink/90" x-text="$store.code.error"></p>
        </div>
    </div>
</div>
