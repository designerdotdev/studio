<div class="flex h-full min-h-0 flex-col">
    {{-- Header --}}
    <div class="flex h-12 shrink-0 items-center gap-2 px-3">
        <p class="s-microlabel flex-1">Pages</p>
        <button
            type="button"
            class="s-icon-btn"
            title="New page"
            aria-label="New page"
            x-data
            @click="window.dispatchEvent(new CustomEvent('studio:open-create-page'))"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        </button>
    </div>

    {{-- Filter --}}
    <div class="shrink-0 border-b border-line px-3 py-2">
        <input
            type="search"
            class="s-input !h-7 !text-xs"
            placeholder="Filter pages…"
            wire:model.live.debounce.200ms="filter"
        >
    </div>

    {{-- List --}}
    <div class="min-h-0 flex-1 overflow-y-auto p-2">
        <div
            class="flex flex-col gap-0.5"
            x-data
            x-init="window.Studio?.sortable($el, '.s-page-handle', (slugs) => $wire.reorder(slugs))"
            wire:ignore.self
        >
            @forelse($this->rows as $row)
                <div
                    wire:key="page-{{ $row['slug'] }}"
                    data-section-id="{{ $row['slug'] }}"
                    class="s-section-row group {{ $row['current'] ? 'is-active' : '' }}"
                    x-data="{ menu: false }"
                >
                    {{-- Drag handle --}}
                    <span class="s-drag-handle s-page-handle group-hover:opacity-100" title="Drag to reorder">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm8-12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>
                    </span>

                    @if($renaming === $row['slug'])
                        <input
                            type="text"
                            class="s-input !h-7 min-w-0 flex-1 !text-xs"
                            wire:model="renameTitle"
                            wire:keydown.enter="saveRename"
                            wire:keydown.escape="cancelRename"
                            wire:blur="saveRename"
                            x-init="$nextTick(() => { $el.focus(); $el.select() })"
                        >
                    @else
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center gap-2 text-left"
                            wire:click="open('{{ $row['slug'] }}')"
                            title="Open {{ $row['title'] }}"
                        >
                            <span class="min-w-0 flex-1 truncate text-[12.5px] {{ $row['current'] ? 'font-medium text-ink' : 'text-ink/85' }}">{{ $row['title'] }}</span>
                            <span class="shrink-0 font-mono text-[10.5px] text-faint">{{ $row['path'] }}</span>
                            @if($row['home'])
                                <span class="s-chip shrink-0" title="Home page">Home</span>
                            @endif
                        </button>
                    @endif

                    {{-- Row actions --}}
                    <button
                        type="button"
                        class="s-icon-btn !h-6 !w-6 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                        wire:click="startRename('{{ $row['slug'] }}')"
                        title="Rename"
                        aria-label="Rename page"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z"/><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z"/></svg>
                    </button>
                    <div class="relative shrink-0" @click.outside="menu = false">
                        <button
                            type="button"
                            class="s-icon-btn !h-6 !w-6 opacity-0 transition-opacity group-hover:opacity-100"
                            :class="menu && '!opacity-100'"
                            @click="menu = !menu"
                            title="Page actions"
                            aria-label="Page actions"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>
                        </button>
                        <div
                            x-show="menu"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            class="s-pop absolute right-0 top-full z-30 mt-1 w-44 origin-top-right"
                            role="menu"
                        >
                            <button type="button" class="s-menu-item" role="menuitem" @click="menu = false" wire:click="open('{{ $row['slug'] }}')">Open</button>
                            <button type="button" class="s-menu-item" role="menuitem" @click="menu = false" wire:click="startRename('{{ $row['slug'] }}')">Rename</button>
                            <button type="button" class="s-menu-item" role="menuitem" @click="menu = false" wire:click="duplicate('{{ $row['slug'] }}')">Duplicate</button>
                            @unless($row['home'])
                                <button type="button" class="s-menu-item" role="menuitem" @click="menu = false" wire:click="setHome('{{ $row['slug'] }}')">Set as home</button>
                            @endunless
                            <div class="s-divider my-1"></div>
                            <button
                                type="button"
                                class="s-menu-item !text-danger"
                                role="menuitem"
                                @click="menu = false"
                                wire:click="delete('{{ $row['slug'] }}')"
                                wire:confirm="Delete “{{ $row['title'] }}”? This can't be undone."
                            >Delete</button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-2 py-6 text-center text-xs text-faint">No pages match.</p>
            @endforelse
        </div>
    </div>
</div>
