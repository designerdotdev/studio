{{-- The page pill: [ ⟳ | page ▾ ]. Refresh inside-left, the page name +
     chevron as the trigger, a dark dropdown listing every page (the home
     page first) with New page… and Manage pages… under it. --}}
<div
    class="relative"
    x-data="{
        open: false,
        pages: window.__studioPageList || [],
        get current() { return this.pages.find((p) => p.current) || { title: 'Page', path: '/' } },
        go(slug) { window.location.href = window.__studioEditorUrl + '?page=' + encodeURIComponent(slug) },
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    @studio:open-page-menu.window="open = true"
>
    <div class="s-page-pill" :class="open && 'is-open'">
        <button
            type="button"
            class="s-page-pill-refresh"
            title="Refresh the preview"
            aria-label="Refresh the preview"
            x-data="{ spinning: false }"
            @click.stop="spinning = true; window.dispatchEvent(new CustomEvent('studio:refresh-preview')); setTimeout(() => spinning = false, 900)"
        >
            <svg class="h-3.5 w-3.5" :class="spinning && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
        </button>
        <button
            type="button"
            class="s-page-pill-trigger"
            @click="open = !open"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Switch page"
        >
            <span class="truncate" x-text="current.title"></span>
            <svg class="h-3 w-3 shrink-0 transition-transform duration-150" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
        </button>
        <span class="h-6 w-6 shrink-0" aria-hidden="true"></span>
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-150 ease-[cubic-bezier(.21,1.02,.47,1)]"
        x-transition:enter-start="-translate-y-1 scale-[0.98] opacity-0"
        x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 scale-[0.99]"
        class="s-pop absolute left-1/2 top-full z-50 mt-1.5 w-72 -translate-x-1/2"
        role="menu"
    >
        <template x-for="p in pages" :key="p.slug">
            <div class="s-page-row group/row" :class="p.current && 'is-current'">
                <button type="button" class="s-page-row-main" role="menuitem" @click="open = false; if (!p.current) go(p.slug)">
                    <svg x-show="p.current" class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                    <span x-show="!p.current" class="w-3 shrink-0"></span>
                    <span class="min-w-0 flex-1 truncate" x-text="p.title"></span>
                    <span class="shrink-0 font-mono text-[10.5px] text-faint" x-text="p.path"></span>
                </button>
                <button x-show="p.current" type="button" class="s-page-row-action" title="Page settings" aria-label="Page settings" @click.stop="open = false; $store.studio.openPageSettings()">
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.929 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.992 6.992 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd"/></svg>
                </button>
            </div>
        </template>

        <div class="s-pop-divider"></div>

        <button type="button" class="s-menu-item" role="menuitem" @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
            New page…
        </button>
        <button type="button" class="s-menu-item" role="menuitem" @click="open = false; $store.studio.setRail('pages', true)">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
            Manage pages…
        </button>
    </div>
</div>
