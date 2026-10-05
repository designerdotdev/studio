{{-- The Code view's editor pane: open files as tabs above one Monaco
     instance whose model is swapped per tab, so each file keeps its own undo
     history and cursor. The files are docked beside it (partials/file-tree)
     on the same surface — the editor's own background, so the whole view
     reads as one. Full-width by default; the split hands half back to the
     live preview. All state lives in $store.code (see the dev-mode script
     block in home.blade.php). --}}
<div
    x-show="$store.studio.view === 'code'"
    x-cloak
    class="s-frame s-code min-h-0 min-w-0"
    :class="$store.studio.codeSplit ? 'shrink-0' : 'flex-1'"
    :style="$store.studio.codeSplit ? { width: $store.studio.codeSize + '%' } : { width: '' }"
    {{-- Register the Monaco host without loading Monaco: the bundle is
         fetched on the first file open, not on entering the Code view. --}}
    x-init="
        $nextTick(() => {
            $store.code.host = $refs.host;
            if ($store.studio.view === 'code') $store.code.boot();
        });
        $watch(() => $store.studio.view, (view) => { if (view === 'code') $store.code.boot() });
    "
    @keydown.window="if ($store.studio.view === 'code' && ($event.metaKey || $event.ctrlKey) && ($event.key === 's' || $event.key === 'S')) { $event.preventDefault(); $store.code.save() }"
>
    {{-- Tab strip. Its hairline runs on from the files' header beside it. --}}
    <div class="s-code-tabs">
        {{-- The files, folded away: the way back --}}
        <button
            type="button"
            class="s-code-tabs-toggle"
            x-show="!$store.studio.docks.code"
            x-cloak
            @click="$store.studio.toggleDock('code')"
            title="Show the files (⌘B)"
            aria-label="Show the files"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4.75" width="18" height="14.5" rx="2.25"/><path d="M9 4.75v14.5"/></svg>
        </button>

        <div class="s-code-tabs-scroll" role="tablist" aria-label="Open files">
            <template x-for="tab in $store.code.tabs" :key="tab.path">
                <div
                    class="s-code-tab group"
                    role="tab"
                    tabindex="0"
                    :aria-selected="$store.code.active === tab.path"
                    :class="{ 'is-active': $store.code.active === tab.path, 'is-dirty': $store.code.dirty[tab.path] }"
                    @click="$store.code.activate(tab.path)"
                    @keydown.enter.self="$store.code.activate(tab.path)"
                    @keydown.space.self.prevent="$store.code.activate(tab.path)"
                    :title="tab.display"
                >
                    <span class="font-mono" x-text="tab.name"></span>
                    <span class="s-code-tab-end">
                        <span class="s-code-tab-dot"></span>
                        <button
                            type="button"
                            class="s-code-tab-close"
                            @click.stop="$store.code.closeTab(tab.path)"
                            :aria-label="`Close ${tab.name}`"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                        </button>
                    </span>
                </div>
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-2 pl-2 pr-2.5">
            {{-- Split the pane with the live preview --}}
            <button
                type="button"
                class="s-icon-btn"
                :class="$store.studio.codeSplit && '!bg-wash-strong !text-ink'"
                @click="$store.studio.toggleCodeSplit()"
                :title="$store.studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code'"
                :aria-label="$store.studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code'"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4.75" width="18" height="14.5" rx="2.25"/><path d="M12 4.75v14.5"/></svg>
            </button>
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
                The site lives in resources/designer and public/designer — pages, sections, layouts, data, and CSS. A saved file is live at once, and the editor picks it up.
            </p>
            <p class="mt-1 flex items-center gap-1.5 text-[11.5px] text-faint">
                <span class="s-kbd">⌘K</span> finds a file by name
            </p>
            {{-- The files can be folded away: offer the way back --}}
            <button
                type="button"
                class="s-btn-outline mt-2"
                x-show="!$store.studio.docks.code"
                x-cloak
                @click="$store.studio.toggleDock('code')"
            >
                Show the files
            </button>
        </div>

        {{-- The error line sits over the editor so a failed save can't be missed --}}
        <div x-show="$store.code.error" x-cloak class="absolute inset-x-0 bottom-0 border-t border-danger/40 bg-danger/15 px-3 py-2">
            <p class="text-[11.5px] leading-snug text-ink/90" x-text="$store.code.error"></p>
        </div>
    </div>
</div>
