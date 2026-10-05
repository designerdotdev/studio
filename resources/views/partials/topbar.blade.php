{{-- The top bar: the editor's one piece of chrome above the stage. Left,
     the brand mark (the menu) and the view switch — Preview, Edit, Content,
     Code (developer mode) — which decides what the stage holds. Centre, the page
     switcher (a dark dropdown). Right, the device button (one control
     cycling Desktop → Tablet → Phone), the Assistant (developer mode), the
     open-in-new-tab link and Publish. The `menu` and `actions` slots come
     from home.blade.php.

     The page switcher and the device button are about the canvas: they
     step back wherever it is not on screen (Content, Code without the
     split). The device button is quiet (`s-tb-quiet`) — it fades in while
     the pointer is on the bar, and stays in view off desktop, because it
     explains what the stage shows. --}}
<header class="s-topbar" :class="$store.studio.view !== 'design' && 'is-flush'" aria-label="Editor">
    <div class="s-topbar-group is-start">
        {{ $menu ?? '' }}

        {{-- The view switch. One well; the thumb slides to the view that is
             showing (measured, so it fits each label). --}}
        <nav
            class="s-views"
            aria-label="View"
            x-data="{
                thumb: { x: 0, w: 0 },
                ready: false,
                place() {
                    const el = $root.querySelector('[data-view=' + $store.studio.view + ']');
                    if (!el || !el.offsetWidth) return;
                    this.thumb = { x: el.offsetLeft, w: el.offsetWidth };
                    // The first placement lands; the rest glide
                    if (!this.ready) requestAnimationFrame(() => this.ready = true);
                },
            }"
            x-init="$nextTick(() => place()); document.fonts?.ready.then(() => place())"
            x-effect="$store.studio.view; $store.studio.codeAvailable; $nextTick(() => place())"
            @resize.window="place()"
        >
            <span class="s-views-thumb" :class="ready && 'is-ready'" :style="{ width: thumb.w + 'px', transform: 'translateX(' + thumb.x + 'px)' }" aria-hidden="true"></span>

            {{-- Only the view that is showing carries its name; the others are
                 their icon, named by a tooltip --}}
            <button type="button" class="s-view s-tip" data-view="preview" :class="$store.studio.view === 'preview' && 'is-active'" :data-tip="$store.studio.view === 'preview' ? '' : 'Preview — use the page'" aria-label="Preview" :aria-pressed="$store.studio.view === 'preview'" @click="$store.studio.setView('preview')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.75 5.6 3.75 9S14.5 18.4 12 21c-2.5-2.6-3.75-5.6-3.75-9S9.5 5.6 12 3Z"/></svg>
                <span x-show="$store.studio.view === 'preview'" x-cloak>Preview</span>
            </button>
            <button type="button" class="s-view s-tip" data-view="design" :class="$store.studio.view === 'design' && 'is-active'" :data-tip="$store.studio.view === 'design' ? '' : 'Edit — click a section to edit it'" aria-label="Edit" :aria-pressed="$store.studio.view === 'design'" @click="$store.studio.setView('design')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.5 4.5 11.4 20l2.3-6.3L20 11.4 5.5 4.5Z"/></svg>
                <span x-show="$store.studio.view === 'design'" x-cloak>Edit</span>
            </button>
            <button type="button" class="s-view s-tip" data-view="content" :class="$store.studio.view === 'content' && 'is-active'" :data-tip="$store.studio.view === 'content' ? '' : 'Content — the collections'" aria-label="Content" :aria-pressed="$store.studio.view === 'content'" @click="$store.studio.setView('content')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125S3.75 14.278 3.75 12"/></svg>
                <span x-show="$store.studio.view === 'content'" x-cloak>Content</span>
            </button>
            <button type="button" class="s-view s-tip" data-view="code" x-show="$store.studio.codeAvailable" x-cloak :class="$store.studio.view === 'code' && 'is-active'" :data-tip="$store.studio.view === 'code' ? '' : 'Code — the site\'s files'" aria-label="Code" :aria-pressed="$store.studio.view === 'code'" @click="$store.studio.setView('code')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 7.5 4 12l4.5 4.5M15.5 7.5 20 12l-4.5 4.5"/></svg>
                <span x-show="$store.studio.view === 'code'" x-cloak>Code</span>
            </button>
        </nav>
    </div>

    {{-- The page switcher --}}
    <div class="s-topbar-group is-center" :class="!$store.studio.canvasVisible && 'is-away'" :inert="!$store.studio.canvasVisible">
        <div
            class="relative min-w-0"
            x-data="{
                open: false,
                pages: window.__studioPageList || [],
                // Which pages have their entries unfolded, by path; the one
                // holding the entry on the canvas starts open
                unfolded: Object.fromEntries((window.__studioPageList || []).filter((p) => p.children.some((c) => c.current)).map((p) => [p.path, true])),
                // Rows without entries keep the chevron's room, so the paths line up
                nested: (window.__studioPageList || []).some((p) => p.children.length),
                get current() { return $store.studio.entry || this.pages.find((p) => p.current) || { title: 'Page', path: '/' } },
                // An entry's own part of its address: /blog/first-post → /first-post
                tail(page, child) { return page.path !== '/' && child.path.startsWith(page.path + '/') ? child.path.slice(page.path.length) : child.path },
                goEntry(child) { $store.studio.leaveContent(); window.location.href = child.url },
                // Choosing a page asks for the page: Content gives the stage back
                go(slug) { $store.studio.leaveContent(); window.location.href = window.__studioEditorUrl + '?page=' + encodeURIComponent(slug) },
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
                class="s-pop absolute left-1/2 top-full z-[88] mt-1.5 w-80 origin-top -translate-x-1/2"
                role="menu"
            >
                <div class="max-h-[min(64vh,560px)] overflow-y-auto overscroll-contain">
                <template x-for="p in pages" :key="p.path">
                    <div>
                    <div class="s-page-row" :class="p.current && 'is-current'">
                        {{-- A collection with no page at its address has nothing to open: its row only unfolds --}}
                        <button type="button" class="s-page-row-main" role="menuitem" @click="if (!p.slug) { unfolded[p.path] = !unfolded[p.path]; return } open = false; if (!p.current) go(p.slug)">
                            <svg x-show="p.current" class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                            <span x-show="!p.current" class="w-3 shrink-0"></span>
                            <span class="min-w-0 flex-1 truncate" x-text="p.title"></span>
                            <span class="shrink-0 font-mono text-[10.5px] text-faint" x-text="p.path"></span>
                        </button>
                        <button x-show="p.current" type="button" class="s-page-row-action" title="Page settings" aria-label="Page settings" @click.stop="open = false; $store.studio.openPageSettings()">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.929 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.992 6.992 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd"/></svg>
                        </button>
                        <span x-show="nested && !p.children.length" class="w-6 shrink-0"></span>
                        <button
                            x-show="p.children.length"
                            type="button"
                            class="s-page-row-fold"
                            :class="unfolded[p.path] && 'is-open'"
                            :title="(unfolded[p.path] ? 'Hide' : 'Show') + ' the ' + p.children.length + (p.children.length === 1 ? ' entry' : ' entries')"
                            :aria-label="(unfolded[p.path] ? 'Hide' : 'Show') + ' the entries under ' + p.title"
                            :aria-expanded="!!unfolded[p.path]"
                            @click.stop="unfolded[p.path] = !unfolded[p.path]"
                        >
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L10.94 10 7.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>

                    {{-- The collection's entries served under this page --}}
                    <div class="s-page-children" x-show="p.children.length && unfolded[p.path]" x-collapse.duration.150ms>
                        <template x-for="c in p.children" :key="c.path">
                            <button type="button" class="s-page-child" :class="c.current && 'is-current'" role="menuitem" :title="c.path" @click="open = false; if (!c.current) goEntry(c)">
                                <span class="min-w-0 flex-1 truncate" x-text="c.title"></span>
                                <span class="s-page-child-path" x-text="tail(p, c)"></span>
                            </button>
                        </template>
                    </div>
                    </div>
                </template>
                </div>

                <div class="s-pop-divider"></div>

                <button type="button" class="s-menu-item" role="menuitem" @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    New page…
                </button>
                <button type="button" class="s-menu-item" role="menuitem" @click="open = false; $store.studio.openDrawer('pages')">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    Manage pages…
                </button>
            </div>
        </div>
    </div>

    <div class="s-topbar-group is-end">
        {{-- Undo and redo: one history for the whole site (Services/History),
             whoever made the change. Always in view — dimmed when there is
             nothing to take back or bring back; the tooltip names the step. --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            :disabled="!$store.studio.history.undo || $store.studio.historyBusy"
            :data-tip="$store.studio.history.undo ? 'Undo ' + $store.studio.history.undo + '  ⌘Z' : 'Nothing to undo'"
            aria-label="Undo"
            @click="$store.studio.undo()"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
        </button>
        <button
            type="button"
            class="s-tb-btn s-tip"
            :disabled="!$store.studio.history.redo || $store.studio.historyBusy"
            :data-tip="$store.studio.history.redo ? 'Redo ' + $store.studio.history.redo + '  ⇧⌘Z' : 'Nothing to redo'"
            aria-label="Redo"
            @click="$store.studio.redo()"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
        </button>

        {{-- The canvas's width: Fit (the stage as it is) or a device. A device
             is a real width — the page is laid out at 1440, 768 or 390 and
             shown scaled down where the stage is narrower; the percentage
             beside the buttons says by how much. --}}
        <div class="s-devices" x-show="$store.studio.canvasVisible" role="group" aria-label="Canvas width">
            <span class="s-devices-zoom" x-show="$store.studio.device !== 'fit' && $store.studio.zoom < 0.995" x-cloak x-text="Math.round($store.studio.zoom * 100) + '%'" title="The page is shown smaller than life to fit"></span>
            <button type="button" class="s-device s-tip" :class="$store.studio.device === 'fit' && 'is-active'" data-tip="Fit — the width there is  ⌥0" aria-label="Fit the window" :aria-pressed="$store.studio.device === 'fit'" @click="$store.studio.setDevice('fit')">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.75 12h16.5M7 8.5 3.5 12 7 15.5M17 8.5l3.5 3.5-3.5 3.5"/></svg>
            </button>
            <button type="button" class="s-device s-tip" :class="$store.studio.device === 'desktop' && 'is-active'" data-tip="Desktop · 1440px  ⌥1" aria-label="Desktop width" :aria-pressed="$store.studio.device === 'desktop'" @click="$store.studio.setDevice('desktop')">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25"/></svg>
            </button>
            <button type="button" class="s-device s-tip" :class="$store.studio.device === 'tablet' && 'is-active'" data-tip="Tablet · 768px  ⌥2" aria-label="Tablet width" :aria-pressed="$store.studio.device === 'tablet'" @click="$store.studio.setDevice('tablet')">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            </button>
            <button type="button" class="s-device s-tip" :class="$store.studio.device === 'mobile' && 'is-active'" data-tip="Phone · 390px  ⌥3" aria-label="Phone width" :aria-pressed="$store.studio.device === 'mobile'" @click="$store.studio.setDevice('mobile')">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3"/></svg>
            </button>
        </div>

        {{-- The Assistant — the right column, in any view (developer mode) --}}
        <button
            type="button"
            class="s-tb-btn s-tip"
            x-show="$store.studio.chatAvailable"
            x-cloak
            :class="{ 'is-active': $store.studio.assistantOpen, 'is-busy': $store.studio.chatBusy }"
            :data-tip="$store.studio.assistantOpen ? 'Close the Assistant  ⌘J' : 'Assistant  ⌘J'"
            aria-label="Assistant"
            :aria-pressed="$store.studio.assistantOpen"
            @click="$store.studio.assistantOpen ? $store.studio.setAssistant(false) : $store.studio.focusChat()"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
        </button>

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

        {{ $actions ?? '' }}
    </div>
</header>
