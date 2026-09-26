{{-- The top bar: the editor's one piece of chrome above the site. Left,
     the brand mark (the menu), the sidebar toggle, the page switcher (a
     dark dropdown) and the open-in-new-tab link; centre, Preview / Edit /
     Code; right, the device button (one control cycling Desktop → Tablet
     → Phone), the Assistant (developer mode) and Publish. The `menu` and
     `actions` slots come from home.blade.php. --}}
<header class="s-topbar" aria-label="Editor">
    <div class="s-topbar-group is-start">
        {{ $menu ?? '' }}

        {{-- Sidebar toggle — the inner bar previews the action on hover --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            :class="$store.studio.sidebar ? 'tgl-collapse' : 'tgl-expand'"
            :data-tip="$store.studio.sidebar ? 'Collapse sidebar  ⌘B' : 'Open sidebar  ⌘B'"
            aria-label="Toggle the sidebar"
            :aria-expanded="$store.studio.sidebar"
            @click="$store.studio.toggleSidebar()"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4.5498 2.30001C3.68785 2.30001 2.8612 2.64242 2.25171 3.25191C1.64221 3.8614 1.2998 4.68805 1.2998 5.55001C1.2998 6.41196 1.3 10.45 1.3 10.45C1.3 10.8768 1.38406 11.2994 1.54739 11.6937C1.71072 12.088 1.95011 12.4463 2.2519 12.7481C2.8614 13.3576 3.68805 13.7 4.55 13.7L11.4498 13.7C11.8766 13.7 12.2992 13.6159 12.6935 13.4526C13.0878 13.2893 13.4461 13.0499 13.7479 12.7481C14.0497 12.4463 14.2891 12.088 14.4524 11.6937C14.6157 11.2994 14.6998 10.8768 14.6998 10.45C14.6998 8.30212 14.6998 7.69789 14.6998 5.55C14.6998 5.12321 14.6157 4.70059 14.4524 4.30628C14.2891 3.91197 14.0497 3.5537 13.7479 3.25191C13.4461 2.95012 13.0878 2.71072 12.6935 2.54739C12.2992 2.38407 11.8766 2.3 11.4498 2.3L4.5498 2.30001ZM2.4998 5.50001C2.4998 4.96957 2.71052 4.46087 3.08559 4.08579C3.46066 3.71072 3.96937 3.50001 4.4998 3.50001H11.4998C12.0302 3.50001 12.5389 3.71072 12.914 4.08579C13.2891 4.46087 13.4998 4.96957 13.4998 5.50001V10.5C13.4998 11.0304 13.2891 11.5391 12.914 11.9142C12.5389 12.2893 12.0302 12.5 11.4998 12.5H4.4998C3.96937 12.5 3.46066 12.2893 3.08559 11.9142C2.71052 11.5391 2.4998 11.0304 2.4998 10.5V5.50001Z"/>
                <rect class="sidebar-toggle-bar" x="3.9" y="5" width="4.5" height="6" rx="0.75"/>
            </svg>
        </button>

        <span class="s-tb-sep"></span>

        {{-- The page switcher --}}
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
                x-transition:enter="transition duration-150 ease-[cubic-bezier(.21,1.02,.47,1)]"
                x-transition:enter-start="-translate-y-1 scale-[0.97] opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-pop absolute left-0 top-full z-50 mt-1.5 w-72"
                role="menu"
            >
                <template x-for="p in pages" :key="p.slug">
                    <div class="s-page-row" :class="p.current && 'is-current'">
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
    </div>

    {{-- Edit / Preview / Code --}}
    <div class="s-topbar-group is-center">
        <div class="s-mode" role="radiogroup" aria-label="Mode">
            <button type="button" class="s-mode-btn" :class="$store.studio.mode === 'preview' && 'is-active'" @click="$store.studio.setMode('preview')" title="Preview — browse the site as a visitor" role="radio" :aria-checked="$store.studio.mode === 'preview'">
                <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                Preview
            </button>
            <button type="button" class="s-mode-btn" :class="$store.studio.mode === 'edit' && 'is-active'" @click="$store.studio.setMode('edit')" title="Edit — click anything on the page to change it" role="radio" :aria-checked="$store.studio.mode === 'edit'">
                <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M4 3.5 19 11l-6.5 1.8L9 19 4 3.5Z"/></svg>
                Edit
            </button>
            <button x-show="$store.studio.codeAvailable" x-cloak type="button" class="s-mode-btn is-code" :class="$store.studio.mode === 'code' && 'is-active'" @click="$store.studio.setMode('code')" title="Code — edit the site's source files" role="radio" :aria-checked="$store.studio.mode === 'code'">
                <svg class="h-[14px] w-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 7.5 4 12l4.5 4.5M15.5 7.5 20 12l-4.5 4.5"/></svg>
                Code
            </button>
        </div>
    </div>

    <div class="s-topbar-group is-end">
        {{-- One responsive button: a click cycles Desktop → Tablet → Phone.
             The icon is the current device; the tooltip names the next one. --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            :class="$store.studio.device !== 'desktop' && 'is-accent'"
            x-show="$store.studio.canvasVisible"
            :data-tip="({ desktop: 'Desktop — switch to Tablet  ⌥2', tablet: 'Tablet · 768px — switch to Phone  ⌥3', mobile: 'Phone · 390px — switch to Desktop  ⌥1' })[$store.studio.device]"
            :aria-label="'Preview size: ' + $store.studio.device"
            @click="$store.studio.cycleDevice()"
        >
            <svg x-show="$store.studio.device === 'desktop'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
            <svg x-show="$store.studio.device === 'tablet'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            <svg x-show="$store.studio.device === 'mobile'" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3"/></svg>
        </button>

        {{-- Code mode: bring the live preview back beside the editor --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            x-show="$store.studio.mode === 'code'"
            x-cloak
            :class="$store.studio.codeSplit && 'is-active'"
            :data-tip="$store.studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code'"
            aria-label="Toggle the preview beside the code"
            @click="$store.studio.toggleCodeSplit()"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><rect x="3" y="4.5" width="18" height="15" rx="2.5"/><path stroke-linecap="round" d="M3 9h18M6.2 6.75h.01M8.7 6.75h.01M11.2 6.75h.01"/></svg>
        </button>

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
