{{-- The top bar: the editor's one piece of chrome above the site. Left,
     the sidebar toggle and the brand mark (the menu); centre, the page
     switcher; right, the canvas widths, Preview / Edit / Code, the
     open-in-new-tab link, the Assistant (developer mode) and Publish.
     The `menu` and `actions` slots come from home.blade.php. --}}
<header class="s-topbar" aria-label="Editor">
    <div class="s-topbar-group is-start">
        <button
            type="button"
            class="s-tb-btn s-tip"
            :class="$store.studio.sidebar && 'is-active'"
            data-tip="Toggle sidebar  ⌘B"
            aria-label="Toggle the sidebar"
            :aria-pressed="$store.studio.sidebar"
            @click="$store.studio.toggleSidebar()"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="15" rx="2.5"/><path d="M9.5 4.5v15"/></svg>
        </button>

        {{ $menu ?? '' }}
    </div>

    {{-- The page switcher, centred --}}
    <div class="s-topbar-group is-center">
        <div
            class="relative min-w-0"
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
            <button
                type="button"
                class="s-page-btn"
                :class="open && 'is-open'"
                @click="open = !open"
                x-data="{ state: 'idle' }"
                @studio:status.window="state = $event.detail.state"
                :title="state === 'saving' ? 'Saving…' : state === 'error' ? 'Offline — changes are not being saved' : 'All changes saved'"
                aria-haspopup="menu"
                :aria-expanded="open"
            >
                <span class="s-status-pip" :class="{ 'is-saving': state === 'saving', 'is-error': state === 'error' }"></span>
                <span class="truncate font-medium text-ink" x-text="current.title"></span>
                <span class="truncate font-mono text-[11px] text-faint" x-text="current.path"></span>
                <svg class="h-3 w-3 shrink-0 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
            </button>
            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="s-pop absolute left-1/2 top-full z-50 mt-1.5 w-64 -translate-x-1/2 p-1"
                role="menu"
            >
                <p class="s-microlabel px-2.5 pb-1 pt-1.5">Pages</p>
                <div class="max-h-72 overflow-y-auto">
                    <template x-for="p in pages" :key="p.slug">
                        <button type="button" class="s-menu-item" :class="p.current && 'bg-wash !text-ink'" role="menuitem" @click="open = false; if (!p.current) go(p.slug)">
                            <span class="min-w-0 flex-1 truncate" x-text="p.title"></span>
                            <span class="shrink-0 font-mono text-[10.5px] text-faint" x-text="p.path"></span>
                        </button>
                    </template>
                </div>
                <div class="s-divider my-1"></div>
                <button type="button" class="s-menu-item" role="menuitem" @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    New page…
                </button>
                <button type="button" class="s-menu-item" role="menuitem" @click="open = false; $store.studio.openPageSettings()">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.929 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.992 6.992 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd"/></svg>
                    <span class="flex-1">Page settings…</span>
                    <span class="s-kbd">⌘,</span>
                </button>
                <button type="button" class="s-menu-item" role="menuitem" @click="open = false; $store.studio.setRail('pages', true)">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    Manage pages…
                </button>
            </div>
        </div>
    </div>

    <div class="s-topbar-group is-end">
        {{-- Canvas widths --}}
        <div class="s-widths" role="radiogroup" aria-label="Canvas width" x-show="$store.studio.canvasVisible">
            <button type="button" class="s-widths-btn" :class="$store.studio.device === 'desktop' && 'is-active'" @click="$store.studio.device = 'desktop'" title="Desktop (⌥1)" aria-label="Desktop width" role="radio" :aria-checked="$store.studio.device === 'desktop'">
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/></svg>
            </button>
            <button type="button" class="s-widths-btn" :class="$store.studio.device === 'tablet' && 'is-active'" @click="$store.studio.device = 'tablet'" title="Tablet — 768px (⌥2)" aria-label="Tablet width" role="radio" :aria-checked="$store.studio.device === 'tablet'">
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2.25"/><path d="M11 17.75h2"/></svg>
            </button>
            <button type="button" class="s-widths-btn" :class="$store.studio.device === 'mobile' && 'is-active'" @click="$store.studio.device = 'mobile'" title="Mobile — 390px (⌥3)" aria-label="Mobile width" role="radio" :aria-checked="$store.studio.device === 'mobile'">
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2.25"/><path d="M11 17.75h2"/></svg>
            </button>
        </div>

        <span class="s-tb-sep"></span>

        {{-- Preview / Edit / Code --}}
        <div class="s-mode" role="radiogroup" aria-label="Mode">
            <button type="button" class="s-mode-btn s-tip" :class="$store.studio.mode === 'preview' && 'is-active'" @click="$store.studio.setMode('preview')" data-tip="Preview — browse the site as a visitor" aria-label="Preview mode" role="radio" :aria-checked="$store.studio.mode === 'preview'">
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9.25"/><path d="M2.9 12h18.2M12 2.75c2.2 2.5 3.3 5.6 3.3 9.25S14.2 18.75 12 21.25C9.8 18.75 8.7 15.65 8.7 12S9.8 5.25 12 2.75Z"/></svg>
            </button>
            <button type="button" class="s-mode-btn s-tip" :class="$store.studio.mode === 'edit' && 'is-active'" @click="$store.studio.setMode('edit')" data-tip="Edit — click anything on the page to change it" aria-label="Edit mode" role="radio" :aria-checked="$store.studio.mode === 'edit'">
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M4 3.5 19 11l-6.5 1.8L9 19 4 3.5Z"/></svg>
            </button>
            <button x-show="$store.studio.codeAvailable" x-cloak type="button" class="s-mode-btn s-tip is-code" :class="$store.studio.mode === 'code' && 'is-active'" @click="$store.studio.setMode('code')" data-tip="Code — edit the site's source files" aria-label="Code mode" role="radio" :aria-checked="$store.studio.mode === 'code'">
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 7.5 4 12l4.5 4.5M15.5 7.5 20 12l-4.5 4.5"/></svg>
            </button>
        </div>

        {{-- Open the draft (or live) page in a new tab --}}
        @if($openUrl)
        <a
            href="{{ $openUrl }}"
            target="_blank"
            rel="noopener"
            class="s-tb-btn s-tip"
            data-tip="{{ $openLabel }}"
            aria-label="{{ $openLabel }}"
        >
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 13.5v5A1.5 1.5 0 0 1 16.5 20h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6h5"/>
                <path d="M14 4h6v6M20 4l-8.5 8.5"/>
            </svg>
        </a>
        @endif

        {{-- The Assistant — a column on the right, developer mode only --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            x-show="$store.studio.chatAvailable"
            x-cloak
            :class="{ 'is-active': $store.studio.assistantOpen, 'is-busy': $store.studio.chatBusy }"
            data-tip="Assistant  ⌘J"
            aria-label="Toggle the Assistant"
            :aria-pressed="$store.studio.assistantOpen"
            @click="$store.studio.toggleAssistant()"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
        </button>

        {{ $actions ?? '' }}
    </div>
</header>
