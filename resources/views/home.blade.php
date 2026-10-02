@php
    $homeSlug = \Designer\Studio\Support\SiteUrls::homeSlug();
    $liveUrl = \Designer\Studio\Support\SiteUrls::pageUrl($page->slug);
    // Where publishing writes this page (index.blade.php for the home page)
    $pageFile = 'resources/designer/views/pages/' . ($page->slug === $homeSlug ? 'index' : $page->slug) . '.blade.php';
    $totalComponents = $library->flatten(1)->count();
    // The server-side gate. The Code view and the Assistant need this AND the
    // user's developer-mode switch.
    $devModeAvailable = \Designer\Studio\Support\DevMode::enabled();
    // The top bar's "open in a new tab": the draft preview, or the live page
    $openUrl = $draftMode ? route('studio.preview.page', ['slug' => $page->slug]) : $liveUrl;
    $openLabel = $draftMode ? 'Open the draft preview in a new tab' : 'Open the live page in a new tab';
@endphp

<x-studio::layouts.app :open-url="$openUrl" :open-label="$openLabel">
    <x-slot:title>{{ $page->title }} — Designer Studio</x-slot:title>

    {{-- ============================================================ --}}
    {{-- The store, and the top bar's actions (Publish)               --}}
    {{-- ============================================================ --}}
    <x-slot:actions>
        <script>
            // Preview mode follows links inside the canvas. The editor is
            // bound to one page, so StudioEditor.navigate() resolves a clicked
            // path against this list and moves the window to that page.
            window.__studioEditorUrl = @js(route('studio.index'));
            window.__studioPageSlug = @js($page->slug);
            // Same endpoint the image field partial posts to — used by
            // StudioEditor.uploadFieldFile() for a file dropped on the canvas.
            window.__studioUploadUrl = @js(route('studio.api.upload'));
            window.__studioPages = @js($pages->map(fn ($p) => [
                'slug' => $p->slug,
                'path' => $p->slug === $homeSlug ? '' : $p->slug,
            ])->values());
            // The top bar's page switcher and the palette read this list
            window.__studioPageList = @js($pages->map(fn ($p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'path' => $p->slug === $homeSlug ? '/' : '/' . $p->slug,
                'home' => $p->slug === $homeSlug,
                'current' => $p->slug === $page->slug,
            ])->values());

            document.addEventListener('alpine:init', () => {
                const devModeAvailable = @js($devModeAvailable);
                const clamp = (n, lo, hi, fallback) => (Number.isFinite(n) && n >= lo && n <= hi) ? n : fallback;
                const toIframe = (type, payload = {}) => window.dispatchEvent(new CustomEvent('studio:to-iframe', { detail: { type, ...payload } }));

                /* The editor's chrome state. The top bar chooses what the
                   stage holds — Design (the page), Content (the collections)
                   or Code (the site's files, developer mode) — and each view
                   owns its left side: Design has a rail of tools whose panels
                   (Pages, Media) fly out over the canvas; Content and Code
                   dock their list (the collections, the files) inside the
                   stage. The right column holds the section being edited,
                   the page's settings or the Assistant, one at a time. What
                   is remembered is the view, the widths, the theme, the
                   developer switch and whether the Assistant is open —
                   never where the chrome sits. */
                Alpine.store('studio', {
                    device: 'desktop',
                    widths: { desktop: '100%', tablet: '768px', mobile: '390px' },

                    /* --- the view ---------------------------------------- */
                    /* What the stage holds. Design is the page, always
                       editable — the only view a marketing team needs
                       besides Content. Code exists only where the server
                       gate AND the developer switch are on. */
                    views: ['design', 'content', 'code'],
                    view: (() => {
                        const saved = localStorage.getItem('studio.view');
                        const developer = devModeAvailable && localStorage.getItem('studio.devmode') === '1';
                        if (saved === 'content') return 'content';
                        if (saved === 'code' && developer) return 'code';
                        return 'design';
                    })(),
                    setView(name) {
                        if (!this.views.includes(name) || name === this.view) return;
                        if (name === 'code' && !this.codeAvailable) return;
                        // The inspector edits the page, so it cannot outlive it
                        if (name !== 'design' && this.inspector) this.closeInspector();
                        this.closeDrawer();
                        this.view = name;
                        localStorage.setItem('studio.view', name);
                        // The canvas reads this at boot (it only knows edit | code)
                        localStorage.setItem('studio.mode', this.mode);
                        toIframe('studio:mode', { mode: this.mode });
                        window.dispatchEvent(new CustomEvent('studio:view', { detail: { view: name } }));
                    },
                    // The canvas's and the code pane's word for it
                    get mode() { return this.view === 'code' ? 'code' : 'edit' },
                    get codeAvailable() { return this.developer },
                    get stage() { return { design: 'page', content: 'content', code: 'code' }[this.view] },
                    // Something needs the page on the stage
                    leaveContent() { if (this.view === 'content') this.setView('design') },
                    // The collection the stage is showing; the Content
                    // table owns it (livewire/content-table) and the docked
                    // list reads it for its highlight
                    collection: null,
                    // Whether the canvas is on screen at all: Content takes
                    // its place, Code hides it unless the split is open.
                    get canvasVisible() { return this.view === 'design' || (this.view === 'code' && this.codeSplit) },

                    // Code is full-width by default; the split brings the
                    // live preview back beside the editor.
                    codeSplit: localStorage.getItem('studio.code-split') === '1',
                    toggleCodeSplit() {
                        this.codeSplit = !this.codeSplit;
                        localStorage.setItem('studio.code-split', this.codeSplit ? '1' : '0');
                    },
                    codeSize: clamp(parseFloat(localStorage.getItem('studio.code-size')), 20, 80, 50),
                    setCodeSize(percent) {
                        this.codeSize = Math.min(80, Math.max(20, percent));
                        localStorage.setItem('studio.code-size', String(this.codeSize));
                    },

                    /* --- the docked lists -------------------------------- */
                    /* Content's collections and Code's files sit inside the
                       stage, on its left edge: part of the view, not a panel
                       beside it. Each can be folded away (⌘B); they share a
                       width. */
                    docks: {
                        content: localStorage.getItem('studio.dock-content') !== '0',
                        code: localStorage.getItem('studio.dock-code') !== '0',
                    },
                    dockWidth: clamp(parseInt(localStorage.getItem('studio.dock-width'), 10), 200, 440, 248),
                    setDockWidth(px) {
                        this.dockWidth = Math.round(Math.min(440, Math.max(200, px)));
                        localStorage.setItem('studio.dock-width', String(this.dockWidth));
                    },
                    toggleDock(view = this.view) {
                        if (!(view in this.docks)) return;
                        this.docks[view] = !this.docks[view];
                        localStorage.setItem('studio.dock-' + view, this.docks[view] ? '1' : '0');
                    },

                    /* --- the flyout -------------------------------------- */
                    /* Pages and Media open over the canvas from the rail and
                       go away again: a click on the canvas, Esc, the rail
                       button or the panel's close. Nothing is pushed aside.
                       Media is also the image picker, from any view
                       (Studio.mediaPick). */
                    drawers: ['pages', 'media'],
                    drawer: null,
                    // The one that was open last: ⌘B brings it back, and it
                    // is what stays drawn while the flyout slides away
                    drawerShown: 'pages',
                    openDrawer(name) {
                        if (!this.drawers.includes(name) || this.drawer === name) return;
                        this.drawer = name;
                        this.drawerShown = name;
                        window.dispatchEvent(new CustomEvent('studio:drawer', { detail: { name } }));
                        // Typing goes to the panel's own search, straight away
                        requestAnimationFrame(() => requestAnimationFrame(() => {
                            if (this.drawer === name) document.querySelector('[data-drawer=' + name + '] input[type=search]')?.focus({ preventScroll: true });
                        }));
                    },
                    closeDrawer() {
                        if (!this.drawer) return;
                        this.drawer = null;
                        window.dispatchEvent(new CustomEvent('studio:drawer', { detail: { name: null } }));
                    },
                    toggleDrawer(name) { this.drawer === name ? this.closeDrawer() : this.openDrawer(name) },
                    // Pages is a list; Media is a grid and starts wider
                    drawerWidths: {
                        pages: clamp(parseInt(localStorage.getItem('studio.panel-width'), 10), 260, 520, 300),
                        media: clamp(parseInt(localStorage.getItem('studio.panel-width-wide'), 10), 300, 640, 420),
                    },
                    get drawerWidth() { return this.drawerWidths[this.drawerShown] },
                    setDrawerWidth(px) {
                        const media = this.drawerShown === 'media';
                        this.drawerWidths[this.drawerShown] = Math.round(Math.min(media ? 640 : 520, Math.max(media ? 300 : 260, px)));
                        localStorage.setItem(media ? 'studio.panel-width-wide' : 'studio.panel-width', String(this.drawerWidths[this.drawerShown]));
                    },
                    // ⌘B — the left side of whichever view is showing
                    toggleSide() {
                        if (this.view === 'design') this.toggleDrawer(this.drawer || this.drawerShown);
                        else this.toggleDock();
                    },

                    /* --- the right column -------------------------------- */
                    /* One column, one panel at a time: the fields of the
                       section being edited ('section'), the page's settings
                       ('page'), or the Assistant. The Assistant and an edit
                       can both be open; `front` says which one is showing,
                       and closing it brings the other back. */
                    inspector: null,
                    front: 'assistant',
                    inspectorWidth: clamp(parseInt(localStorage.getItem('studio.inspector-width'), 10), 300, 560, 360),
                    chatWidth: clamp(parseInt(localStorage.getItem('studio.assistant-width'), 10), 300, 640, 380),
                    get rightPanel() {
                        if (this.assistantOpen) return 'assistant';
                        return this.inspector ? 'inspector' : null;
                    },
                    // What stays drawn while the column slides away
                    rightShown: 'inspector',
                    get rightWidth() { return this.rightShown === 'assistant' ? this.chatWidth : this.inspectorWidth },
                    setRightWidth(px) {
                        if (this.rightShown === 'assistant') {
                            this.chatWidth = Math.round(Math.min(640, Math.max(300, px)));
                            localStorage.setItem('studio.assistant-width', String(this.chatWidth));
                        } else {
                            this.inspectorWidth = Math.round(Math.min(560, Math.max(300, px)));
                            localStorage.setItem('studio.inspector-width', String(this.inspectorWidth));
                        }
                    },
                    // The toolbar's Edit, the context menu, E. While a section
                    // is being edited the canvas isolates it (studio:focus).
                    openInspector(sectionId = null) {
                        this.setView('design');
                        this.inspector = 'section';
                        this.front = 'inspector';
                        if (sectionId) toIframe('studio:focus', { sectionId, on: true });
                    },
                    // Page settings are the same column, with the page left as it is (⌘,)
                    openPageSettings() {
                        this.setView('design');
                        this.closeDrawer();
                        if (this.inspector === 'section') toIframe('studio:focus', { on: false });
                        this.inspector = 'page';
                        this.front = 'inspector';
                        window.Livewire?.dispatch('studio:open-page-settings');
                    },
                    // Closing a section's editor ends the edit: the page
                    // comes back and the section is deselected. `fromServer`
                    // means Livewire already dropped the selection (its own
                    // close button, a delete) — no need to tell it again.
                    closeInspector({ fromServer = false } = {}) {
                        if (!this.inspector) return;
                        const was = this.inspector;
                        this.inspector = null;
                        if (was === 'section') {
                            toIframe('studio:focus', { on: false });
                            if (!fromServer) {
                                toIframe('studio:deselect');
                                window.Livewire?.dispatch('studio:deselect-section');
                                window.dispatchEvent(new CustomEvent('studio:selection-changed', { detail: { id: null } }));
                            }
                        }
                        if (was === 'page' && !fromServer) window.Livewire?.dispatch('studio:close-page-settings');
                    },

                    /* --- the Assistant ----------------------------------- */
                    // A developer surface — it runs a coding agent against
                    // the app — so it exists only in developer mode: the
                    // server's gate AND the menu's switch. The top bar's
                    // button (⌘J) opens it in the right column, in any view.
                    assistant: devModeAvailable && localStorage.getItem('studio.devmode') === '1' && localStorage.getItem('studio.assistant') === '1',
                    get chatAvailable() { return this.developer },
                    // On screen — open, and not behind an edit
                    get assistantOpen() { return this.assistant && this.chatAvailable && (this.front === 'assistant' || !this.inspector) },
                    chatBusy: false,   // a turn is streaming
                    setAssistant(on) {
                        on = !!on && this.chatAvailable;
                        this.assistant = on;
                        if (on) this.front = 'assistant';
                        localStorage.setItem('studio.assistant', on ? '1' : '0');
                    },
                    toggleAssistant() { this.setAssistant(!this.assistantOpen) },
                    // Back to the fields the Assistant is covering
                    showFields() { if (this.inspector) this.front = 'inspector' },
                    // Bring the chat forward with the caret in it (⌘J)
                    focusChat() {
                        if (!this.chatAvailable) return;
                        this.setAssistant(true);
                        window.dispatchEvent(new CustomEvent('studio:focus-chat'));
                    },
                    // A section's Ask AI: the section becomes the chat's
                    // context (the composer's chip) and the caret lands in
                    // the composer. A section being edited stays as it is,
                    // its fields one click behind the chat.
                    askAi(sectionId) {
                        if (!this.chatAvailable || !sectionId) return;
                        this.setView('design');
                        if (this.inspector === 'page') this.closeInspector();
                        window.Livewire?.dispatch('studio:select-section', { id: sectionId });
                        toIframe('studio:select', { sectionId, scroll: false });
                        this.focusChat();
                    },

                    // The top bar's one responsive button steps Desktop → Tablet → Phone
                    cycleDevice() {
                        this.device = { desktop: 'tablet', tablet: 'mobile', mobile: 'desktop' }[this.device] || 'desktop';
                    },

                    /* --- appearance -------------------------------------- */
                    theme: document.documentElement.classList.contains('studio-light') ? 'light' : 'dark',
                    setTheme(name) {
                        this.theme = name === 'light' ? 'light' : 'dark';
                        localStorage.setItem('studio.theme', this.theme);
                        document.documentElement.classList.toggle('studio-light', this.theme === 'light');
                        // Monaco themes are global and set in JS, not CSS
                        window.StudioMonaco?.syncTheme();
                    },
                    toggleTheme() { this.setTheme(this.theme === 'light' ? 'dark' : 'light') },

                    /* --- developer mode ---------------------------------- */
                    /* One switch, two editors. On: the Code view, the
                       Assistant, edit-code buttons, source lines on the
                       canvas, field bindings, collection schemas, layouts, a
                       page's raw head HTML. Off: the editor a marketing team
                       uses — Design and Content, pages, media, Publish —
                       with nothing that reads as code. `developer` is THE
                       gate for chrome (the switch, where the server allows
                       one at all); the canvas mirrors it as html.studio-devmode.
                       The switch starts off — a new install opens as the
                       second editor until someone turns it on. */
                    devModeAvailable,
                    devMode: localStorage.getItem('studio.devmode') === '1',
                    get developer() { return this.devModeAvailable && this.devMode },
                    toggleDevMode() {
                        this.devMode = !this.devMode;
                        localStorage.setItem('studio.devmode', this.devMode ? '1' : '0');
                        toIframe('studio:devmode', { on: this.devMode });
                        // Code and the Assistant are developer surfaces —
                        // turning the switch off can't leave either showing
                        if (!this.devMode) {
                            if (this.view === 'code') this.setView('design');
                            this.setAssistant(false);
                        }
                    },
                });

                const studio = Alpine.store('studio');

                // Whichever panel the right column showed last stays drawn
                // while the column closes
                Alpine.effect(() => { if (studio.rightPanel) studio.rightShown = studio.rightPanel; });

                // Toasts rise at the stage's bottom right corner — beside the
                // right column when it is open, never over the fields or the
                // composer in it
                Alpine.effect(() => {
                    document.documentElement.style.setProperty('--studio-toast-right', (studio.rightPanel ? studio.rightWidth + 22 : 16) + 'px');
                });

                // Esc with a flyout open (studio.js relays it): Media has its
                // own ladder — lightbox, menu, an armed pick — and closes last
                window.addEventListener('studio:drawer-escape', () => {
                    if (studio.drawer && studio.drawer !== 'media') studio.closeDrawer();
                });

                // Livewire dropped the selection (its close button, a
                // delete, a page switch) — the section editor goes with it.
                window.addEventListener('studio:selection-changed', (event) => {
                    const store = Alpine.store('studio');
                    if ((event.detail?.id ?? null) === null && store.inspector === 'section') {
                        store.closeInspector({ fromServer: true });
                    }
                });
                // Page settings closed from inside the panel
                window.addEventListener('studio:page-settings-closed', () => {
                    const store = Alpine.store('studio');
                    if (store.inspector === 'page') store.closeInspector({ fromServer: true });
                });
            });

            window.addEventListener('studio:toast', (event) => {
                const { message, description, type, action } = event.detail;

                let toastAction = null;

                if (action?.dispatch) {
                    toastAction = { label: action.label, onClick: () => window.Livewire?.dispatch(action.dispatch) };
                } else if (action?.reload) {
                    toastAction = { label: action.label, onClick: () => window.location.reload() };
                }

                window.Studio?.toast({ title: message, description }, type || 'success', undefined, toastAction);
            });
        </script>

        {{-- Publish --}}
        <div class="relative" x-data="{
            open: false,
            copied: false,
            draftMode: @js($draftMode),
            status: @js($publishStatus),
            busy: false,

            copyUrl() {
                // navigator.clipboard only exists in secure contexts (https/localhost);
                // fall back to execCommand for plain-http dev domains like *.test
                const url = @js($liveUrl);
                const done = () => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1800);
                };
                const fallback = () => {
                    const el = document.createElement('textarea');
                    el.value = url;
                    el.setAttribute('readonly', '');
                    el.style.position = 'fixed';
                    el.style.opacity = '0';
                    document.body.appendChild(el);
                    el.select();
                    try { document.execCommand('copy') && done(); } finally { el.remove(); }
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(done).catch(fallback);
                } else {
                    fallback();
                }
            },

            async refreshStatus() {
                if (!this.draftMode) return;
                try {
                    const response = await fetch(@js(route('studio.api.publish.status')), { headers: { 'Accept': 'application/json' } });
                    this.status = await response.json();
                } catch (e) { /* keep last known status */ }
            },

            async publish() {
                if (this.busy) return;
                this.busy = true;
                try {
                    const response = await fetch(@js(route('studio.api.publish')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.Studio.toast(data.count === 1 ? '1 change published' : data.count + ' changes published');
                        (data.notes || []).forEach((note) => window.Studio.toast(note, 'error', 9000));
                        if (data.home_claimed) {
                            window.Studio.toast('Removed Laravel\'s default welcome page — your homepage now serves at /', 'info', 6000);
                        }
                        await this.refreshStatus();
                    } else {
                        window.Studio.toast('Could not publish the site', 'error');
                    }
                } catch (e) {
                    window.Studio.toast('Could not publish the site', 'error');
                }
                this.busy = false;
            },

            async discard() {
                const count = this.status?.items?.length ?? 0;
                if (!window.confirm('Discard all unpublished changes? ' + count + (count === 1 ? ' item' : ' items') + ' will be reverted to the live site. This cannot be undone.')) return;
                this.busy = true;
                try {
                    const response = await fetch(@js(route('studio.api.publish.discard')), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                } catch (e) { /* fall through */ }
                window.Studio.toast('Could not discard the draft', 'error');
                this.busy = false;
            },
        }"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
        @studio:status.window="if ($event.detail.state === 'saved' && status) status.dirty = true"
        @studio:open-publish.window="open = true; refreshStatus()"
        >
            <button
                @click="open = !open; if (open) refreshStatus()"
                class="s-publish"
                :class="open && 'is-open'"
                x-data="{ state: 'idle' }"
                @studio:status.window="state = $event.detail.state"
                :title="state === 'saving' ? 'Saving…' : state === 'error' ? 'Offline — changes are not being saved' : 'All changes saved'"
                aria-label="Publish"
                aria-haspopup="dialog"
                :aria-expanded="open"
            >
                <span class="s-status-pip" :class="{ 'is-saving': state === 'saving', 'is-error': state === 'error' }"></span>
                <span class="s-publish-label">Publish</span>
                <span x-show="draftMode && status?.dirty" x-cloak x-transition.opacity class="s-dirty-pip"></span>
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
                class="s-pop absolute right-0 top-full z-[88] mt-1.5 w-80 p-3"
            >
                @if($draftMode)
                    {{-- Unpublished changes --}}
                    <div x-show="status?.dirty" x-cloak>
                        <p class="s-microlabel px-0.5">Unpublished changes</p>

                        <div class="mt-2 max-h-44 space-y-1 overflow-y-auto pr-0.5">
                            <template x-for="item in (status?.items ?? [])" :key="item.type + '/' + item.slug">
                                <div class="flex items-center gap-2 rounded-md px-0.5 py-0.5 text-xs">
                                    <span class="s-chip w-14 shrink-0 !justify-center capitalize" x-text="item.type"></span>
                                    <span class="min-w-0 flex-1 truncate text-soft" x-text="item.label"></span>
                                    <span
                                        class="shrink-0 text-[10.5px]"
                                        :class="{ 'text-ok': item.state === 'added', 'text-warn': item.state === 'updated', 'text-danger': item.state === 'removed' }"
                                        x-text="item.state === 'removed' ? 'will be removed' : item.state"
                                    ></span>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex items-center gap-1.5">
                            <button @click="discard()" :disabled="busy" class="s-btn-ghost flex-1 !justify-center !text-danger hover:!bg-danger/10">
                                Discard
                            </button>
                            <button @click="publish()" :disabled="busy" class="s-btn-accent flex-1">
                                <span x-show="!busy">Publish site</span>
                                <span x-show="busy" x-cloak>Publishing…</span>
                            </button>
                        </div>

                        <a
                            href="{{ route('studio.preview.page', ['slug' => $page->slug]) }}"
                            target="_blank"
                            class="s-btn-outline mt-1.5 w-full"
                        >
                            Preview the draft site
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        </a>

                        <div class="s-divider my-3"></div>
                    </div>

                    {{-- Everything published --}}
                    <div x-show="!status || !status.dirty" x-cloak class="mb-3 flex items-start gap-2.5">
                        <span class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center">
                            <span class="s-status-dot bg-ok"></span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-ink">Everything is live</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-soft">The live site matches your draft. New edits stay in the draft until you publish them.</p>
                        </div>
                    </div>
                @else
                    <div class="mb-3 flex items-start gap-2.5">
                        <span class="mt-1 flex h-4 w-4 shrink-0 items-center justify-center">
                            <span class="s-status-dot bg-ok"></span>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-ink">This page is live</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-soft">Changes you make in the Studio are published to your site immediately.</p>
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-1.5 rounded-lg border border-line bg-shell py-1.5 pl-2.5 pr-1.5">
                    <span class="min-w-0 flex-1 truncate font-mono text-xs text-soft">{{ $liveUrl }}</span>
                    <button @click="copyUrl()" class="s-icon-btn !h-6 !w-6" title="Copy URL">
                        <svg x-show="!copied" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.988 3.012A2.25 2.25 0 0 1 18 5.25v6.5A2.25 2.25 0 0 1 15.75 14H13.5v-3.379a3 3 0 0 0-.879-2.121l-3.12-3.121a3 3 0 0 0-1.402-.791 2.252 2.252 0 0 1 1.913-1.576A2.25 2.25 0 0 1 12.25 1h1.5a2.25 2.25 0 0 1 2.238 2.012ZM11.5 3.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v.25h-3v-.25Z" clip-rule="evenodd"/><path d="M3.5 6A1.5 1.5 0 0 0 2 7.5v9A1.5 1.5 0 0 0 3.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L8.44 6.439A1.5 1.5 0 0 0 7.378 6H3.5Z"/></svg>
                        <svg x-show="copied" x-cloak class="h-3.5 w-3.5 text-ok" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                <div class="s-divider my-3"></div>

                <p class="font-medium text-ink">Site files</p>
                <p class="mt-0.5 text-xs leading-relaxed text-soft">
                    {{ $draftMode ? 'Publishing writes' : 'Edits are written' }} to <span class="font-mono text-[11px] text-ink/80">resources/designer</span> —
                    this page is <span class="font-mono text-[11px] text-ink/80">{{ \Illuminate\Support\Str::after($pageFile, 'resources/designer/') }}</span>.
                    The site runs from those files, with or without Studio.
                </p>
            </div>
        </div>
    </x-slot:actions>

    {{-- ============================================================ --}}
    {{-- Menu — the brand mark in the top bar                          --}}
    {{-- ============================================================ --}}
    <x-slot:menu>
        <div
            class="relative"
            x-data="{
                open: false,

                async duplicatePage() {
                    this.open = false;
                    try {
                        const response = await fetch(@js(route('studio.api.pages.duplicate', ['slug' => $page->slug])), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = data.editor_url;
                            return;
                        }
                        window.Studio.toast(data.error || 'Could not duplicate the page', 'error');
                    } catch (e) {
                        window.Studio.toast('Could not duplicate the page', 'error');
                    }
                },
            }"
            @click.outside="open = false"
            @keydown.escape.window="open = false"
        >
            {{-- The brand mark at rest; the hamburger on hover and while the menu is open --}}
            <button @click="open = !open" class="s-tb-btn s-brand" :class="open && 'is-open'" title="Menu" aria-label="Menu" aria-haspopup="menu" :aria-expanded="open">
                <svg class="s-brand-logo h-[15px] w-auto" viewBox="0 0 72 75" fill="none" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/></svg>
                <svg class="s-brand-menu h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" d="M4 6.5h16M4 12h16M4 17.5h16"/></svg>
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
                class="s-pop absolute left-0 top-full z-[88] mt-1.5 w-[268px]"
                role="menu"
            >
                <div class="flex items-center gap-2.5 px-2.5 pb-2 pt-2">
                    <svg class="h-[15px] w-auto text-ink" viewBox="0 0 72 75" fill="none" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"></path></svg>
                    <span class="text-[13px] font-semibold text-ink">Designer Studio</span>
                </div>

                <div class="s-divider mb-1"></div>

                <button @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    New page…
                </button>
                <button @click="duplicatePage()" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    Duplicate this page
                </button>
                <button @click="open = false; $store.studio.openPageSettings()" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.929 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.992 6.992 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd"/></svg>
                    <span class="flex-1">Page settings…</span>
                    <span class="s-kbd">⌘,</span>
                </button>
                <button x-show="$store.studio.developer" x-cloak @click="open = false; $store.studio.openPageSettings(); window.Livewire?.dispatch('studio:new-layout')" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    New layout…
                </button>

                <div class="s-divider my-1"></div>

                @if($devModeAvailable)
                    {{-- One switch, two editors --}}
                    <button @click="$store.studio.toggleDevMode()" class="s-menu-item !items-start !py-2" role="menuitemcheckbox" :aria-checked="$store.studio.devMode">
                        <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd"/></svg>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="text-ink">Developer mode</span>
                            <span class="mt-px text-[11px] leading-snug text-faint" x-text="$store.studio.devMode ? 'Code, the Assistant, source lines and bindings are on' : 'Content editing only — nothing that reads as code'"></span>
                        </span>
                        <span class="s-switch mt-0.5" :class="$store.studio.devMode && 'is-on'" aria-hidden="true"></span>
                    </button>
                    <div class="s-divider my-1"></div>
                @endif

                {{-- Appearance --}}
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span class="flex items-center gap-2.5">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg>
                        Appearance
                    </span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Theme">
                        @foreach(['dark' => 'Dark', 'light' => 'Light'] as $value => $label)
                            <button
                                type="button"
                                class="flex h-6 cursor-pointer items-center justify-center rounded-md px-2 text-[11.5px] transition-colors duration-150"
                                :class="$store.studio.theme === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.setTheme('{{ $value }}')"
                                role="radio"
                                :aria-checked="$store.studio.theme === '{{ $value }}'"
                            >{{ $label }}</button>
                        @endforeach
                    </span>
                </div>

                <div class="s-divider my-1"></div>

                <button @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-shortcuts'))" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5.25A2.25 2.25 0 0 1 4.25 3h11.5A2.25 2.25 0 0 1 18 5.25v9.5A2.25 2.25 0 0 1 15.75 17H4.25A2.25 2.25 0 0 1 2 14.75v-9.5Zm2.5 1a.75.75 0 0 0 0 1.5h1a.75.75 0 0 0 0-1.5h-1Zm3.5 0a.75.75 0 0 0 0 1.5h1a.75.75 0 0 0 0-1.5H8Zm3.5 0a.75.75 0 0 0 0 1.5h1a.75.75 0 0 0 0-1.5h-1Zm3.5 0a.75.75 0 0 0 0 1.5h.5a.75.75 0 0 0 0-1.5H15Zm-9 3.5a.75.75 0 0 0 0 1.5h1a.75.75 0 0 0 0-1.5H6Zm3.5 0a.75.75 0 0 0 0 1.5h1a.75.75 0 0 0 0-1.5h-1Zm3.5 0a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H13Zm-6.5 3.5a.75.75 0 0 0 0 1.5h7a.75.75 0 0 0 0-1.5h-7Z" clip-rule="evenodd"/></svg>
                    <span class="flex-1">Keyboard shortcuts</span>
                    <span class="s-kbd">?</span>
                </button>
                @if($liveUrl)
                    <a href="{{ $liveUrl }}" target="_blank" rel="noopener" class="s-menu-item" role="menuitem">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        View live site
                    </a>
                @endif
                <a href="https://designer.dev/docs" target="_blank" rel="noopener" class="s-menu-item" role="menuitem">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 16.82A7.462 7.462 0 0 1 15 15.5c.71 0 1.396.098 2.046.282A.75.75 0 0 0 18 15.06v-11a.75.75 0 0 0-.546-.721A9.006 9.006 0 0 0 15 3a8.963 8.963 0 0 0-4.25 1.065V16.82ZM9.25 4.065A8.963 8.963 0 0 0 5 3c-.85 0-1.673.118-2.454.339A.75.75 0 0 0 2 4.06v11a.75.75 0 0 0 .954.721A7.506 7.506 0 0 1 5 15.5c1.579 0 3.042.487 4.25 1.32V4.065Z"/></svg>
                    Documentation
                </a>
            </div>
        </div>
    </x-slot:menu>

    {{-- ============================================================ --}}
    {{-- The flyout — Pages and Media, over the canvas                 --}}
    {{-- ============================================================ --}}
    <x-slot:flyout>
        <div x-data data-drawer="pages" class="flex min-h-0 flex-1 flex-col" x-show="$store.studio.drawerShown === 'pages'">
            <livewire:studio::pages-panel :page-slug="$page->slug" />
        </div>
        <div x-data data-drawer="media" class="flex min-h-0 flex-1 flex-col" x-show="$store.studio.drawerShown === 'media'" x-cloak>
            <livewire:studio::media-panel />
        </div>
    </x-slot:flyout>

    {{-- ============================================================ --}}
    {{-- The right column — an edit, or the Assistant                  --}}
    {{-- ============================================================ --}}
    <x-slot:inspector>
        <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rightShown !== 'assistant'">
            <livewire:studio::editor-panel :page-slug="$page->slug" />
        </div>
        @if($devModeAvailable)
            <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rightShown === 'assistant'" x-cloak>
                <livewire:studio::assistant-panel :page-slug="$page->slug" />
            </div>
        @endif
    </x-slot:inspector>

    {{-- ============================================================ --}}
    {{-- Command palette (⌘K)                                          --}}
    {{-- ============================================================ --}}
    <div
        x-data="{
            open: false,
            query: '',
            cursor: 0,

            /* Every command the chrome can run, filtered by `query`. Entries
               with `when` only appear where they make sense. */
            get commands() {
                const studio = $store.studio;
                const all = [
                    { label: 'Add section…', hint: 'Insert', run: () => window.dispatchEvent(new CustomEvent('studio:open-library', { detail: {} })) },
                    { label: 'New page…', hint: 'Create', run: () => window.dispatchEvent(new CustomEvent('studio:open-create-page')) },
                    { label: 'New layout…', hint: 'Create', when: studio.developer, run: () => { studio.openPageSettings(); window.Livewire?.dispatch('studio:new-layout') } },
                    // One per page, minus the open one
                    ...(window.__studioPageList || []).filter((p) => !p.current).map((p) => ({
                        label: 'Go to ' + p.title, hint: p.path,
                        run: () => { studio.leaveContent(); window.location.href = window.__studioEditorUrl + '?page=' + encodeURIComponent(p.slug) },
                    })),
                    { label: 'Page settings', hint: 'Page', run: () => studio.openPageSettings() },
                    { label: 'Design', hint: 'View', when: studio.view !== 'design', run: () => studio.setView('design') },
                    { label: 'Content', hint: 'View', when: studio.view !== 'content', run: () => studio.setView('content') },
                    { label: 'Code', hint: 'View', when: studio.codeAvailable && studio.view !== 'code', run: () => studio.setView('code') },
                    { label: studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code', hint: 'Code', when: studio.view === 'code', run: () => studio.toggleCodeSplit() },
                    { label: studio.docks.code ? 'Hide the files' : 'Show the files', hint: '⌘B', when: studio.view === 'code', run: () => studio.toggleDock('code') },
                    @if($draftMode)
                    { label: 'Publish…', hint: 'Site', run: () => window.dispatchEvent(new CustomEvent('studio:open-publish')) },
                    @endif
                    { label: 'Assistant', hint: '⌘J', when: studio.chatAvailable, run: () => studio.focusChat() },
                    { label: 'Pages', hint: 'Panel', run: () => { studio.setView('design'); studio.openDrawer('pages') } },
                    { label: 'Media', hint: 'Panel', run: () => studio.openDrawer('media') },
                    { label: 'Refresh the preview', hint: 'Canvas', run: () => window.dispatchEvent(new CustomEvent('studio:refresh-preview')) },
                    { label: 'Open in a new tab', hint: 'Canvas', run: () => window.open(@js($openUrl), '_blank', 'noopener') },
                    { label: 'View live site', hint: 'Site', run: () => window.open(@js($liveUrl ?? url('/')), '_blank', 'noopener') },
                    { label: 'Desktop width', hint: '⌥1', when: studio.device !== 'desktop', run: () => studio.device = 'desktop' },
                    { label: 'Tablet width', hint: '⌥2', when: studio.device !== 'tablet', run: () => studio.device = 'tablet' },
                    { label: 'Mobile width', hint: '⌥3', when: studio.device !== 'mobile', run: () => studio.device = 'mobile' },
                    { label: studio.theme === 'dark' ? 'Switch to light appearance' : 'Switch to dark appearance', hint: 'Appearance', run: () => studio.toggleTheme() },
                    @if($devModeAvailable)
                    { label: studio.devMode ? 'Turn developer mode off' : 'Turn developer mode on', hint: 'Editor', run: () => studio.toggleDevMode() },
                    @endif
                    { label: 'Keyboard shortcuts', hint: '?', run: () => window.dispatchEvent(new CustomEvent('studio:open-shortcuts')) },
                ].filter((command) => command.when !== false);

                @if($devModeAvailable)
                // Quick-open: in the Code view, the workspace files join
                // the list so ⌘K doubles as a file switcher.
                if (studio.view === 'code') {
                    // Every file of the site, and whatever else the tree has read so far
                    [...new Set([
                        ...($store.code.index || []),
                        ...($store.code.nodes || []).filter((node) => node.type === 'file' && !node.inert).map((node) => node.path),
                    ])].forEach((path) => {
                        all.push({ label: path, hint: 'File', run: () => $store.code.openFile(path) });
                    });
                }
                @endif

                const needle = this.query.trim().toLowerCase();
                // The Laravel tree can contribute a thousand files — render a
                // window of them, never the whole list.
                if (!needle) return all.slice(0, 50);
                return all.filter((command) => command.label.toLowerCase().includes(needle)).slice(0, 50);
            },

            show() {
                this.query = '';
                this.cursor = 0;
                this.open = true;
                this.$nextTick(() => this.$refs.input?.focus());
            },

            move(delta) {
                const count = this.commands.length;
                if (!count) return;
                this.cursor = (this.cursor + delta + count) % count;
                this.$nextTick(() => this.$refs.list?.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' }));
            },

            choose(index = null) {
                const command = this.commands[index ?? this.cursor];
                if (!command) return;
                this.open = false;
                command.run();
            },
        }"
        @studio:open-palette.window="show()"
        @keydown.escape.window="open = false"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[95] flex items-start justify-center p-4 pt-[14vh]"
        role="dialog"
        aria-modal="true"
        aria-label="Command palette"
    >
        <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.150ms @click="open = false"></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 scale-[0.99]"
            class="s-modal flex max-h-[60vh] w-[520px] max-w-full flex-col overflow-hidden"
        >
            <div class="flex shrink-0 items-center gap-2.5 border-b border-line px-3.5 py-3">
                <svg class="h-4 w-4 shrink-0 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                <input
                    x-ref="input"
                    x-model="query"
                    @input="cursor = 0"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="choose()"
                    class="min-w-0 flex-1 border-0 bg-transparent text-[13.5px] text-ink placeholder:text-faint focus:outline-none"
                    placeholder="Search pages and commands…"
                    aria-label="Search pages and commands"
                >
                <span class="s-kbd">Esc</span>
            </div>

            <div x-ref="list" class="min-h-0 flex-1 overflow-y-auto p-1.5">
                <template x-for="(command, index) in commands" :key="command.label">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-left transition-colors"
                        :class="index === cursor ? 'bg-wash-strong text-ink' : 'text-soft hover:bg-wash'"
                        :data-active="index === cursor ? '' : null"
                        @mouseenter="cursor = index"
                        @click="choose(index)"
                    >
                        <span class="min-w-0 flex-1 truncate text-[13px]" x-text="command.label"></span>
                        <span class="shrink-0 text-[10.5px] uppercase tracking-wide text-faint" x-text="command.hint"></span>
                    </button>
                </template>

                <p x-show="!commands.length" x-cloak class="px-2.5 py-6 text-center text-[12.5px] text-faint">
                    No matching command.
                </p>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Keyboard shortcuts (?)                                        --}}
    {{-- ============================================================ --}}
    <div
        x-data="{ open: false }"
        @studio:open-shortcuts.window="open = true"
        @keydown.escape.window="open = false"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[95] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Keyboard shortcuts"
    >
        <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.150ms @click="open = false"></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-[0.98] translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 scale-[0.99]"
            class="s-modal w-[560px] max-w-full p-5"
        >
            <div class="flex items-center gap-3">
                <h2 class="text-[15px] font-semibold text-ink">Keyboard shortcuts</h2>
                <button @click="open = false" class="s-icon-btn ml-auto" title="Close (Esc)">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                </button>
            </div>

            @php
                $shortcutGroups = [
                    'Editor' => [
                        ['Command palette', ['⌘', 'K']],
                        ['Toggle the side panel', ['⌘', 'B']],
                        ['Page settings', ['⌘', ',']],
                        ['Desktop / tablet / phone width', ['⌥', '1–3']],
                        ['This list', ['?']],
                    ],
                    'Selected section' => [
                        ['Edit', ['E']],
                        ['Ask the Assistant about it', ['A']],
                        ['Duplicate', ['⌘', 'D']],
                        ['Move up / down', ['⌘', '↑ ↓']],
                        ['Delete (undo from the toast)', ['⌫']],
                        ['Close the editor / deselect', ['Esc']],
                    ],
                ];
                if ($devModeAvailable) {
                    $shortcutGroups['Developer mode'] = [
                        ['Assistant', ['⌘', 'J']],
                        ['Open a field\'s source line', ['⌥', 'click']],
                        ['Save the open file (Code)', ['⌘', 'S']],
                    ];
                }
            @endphp
            <div class="mt-4 grid grid-cols-2 gap-x-8 gap-y-5">
                @foreach($shortcutGroups as $group => $rows)
                    <div class="{{ $loop->last && $loop->count === 3 ? 'col-span-2' : '' }}">
                        <p class="s-microlabel">{{ $group }}</p>
                        <div class="mt-2 space-y-1.5">
                            @foreach($rows as [$label, $keys])
                                <div class="flex items-center gap-3 text-[12.5px]">
                                    <span class="min-w-0 flex-1 truncate text-soft">{{ $label }}</span>
                                    <span class="flex shrink-0 items-center gap-1">
                                        @foreach($keys as $key)
                                            <span class="s-kbd !h-5 !min-w-5 !text-[11px]">{{ $key }}</span>
                                        @endforeach
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Editor notices (security / storage warnings)                  --}}
    {{-- ============================================================ --}}
    {{-- Toasts that stay until they are closed, raised once the editor
         has faded in. Closing a dismissible one is remembered; the others
         come back on the next load for as long as they are true. --}}
    @if(!empty($notices))
        <script>
            (() => {
                const notices = @js($notices);

                const show = () => notices.forEach((notice, i) => {
                    const key = 'studio.notice.' + notice.id;

                    if (notice.dismissible && localStorage.getItem(key) === '1') return;

                    setTimeout(() => window.Studio?.toast({
                        id: 'notice-' + notice.id,
                        title: notice.title,
                        description: notice.text,
                        type: notice.tone,
                        duration: 0,
                        onDismiss: (byUser) => {
                            if (byUser && notice.dismissible) localStorage.setItem(key, '1');
                        },
                    }), 500 + i * 140);
                });

                if (document.documentElement.classList.contains('studio-booting')) {
                    window.addEventListener('studio:revealed', show, { once: true });
                } else {
                    window.addEventListener('load', show, { once: true });
                }
            })();
        </script>
    @endif

    {{-- ============================================================ --}}
    {{-- Canvas                                                        --}}
    {{-- ============================================================ --}}
    {{-- The Assistant's pick tool, armed: a banner over the canvas says what
         the next click does, and how to stop. The iframe draws the dashed
         outline; this is the editor's half of the same state. --}}
    <div
        x-data="{ on: false }"
        @studio:pick.window="on = !!$event.detail?.on; window.Studio.picking = on"
        x-show="on"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="s-pick-banner"
        role="status"
    >
        <span class="s-pick-banner-dot"></span>
        <span>Click anything on the page to add it to the chat</span>
        <button type="button" class="s-pick-banner-cancel" @click="window.dispatchEvent(new CustomEvent('studio:pick-cancel'))">
            Cancel <span class="s-kbd">Esc</span>
        </button>
    </div>

    {{-- The stage. Design is the canvas; Content is the collections (their
         list docked on the left, the table beside it); Code is the files,
         the editor and — with the split open — the canvas again. Each fills
         the stage edge to edge. --}}
    <div class="s-canvas flex h-full w-full min-w-0" x-data>
        @include('studio::partials.content-stage')

        @if($devModeAvailable)
            {{-- Code's files, docked: one dark surface with the editor --}}
            <div
                class="s-dock s-code-files"
                x-show="$store.studio.view === 'code' && $store.studio.docks.code"
                x-cloak
                :style="{ width: $store.studio.dockWidth + 'px' }"
            >
                @include('studio::partials.file-tree')
                @include('studio::partials.dock-seam')
            </div>
        @endif

        <div class="flex min-h-0 min-w-0 flex-1" x-show="$store.studio.view !== 'content'">
            @if($devModeAvailable)
                @include('studio::partials.code-pane')

                {{-- Drag seam between the code pane and the preview. While it is
                     held, a transparent shield covers the window: a pointer that
                     crosses into the preview iframe hands its mousemove/mouseup to
                     that document, so a quick drag toward the preview would stall. --}}
                <div
                    x-show="$store.studio.view === 'code' && $store.studio.codeSplit"
                    x-cloak
                    class="s-code-seam"
                    @mousedown.prevent="
                        const surface = $el.parentElement;
                        const shield = document.createElement('div');
                        shield.className = 's-drag-shield';
                        document.body.appendChild(shield);
                        const move = (event) => {
                            const box = surface.getBoundingClientRect();
                            $store.studio.setCodeSize(((event.clientX - box.left) / box.width) * 100);
                        };
                        const stop = () => {
                            shield.remove();
                            document.removeEventListener('mousemove', move);
                            document.removeEventListener('mouseup', stop);
                            window.removeEventListener('blur', stop);
                            document.body.classList.remove('select-none');
                        };
                        document.body.classList.add('select-none');
                        document.addEventListener('mousemove', move);
                        document.addEventListener('mouseup', stop);
                        window.addEventListener('blur', stop);
                    "
                    role="separator"
                    aria-label="Resize the code pane"
                ></div>
            @endif

            <div class="min-w-0 flex-1 overflow-auto" x-show="$store.studio.canvasVisible">
                <div class="flex h-full flex-col">
                    <div
                        class="s-frame mx-auto w-full transition-[max-width] duration-300 ease-out"
                        :class="$store.studio.device !== 'desktop' && 'is-narrow'"
                        :style="`max-width: ${$store.studio.widths[$store.studio.device]}`"
                    >
                        {{-- Live preview --}}
                        <iframe
                            id="studio-canvas-frame"
                            class="w-full flex-1 border-0 bg-white"
                            src="{{ route('studio.page.iframe', ['slug' => $page->slug]) }}"
                            title="Page preview"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Section library modal                                         --}}
    {{-- ============================================================ --}}
    <div
            x-data="{
                open: false,
                insertIndex: null,
                insertScope: 'page',
                addingRef: null,
                search: '',
                category: 'all',

                openLibrary(index = null, scope = 'page') {
                    this.insertIndex = index;
                    this.insertScope = scope;
                    this.search = '';
                    this.category = 'all';
                    this.open = true;
                    this.$nextTick(() => {
                        this.$refs.searchInput?.focus();
                        // Scale previews once the modal is measurable
                        this.$root.querySelectorAll('.s-preview-card').forEach((card) => this.scaleCard(card));
                    });
                },

                close() {
                    this.open = false;
                    this.addingRef = null;
                },

                add(ref) {
                    if (this.addingRef) return;
                    this.addingRef = ref;
                    Livewire.dispatch('studio:add-section', { ref: ref, index: this.insertIndex, scope: this.insertScope });
                },

                matches(el) {
                    const haystack = el.dataset.search || '';
                    const cat = el.dataset.category || '';
                    const okSearch = !this.search || haystack.includes(this.search.toLowerCase());
                    const okCategory = this.category === 'all' || cat === this.category;
                    return okSearch && okCategory;
                },

                scaleCard(card) {
                    const viewport = card.querySelector('.s-preview-viewport');
                    const frame = viewport?.querySelector('iframe');
                    if (!viewport || !frame) return;
                    const scale = viewport.offsetWidth / 1200;
                    frame.style.transform = `scale(${scale})`;
                    frame.style.height = `${Math.ceil(viewport.offsetHeight / scale)}px`;
                }
            }"
            @studio:open-library.window="openLibrary($event.detail?.index ?? null, $event.detail?.scope || 'page')"
            @studio:section-added.window="close()"
            @keydown.escape.window="close()"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center p-4 lg:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="Add a section"
        >
            <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="close()"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-modal flex h-[660px] max-h-[88vh] w-[980px] max-w-full flex-col overflow-hidden"
            >
                {{-- Modal header --}}
                <div class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-3">
                    <h2 class="text-[15px] font-semibold text-ink" x-text="insertScope === 'layout' ? 'Add a section to the layout' : 'Add a section'">Add a section</h2>
                    <span class="s-chip" x-show="insertScope === 'layout'" x-cloak style="border-color: color-mix(in srgb, var(--color-layout) 40%, transparent); color: var(--color-layout);">Shared across pages</span>
                    <span class="s-chip">{{ $totalComponents }} designs</span>

                    <div class="relative ml-auto w-64">
                        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
                        </svg>
                        <input
                            x-ref="searchInput"
                            x-model="search"
                            type="text"
                            placeholder="Search sections…"
                            class="s-input !pl-8"
                        >
                    </div>

                    <button @click="close()" class="s-icon-btn" title="Close">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>

                <div class="flex min-h-0 flex-1">
                    {{-- Category rail --}}
                    <div class="w-44 shrink-0 space-y-0.5 overflow-y-auto border-r border-line p-2">
                        @if(count($blocks))
                            <button
                                @click="category = '__blocks'"
                                class="s-menu-item justify-between !text-block"
                                :class="category === '__blocks' && 'bg-block/10'"
                            >
                                Global blocks
                                <span class="text-[10.5px] text-block/70">{{ count($blocks) }}</span>
                            </button>
                        @endif
                        <button
                            @click="category = 'all'"
                            class="s-menu-item justify-between"
                            :class="category === 'all' && 'bg-wash !text-ink'"
                        >
                            All
                            <span class="text-[10.5px] text-faint">{{ $totalComponents }}</span>
                        </button>

                        @foreach($library as $categoryName => $components)
                            <button
                                @click="category = @js($categoryName)"
                                class="s-menu-item justify-between capitalize"
                                :class="category === @js($categoryName) && 'bg-wash !text-ink'"
                            >
                                {{ ['cta' => 'CTA', 'faq' => 'FAQ', 'faqs' => 'FAQs', 'seo' => 'SEO'][$categoryName] ?? str_replace('-', ' ', $categoryName) }}
                                <span class="text-[10.5px] text-faint">{{ $components->count() }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Cards --}}
                    <div class="min-h-0 flex-1 overflow-y-auto p-4">
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Global blocks — synced everywhere they're placed --}}
                            @foreach($blocks as $block)
                                <button
                                    type="button"
                                    class="s-preview-card !border-block/25 hover:!border-block/60"
                                    data-category="__blocks"
                                    data-search="{{ strtolower($block['name'] . ' global block') }}"
                                    x-show="matches($el)"
                                    @click="add(@js('block:' . $block['slug']))"
                                    :class="addingRef === @js('block:' . $block['slug']) && 'pointer-events-none opacity-70'"
                                >
                                    <span class="s-preview-viewport">
                                        <iframe
                                            src="{{ route('studio.preview.block', ['slug' => $block['slug']]) }}"
                                            loading="lazy"
                                            tabindex="-1"
                                            title="{{ $block['name'] }} preview"
                                        ></iframe>
                                    </span>
                                    <span class="flex items-center gap-2 p-3">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-1.5">
                                                <span class="truncate text-[13px] font-medium text-ink">{{ $block['name'] }}</span>
                                                <span class="s-chip !border-block/40 !text-block">Global</span>
                                            </span>
                                            <span class="mt-0.5 block truncate text-xs text-faint">
                                                Synced — used {{ $block['usage'] }} {{ \Illuminate\Support\Str::plural('time', $block['usage']) }}
                                            </span>
                                        </span>
                                        <span x-show="addingRef === @js('block:' . $block['slug'])" x-cloak>
                                            <svg class="h-4 w-4 animate-spin text-block" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                        </span>
                                    </span>
                                </button>
                            @endforeach

                            @foreach($library as $categoryName => $components)
                                @foreach($components as $component)
                                    <button
                                        type="button"
                                        class="s-preview-card"
                                        data-category="{{ $categoryName }}"
                                        data-search="{{ strtolower($component->title . ' ' . $component->description . ' ' . $categoryName . ' ' . implode(' ', $component->tags)) }}"
                                        x-show="matches($el)"
                                        @click="add(@js($component->name))"
                                        :class="addingRef === @js($component->name) && 'pointer-events-none opacity-70'"
                                    >
                                        <span class="s-preview-viewport">
                                            <iframe
                                                src="{{ route('studio.preview.component', ['name' => $component->name]) }}"
                                                loading="lazy"
                                                tabindex="-1"
                                                title="{{ $component->title }} preview"
                                            ></iframe>
                                        </span>
                                        <span class="flex items-center gap-2 p-3">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[13px] font-medium text-ink">{{ $component->title }}</span>
                                                @if($component->description)
                                                    <span class="mt-0.5 block truncate text-xs text-faint">{{ $component->description }}</span>
                                                @endif
                                            </span>
                                            <span x-show="addingRef === @js($component->name)" x-cloak>
                                                <svg class="h-4 w-4 animate-spin text-accent" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            @endforeach
                        </div>

                        {{-- No results --}}
                        <div x-show="search && ![...$el.parentElement.querySelectorAll('.s-preview-card')].some(el => el.style.display !== 'none')" x-cloak class="py-16 text-center">
                            <p class="text-sm text-soft">No sections match “<span x-text="search" class="text-ink"></span>”</p>
                            <button @click="search = ''" class="s-btn-ghost mx-auto mt-2">Clear search</button>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Create page modal                                             --}}
    {{-- ============================================================ --}}
    <div
            x-data="{
                open: false,
                title: '',
                slug: '',
                slugEdited: false,
                creating: false,
                error: '',

                openModal() {
                    this.open = true;
                    this.title = '';
                    this.slug = '';
                    this.slugEdited = false;
                    this.error = '';
                    this.$nextTick(() => this.$refs.titleInput?.focus());
                },

                syncSlug() {
                    if (this.slugEdited) return;
                    this.slug = this.title.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                },

                async create() {
                    if (!this.title.trim() || this.creating) return;
                    this.creating = true;
                    this.error = '';
                    try {
                        const response = await fetch(@js(route('studio.api.pages.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ title: this.title, slug: this.slug }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = data.editor_url;
                            return;
                        }
                        this.error = data.message || 'Could not create the page.';
                    } catch (e) {
                        this.error = 'Could not create the page.';
                    }
                    this.creating = false;
                }
            }"
            @studio:open-create-page.window="openModal()"
            @keydown.escape.window="open = false"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Create a new page"
        >
            <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="open = false"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-modal w-[420px] max-w-full p-5"
            >
                <h2 class="text-[15px] font-semibold text-ink">Create a new page</h2>
                <p class="mt-1 text-xs leading-relaxed text-soft">Pages start empty — add sections from the library once it opens.</p>

                <div class="mt-4">
                    <label for="new-page-title" class="s-label">Page title</label>
                    <input
                        id="new-page-title"
                        x-ref="titleInput"
                        x-model="title"
                        @input="syncSlug()"
                        @keydown.enter="create()"
                        type="text"
                        placeholder="About us"
                        class="s-input"
                    >
                </div>

                <div class="mt-3">
                    <label for="new-page-slug" class="s-label">URL</label>
                    <div class="flex items-center overflow-hidden rounded-lg border border-line bg-raised transition-colors focus-within:border-accent">
                        <span class="border-r border-line px-2.5 font-mono text-xs text-faint">/</span>
                        <input
                            id="new-page-slug"
                            x-model="slug"
                            @input="slugEdited = true"
                            @keydown.enter="create()"
                            type="text"
                            placeholder="about-us"
                            class="h-8 w-full bg-transparent px-2.5 font-mono text-xs text-ink placeholder:text-faint focus:outline-none"
                        >
                    </div>
                </div>

                <p x-show="error" x-cloak x-text="error" class="mt-3 text-xs text-danger"></p>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="open = false" class="s-btn-ghost">Cancel</button>
                    <button @click="create()" :disabled="creating || !title.trim()" class="s-btn-primary">
                        <span x-show="!creating">Create page</span>
                        <span x-show="creating" x-cloak>Creating…</span>
                    </button>
                </div>
            </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Dev mode — section source editor                              --}}
    {{-- ============================================================ --}}
    @if(\Designer\Studio\Support\DevMode::enabled())
    <script>
        window.__studioMonacoAssets = {
            script: @js(\Designer\Studio\Support\StudioAssets::url('studio-monaco.js')),
            css: @js(\Designer\Studio\Support\StudioAssets::url('studio-monaco.css')),
            workers: {
                editor: @js(\Designer\Studio\Support\StudioAssets::url('monaco-editor-worker.js')),
                html: @js(\Designer\Studio\Support\StudioAssets::url('monaco-html-worker.js')),
                css: @js(\Designer\Studio\Support\StudioAssets::url('monaco-css-worker.js')),
                json: @js(\Designer\Studio\Support\StudioAssets::url('monaco-json-worker.js')),
            },
        };

        /* ------------------------------------------------------------------
           The Code view's workspace: the docked file tree and the editor
           pane both read and write this store. Monaco itself is not loaded
           until the first file is opened.
           ------------------------------------------------------------------ */
        /* Monaco's editor and models are large object graphs full of getters.
           Alpine deep-proxies anything it stores, which mangles (and can hang
           on) them — so the buffers live out here, and only plain, printable
           state goes in the store. */
        const studioCodeBuffers = { editor: null, models: {}, saved: {}, mounting: null };

        // Files written from outside the Code view: its open tabs catch up
        window.addEventListener('studio:files-changed', () => window.Alpine?.store('code')?.syncFromDisk());

        // A file edited and not saved lives only in this window: leaving it
        // (a reload, another page, closing the tab) asks first
        window.addEventListener('beforeunload', (event) => {
            if (!Object.values(window.Alpine?.store('code')?.dirty || {}).some(Boolean)) return;
            event.preventDefault();
            event.returnValue = '';
        });

        document.addEventListener('alpine:init', () => {
            Alpine.store('code', {
                base: @js(url(trim(config('studio.path', 'studio'), '/') . '/api/code')),

                host: null,          // the Monaco container, registered by the pane
                dirty: {},
                tabs: [],
                active: null,

                // The application's files, a folder at a time: the root
                // at boot, each folder's children when it is first opened
                nodes: [],
                // Every text file of the site, whether its folder has been
                // opened or not — what the palette's quick-open searches
                index: [],
                // The app root on disk (Copy path) and the folders the site
                // lives in (they, and what holds them, cannot be renamed)
                root: @js(rtrim(base_path(), '/')),
                siteRoots: @js(app(\Designer\Studio\Services\CodeWorkspace::class)->siteRoots()),
                openFolders: (() => {
                    try {
                        // A first visit opens on the site's own folder
                        return JSON.parse(localStorage.getItem('studio.code-folders') || 'null') || { resources: true, 'resources/designer': true };
                    } catch (e) { return {} }
                })(),

                booted: false,
                loading: false,
                treeGeneration: 0,   // bumped per reload, so a stale folder fetch lands nowhere
                saving: false,
                creating: false,
                busy: false,         // a tree write is in flight
                error: '',
                // The row the tree's actions act on: the last one clicked
                selected: null,
                // A file or folder being named before it exists:
                // { kind: 'file' | 'dir', parent, depth, value }
                draft: null,
                // The row being renamed in place, and its field
                renaming: null,
                renameValue: '',
                // The row whose Delete is waiting for a second click
                confirmDelete: null,
                // The context menu: where, and for which row (null: the tree itself)
                menu: { open: false, x: 0, y: 0, path: null },
                newSectionOpen: false,
                newName: '',
                newCategory: '',
                newLabel: '',

                /** Entering the Code view: load the tree, once. */
                boot() {
                    if (!this.booted) {
                        this.booted = true;
                        this.loadTree();
                    }
                },

                async loadTree() {
                    const generation = ++this.treeGeneration;
                    this.loading = true;
                    try {
                        const data = await this.fetchTree();
                        if (generation !== this.treeGeneration) return;
                        this.nodes = data.nodes;
                        this.index = data.index || [];
                        await this.expandOpen(generation);
                    } catch (e) {
                        this.error = e.message;
                    }
                    if (generation === this.treeGeneration) this.loading = false;
                },

                /** One tree request: one folder's entries (the root without `dir`). */
                async fetchTree(dir = null) {
                    const query = new URLSearchParams();
                    if (dir) query.set('dir', dir);
                    const response = await fetch(`${this.base}/tree?${query}`, { headers: { 'Accept': 'application/json' } });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not read the workspace.');
                    return data;
                },

                /** A lazy folder's children, fetched and slotted in right under it. */
                async loadChildren(path, generation) {
                    const folder = this.nodes.find((n) => n.path === path);
                    if (!folder?.lazy || folder.loading) return;
                    folder.loading = true;
                    try {
                        const data = await this.fetchTree(path);
                        if (generation !== this.treeGeneration) return;
                        this.nodes.splice(this.nodes.indexOf(folder) + 1, 0, ...data.nodes);
                        folder.lazy = false;
                    } catch (e) {
                        this.error = e.message;
                        // Closed, so expandOpen() doesn't retry it forever
                        this.openFolders = { ...this.openFolders, [path]: false };
                    } finally {
                        folder.loading = false;
                    }
                },

                /** Fetch every open, showing folder that is still lazy — a level at a time, until none are left. */
                async expandOpen(generation) {
                    while (generation === this.treeGeneration) {
                        const pending = this.visibleNodes.filter((n) => n.lazy && !n.loading && this.openFolders[n.path]);
                        if (!pending.length) return;
                        await Promise.all(pending.map((n) => this.loadChildren(n.path, generation)));
                    }
                },

                /**
                 * Flat list → tree: a node shows when every ancestor is open.
                 * A file or folder being named (`draft`) is a row of its own,
                 * first inside the folder it will land in.
                 */
                get visibleNodes() {
                    const shown = {};
                    const rows = this.nodes.filter((node) => {
                        const visible = node.depth === 0 || (shown[node.parent] && !!this.openFolders[node.parent]);
                        if (node.type === 'dir') shown[node.path] = visible;
                        return visible;
                    });

                    if (this.draft) {
                        const at = this.draft.parent ? rows.findIndex((n) => n.path === this.draft.parent) + 1 : 0;
                        rows.splice(at, 0, { type: 'draft', path: '\u0000draft', kind: this.draft.kind, depth: this.draft.depth, parent: this.draft.parent });
                    }

                    return rows;
                },

                node(path) { return path ? this.nodes.find((n) => n.path === path) || null : null },

                /** Where something new goes: the folder itself, a file's folder, or the root (null). */
                folderFor(path) {
                    const node = this.node(path);
                    if (!node) return null;
                    return node.type === 'dir' ? node.path : (node.parent || null);
                },

                /** A folder that has to stay where it is: a top-level one, or one the site stands on. */
                locked(node) {
                    return node?.type === 'dir'
                        && (node.depth === 0 || this.siteRoots.some((root) => root === node.path || root.startsWith(node.path + '/')));
                },

                toggleFolder(path) {
                    const open = !this.openFolders[path];
                    this.openFolders = { ...this.openFolders, [path]: open };
                    this.persistFolders();
                    // Opening a lazy folder fetches it, and any folders inside it left open last time
                    if (open) this.expandOpen(this.treeGeneration);
                },

                collapseAll() {
                    this.openFolders = {};
                    this.persistFolders();
                },

                persistFolders() {
                    try { localStorage.setItem('studio.code-folders', JSON.stringify(this.openFolders)) } catch (e) { /* private mode */ }
                },

                /** Open a folder and every folder above it, fetching what has not been read yet. */
                async reveal(dir) {
                    if (!dir) return;
                    let path = '';
                    for (const part of dir.split('/')) {
                        path = path ? path + '/' + part : part;
                        this.openFolders = { ...this.openFolders, [path]: true };
                        await this.loadChildren(path, this.treeGeneration);
                    }
                    this.persistFolders();
                },

                /** Read one folder again from disk (the whole tree without a path). */
                async reloadFolder(path = null) {
                    const folder = this.node(path);
                    if (!folder) return this.loadTree();
                    this.nodes = this.nodes.filter((n) => !n.path.startsWith(path + '/'));
                    this.node(path).lazy = true;
                    this.openFolders = { ...this.openFolders, [path]: true };
                    await this.expandOpen(this.treeGeneration);
                    // The palette's index of the site moves with it
                    this.fetchTree().then((data) => { this.index = data.index || this.index }).catch(() => {});
                },

                /* --- writes: create, rename, duplicate, delete ----------- */

                async request(method, endpoint, body) {
                    const response = await fetch(this.base + endpoint, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || (data.errors ? Object.values(data.errors).flat()[0] : null) || 'That did not work.');
                    }
                    return data;
                },

                // A change inside the site has already been re-read on the
                // server; the editor's own documents follow
                afterWrite(data) {
                    if (data.synced) window.Livewire?.dispatch('studio:code-saved');
                },

                /** New file / New folder: a row to type its name into, inside the selected folder. */
                async startCreate(kind, at = this.selected) {
                    this.closeMenu();
                    this.renaming = null;
                    this.confirmDelete = null;
                    const parent = this.folderFor(at);
                    if (this.node(parent)?.inert) return;
                    await this.reveal(parent);
                    this.draft = { kind, parent, depth: parent ? parent.split('/').length : 0, value: '' };
                },

                cancelCreate() { this.draft = null },

                async commitCreate() {
                    const draft = this.draft;
                    if (!draft || this.busy) return;
                    const name = draft.value.trim().replace(/^\/+|\/+$/g, '');
                    if (!name) { this.draft = null; return; }

                    this.busy = true;
                    try {
                        const data = await this.request('POST', '/file', { path: (draft.parent ? draft.parent + '/' : '') + name, type: draft.kind });
                        this.draft = null;
                        await this.reloadFolder(draft.parent);
                        this.selected = data.path;
                        if (data.type === 'file') await this.openFile(data.path);
                        else this.toggleFolder(data.path);
                        this.afterWrite(data);
                    } catch (e) {
                        // The row stays, so the name can be put right
                        window.Studio.toast(e.message, 'error', 5000);
                    }
                    this.busy = false;
                },

                startRename(path) {
                    this.closeMenu();
                    const node = this.node(path);
                    if (!node || node.inert || this.locked(node)) return;
                    this.draft = null;
                    this.confirmDelete = null;
                    this.renameValue = node.name;
                    this.renaming = path;
                },

                cancelRename() { this.renaming = null },

                async commitRename() {
                    const from = this.renaming;
                    const node = this.node(from);
                    if (!from || this.busy) return;
                    const name = this.renameValue.trim();
                    if (!node || !name || name === node.name) { this.renaming = null; return; }

                    this.busy = true;
                    try {
                        const data = await this.request('PATCH', '/file', { path: from, to: (node.parent ? node.parent + '/' : '') + name });
                        this.renaming = null;
                        (data.moved || []).forEach((move) => this.remap(move.from, move.to, move.to === data.path ? data.language : null));
                        await this.reloadFolder(node.parent);
                        this.selected = data.path;
                        this.afterWrite(data);
                    } catch (e) {
                        window.Studio.toast(e.message, 'error', 5000);
                    }
                    this.busy = false;
                },

                /**
                 * A path changed on disk: every open buffer at it — or under
                 * it, when it was a folder — moves with it, unsaved edits and all.
                 */
                remap(from, to, language = null) {
                    const moved = (path) => to + path.slice(from.length);
                    const under = (path) => path === from || path.startsWith(from + '/');

                    Object.keys(studioCodeBuffers.models).filter(under).forEach((path) => {
                        const next = moved(path);
                        const old = studioCodeBuffers.models[path];
                        const model = window.StudioMonaco.model(next, (path === from && language) || old.getLanguageId(), old.getValue());

                        studioCodeBuffers.models[next] = model;
                        studioCodeBuffers.saved[next] = studioCodeBuffers.saved[path];
                        delete studioCodeBuffers.models[path];
                        delete studioCodeBuffers.saved[path];

                        if (this.active === path) {
                            this.active = next;
                            studioCodeBuffers.editor?.setModel(model);
                        }

                        old.dispose();
                    });

                    this.tabs = this.tabs.map((tab) => under(tab.path) ? { path: moved(tab.path), name: moved(tab.path).split('/').pop(), display: moved(tab.path) } : tab);
                    this.dirty = Object.fromEntries(Object.entries(this.dirty).map(([path, value]) => [under(path) ? moved(path) : path, value]));
                    this.openFolders = Object.fromEntries(Object.entries(this.openFolders).map(([path, value]) => [under(path) ? moved(path) : path, value]));
                    this.persistFolders();
                },

                async duplicate(path) {
                    this.closeMenu();
                    const node = this.node(path);
                    if (!node || node.type !== 'file' || node.inert || this.busy) return;

                    this.busy = true;
                    try {
                        const data = await this.request('POST', '/duplicate', { path });
                        await this.reloadFolder(node.parent);
                        this.selected = data.path;
                        await this.openFile(data.path);
                        this.afterWrite(data);
                    } catch (e) {
                        window.Studio.toast(e.message, 'error', 5000);
                    }
                    this.busy = false;
                },

                /** Delete: armed by the first ask, done by the second (the row shows the pair of buttons). */
                askDelete(path) {
                    this.closeMenu();
                    const node = this.node(path);
                    if (!node || node.inert || this.locked(node)) return;
                    this.renaming = null;
                    this.confirmDelete = path;
                },

                async remove(path) {
                    this.confirmDelete = null;
                    const node = this.node(path);
                    if (!node || this.busy) return;

                    this.busy = true;
                    try {
                        const data = await this.request('DELETE', '/file', { path });

                        // A section is a pair — both halves leave together;
                        // a folder takes every open buffer under it
                        (data.removed || [path]).forEach((gone) => {
                            Object.keys(studioCodeBuffers.models)
                                .filter((open) => open === gone || open.startsWith(gone + '/'))
                                .forEach((open) => {
                                    studioCodeBuffers.models[open]?.dispose?.();
                                    delete studioCodeBuffers.models[open];
                                    delete studioCodeBuffers.saved[open];
                                    this.dirty = { ...this.dirty, [open]: false };
                                    this.closeTab(open);
                                });
                        });

                        if (this.selected === path) this.selected = null;
                        await this.reloadFolder(node.parent);
                        this.afterWrite(data);
                        window.Studio.toast(node.type === 'dir' ? `Deleted the folder “${node.name}”` : `Deleted “${node.name}”`);
                    } catch (e) {
                        window.Studio.toast(e.message, 'error', 5000);
                    }
                    this.busy = false;
                },

                /** The path on the clipboard: from the project root, or from the disk's. */
                copyPath(path, absolute = false) {
                    this.closeMenu();
                    const text = absolute ? this.root + (path ? '/' + path : '') : (path || '.');
                    const done = () => window.Studio.toast(absolute ? 'Path copied' : 'Relative path copied');
                    // navigator.clipboard only exists in secure contexts
                    // (https/localhost); plain-http dev domains fall back
                    const fallback = () => {
                        const el = document.createElement('textarea');
                        el.value = text;
                        el.setAttribute('readonly', '');
                        el.style.position = 'fixed';
                        el.style.opacity = '0';
                        document.body.appendChild(el);
                        el.select();
                        try { document.execCommand('copy') && done(); } finally { el.remove(); }
                    };
                    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done).catch(fallback);
                    else fallback();
                },

                /* --- the context menu ------------------------------------ */

                openMenu(event, path = null) {
                    this.confirmDelete = null;
                    if (path) this.selected = path;
                    this.menu = { open: true, x: event.clientX, y: event.clientY, path };
                },

                closeMenu() {
                    if (this.menu.open) this.menu = { ...this.menu, open: false };
                },

                /** Load Monaco on demand and keep one editor for every tab. */
                async mount() {
                    if (!this.host) throw new Error('The code editor is not ready yet.');

                    if (!studioCodeBuffers.mounting) {
                        studioCodeBuffers.mounting = (async () => {
                            const wrapper = await window.Studio.codeEditor(this.host, { language: 'html' });
                            studioCodeBuffers.editor = wrapper.editor;

                            // Code → canvas: moving the caret inside a section
                            // file haloes the text that line renders. Bails
                            // immediately for every file that isn't a section
                            // source, so a keystroke in any other buffer costs
                            // one regex test and nothing else.
                            studioCodeBuffers.editor.onDidChangeCursorPosition((event) => {
                                const path = this.active || '';
                                const match = path.match(/^resources\/designer\/views\/components\/(.+)\.blade\.php$/);

                                if (!match) return;

                                window.dispatchEvent(new CustomEvent('studio:to-iframe', {
                                    detail: {
                                        type: 'studio:highlight-field',
                                        source: match[1],
                                        line: event.position.lineNumber,
                                    },
                                }));
                            });

                            wrapper.editor.onDidChangeModelContent(() => this.track());
                        })().catch((error) => {
                            studioCodeBuffers.mounting = null; // let the next open retry
                            throw error;
                        });
                    }

                    await studioCodeBuffers.mounting;
                    studioCodeBuffers.editor.layout();
                    return studioCodeBuffers.editor;
                },

                async openFile(path) {
                    this.error = '';
                    this.confirmDelete = null;

                    // An open buffer wins over disk — unsaved edits survive
                    // closing and reopening a tab.
                    if (studioCodeBuffers.models[path]) {
                        this.addTab(path);
                        this.activate(path);
                        return;
                    }

                    try {
                        const response = await fetch(`${this.base}/file?path=${encodeURIComponent(path)}`, { headers: { 'Accept': 'application/json' } });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not open that file.');

                        await this.mount();

                        const key = data.path || path;

                        if (!studioCodeBuffers.models[key]) {
                            studioCodeBuffers.models[key] = window.StudioMonaco.model(key, data.language, data.contents);
                            studioCodeBuffers.saved[key] = data.contents;
                            this.dirty = { ...this.dirty, [key]: false };
                        }

                        this.addTab(key, data.display);
                        this.activate(key);
                        this.selected = key;
                    } catch (e) {
                        this.error = e.message;
                    }
                },

                /**
                 * Open a file and put the caret on one line — the landing
                 * half of ⌥-clicking a field on the canvas.
                 */
                async openFileAt(path, line) {
                    await this.openFile(path);

                    // openFile() swallows its own errors (sets `error`, no
                    // rethrow) — if the target failed to open (deleted,
                    // renamed, no permission), `active` is left pointing at
                    // whatever file was already open. Bail rather than move
                    // that unrelated file's caret.
                    if (this.active !== path) return;

                    const editor = studioCodeBuffers.editor;

                    if (!editor || !line) return;

                    editor.revealLineInCenter(line);
                    editor.setPosition({ lineNumber: line, column: 1 });
                    editor.setSelection({ startLineNumber: line, startColumn: 1, endLineNumber: line + 1, endColumn: 1 });
                    editor.focus();
                },

                addTab(path, display = null) {
                    if (this.tabs.some((tab) => tab.path === path)) return;
                    this.tabs = [...this.tabs, { path, name: path.split('/').pop(), display: display || path }];
                },

                activate(path) {
                    this.active = path;
                    const model = studioCodeBuffers.models[path];
                    if (studioCodeBuffers.editor && model) {
                        studioCodeBuffers.editor.setModel(model);
                        studioCodeBuffers.editor.layout();
                        studioCodeBuffers.editor.focus();
                    }
                    this.showInTree(path);
                },

                /**
                 * The file in the editor is the row lit in the tree: however
                 * it was opened (a tab, ⌘K, a field's source line), its
                 * folders open and its row comes into view.
                 */
                async showInTree(path) {
                    this.selected = path;
                    await this.reveal(path.split('/').slice(0, -1).join('/'));
                    setTimeout(() => {
                        if (this.active !== path) return;
                        document.querySelector('.s-tree [data-row=' + JSON.stringify(path) + ']')?.scrollIntoView({ block: 'center' });
                    }, 60);
                },

                /**
                 * Files changed underneath the editor (the Assistant wrote
                 * them, or the section-source modal did). The tree is read
                 * again, and every open file that has no unsaved edits takes
                 * what is on disk now. One that does is left alone — the
                 * edits are the user's — and named, because saving it would
                 * write over the change.
                 */
                async syncFromDisk() {
                    if (this.booted) this.loadTree();

                    const kept = [];

                    for (const path of Object.keys(studioCodeBuffers.models)) {
                        try {
                            const response = await fetch(`${this.base}/file?path=${encodeURIComponent(path)}`, { headers: { 'Accept': 'application/json' } });
                            const data = await response.json().catch(() => ({}));
                            if (!response.ok || !data.success || data.contents === studioCodeBuffers.saved[path]) continue;

                            if (this.dirty[path]) {
                                kept.push(path.split('/').pop());
                                continue;
                            }

                            studioCodeBuffers.saved[path] = data.contents;
                            studioCodeBuffers.models[path].setValue(data.contents);
                            this.dirty = { ...this.dirty, [path]: false };
                        } catch (e) { /* the file is gone or unreadable: its tab keeps what it had */ }
                    }

                    if (kept.length) {
                        window.Studio.toast({
                            title: kept.length === 1 ? `${kept[0]} changed on disk` : `${kept.length} open files changed on disk`,
                            description: 'Your unsaved edits are kept. Saving will overwrite the newer version.',
                        }, 'warning', 9000);
                    }
                },

                closeTab(path) {
                    this.tabs = this.tabs.filter((tab) => tab.path !== path);
                    if (this.active !== path) return;
                    const next = this.tabs[this.tabs.length - 1];
                    if (next) {
                        this.activate(next.path);
                    } else {
                        this.active = null;
                        studioCodeBuffers.editor?.setModel(null);
                    }
                },

                track() {
                    const path = this.active;
                    const model = studioCodeBuffers.models[path];
                    if (!path || !model) return;
                    this.dirty = { ...this.dirty, [path]: model.getValue() !== studioCodeBuffers.saved[path] };
                },

                async save() {
                    const path = this.active;
                    if (!path || this.saving || !studioCodeBuffers.models[path]) return;

                    this.saving = true;
                    this.error = '';

                    try {
                        const contents = studioCodeBuffers.models[path].getValue();
                        const response = await fetch(`${this.base}/file`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ path, contents }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not save that file.');

                        studioCodeBuffers.saved[path] = contents;
                        this.dirty = { ...this.dirty, [path]: false };

                        window.Studio.toast(data.synced
                            ? 'Saved — live on the site, and the editor is up to date'
                            : 'Saved');
                        // A site file re-syncs first (EditorPanel reloads the canvas after)
                        if (data.synced) window.Livewire?.dispatch('studio:code-saved');
                        else window.dispatchEvent(new CustomEvent('studio:refresh-preview'));
                    } catch (e) {
                        this.error = e.message;
                    }

                    this.saving = false;
                },

                async createSection() {
                    if (this.creating) return;
                    this.creating = true;
                    this.error = '';

                    try {
                        const response = await fetch(`${this.base}/section`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                name: this.newName,
                                category: this.newCategory || 'content',
                                label: this.newLabel || '',
                            }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not create that section.');

                        this.newSectionOpen = false;
                        this.newName = '';
                        this.newCategory = '';
                        this.newLabel = '';
                        await this.reveal(data.path.split('/').slice(0, -1).join('/'));
                        await this.reloadFolder(data.path.split('/').slice(0, -1).join('/'));
                        await this.openFile(data.path);
                        window.Studio.toast(`Created ${data.name} — it is in the section library now`);
                    } catch (e) {
                        this.error = e.message;
                    }

                    this.creating = false;
                },
            });
        });
    </script>
    <div
            x-data="{
                open: false,
                ref: null,
                title: '',
                tab: 'html',
                loading: false,
                saving: false,
                error: '',
                paths: { html: '', yaml: '' },
                editors: null,
                _editorsPromise: null,
                base: @js(url(trim(config('studio.path', 'studio'), '/') . '/api/dev/components')),

                ensureEditors() {
                    // Assigned synchronously so overlapping openEditor() calls
                    // share one in-flight boot instead of double-creating
                    // Monaco instances on the same hosts.
                    if (!this._editorsPromise) {
                        this._editorsPromise = (async () => {
                            this.editors = {
                                html: await window.Studio.codeEditor(this.$refs.htmlHost, { language: 'html' }),
                                yaml: await window.Studio.codeEditor(this.$refs.yamlHost, { language: 'yaml' }),
                            };
                        })().catch((error) => {
                            this._editorsPromise = null; // allow retry after a failed load
                            throw error;
                        });
                    }
                    return this._editorsPromise;
                },

                async openEditor(detail) {
                    this.ref = detail.ref;
                    this.title = detail.title || detail.ref;
                    this.tab = 'html';
                    this.error = '';
                    this.open = true;
                    this.loading = true;
                    window.Studio.codeModalOpen = true;

                    try {
                        const response = await fetch(`${this.base}/${this.ref}`, { headers: { 'Accept': 'application/json' } });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not load the source files.');
                        await this.$nextTick();
                        await this.ensureEditors();
                        this.editors.html.setValue(data.html);
                        this.editors.yaml.setValue(data.yaml);
                        this.paths = data.paths;
                    } catch (e) {
                        this.error = e.message;
                    }
                    this.loading = false;
                },

                close() {
                    this.open = false;
                    window.Studio.codeModalOpen = false;
                },

                async save() {
                    if (this.saving || this.loading || !this.ref || !this.editors) return;
                    this.saving = true;
                    this.error = '';
                    try {
                        const response = await fetch(`${this.base}/${this.ref}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                html: this.editors.html.getValue(),
                                yaml: this.editors.yaml.getValue(),
                            }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not save the source files.');
                        window.Studio.toast('Section source saved — every section using it is updated');
                        window.dispatchEvent(new CustomEvent('studio:files-changed', { detail: { paths: Object.values(this.paths) } }));
                        // EditorPanel re-syncs, then reloads the canvas
                        window.Livewire?.dispatch('studio:code-saved');
                    } catch (e) {
                        this.error = e.message;
                    }
                    this.saving = false;
                }
            }"
            @studio:open-code-editor.window="openEditor($event.detail)"
            @keydown.escape.window="close()"
            @keydown.window="if (open && ($event.metaKey || $event.ctrlKey) && ($event.key === 's' || $event.key === 'S')) { $event.preventDefault(); save(); }"
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[90] flex items-center justify-center p-4 lg:p-8"
            role="dialog"
            aria-modal="true"
            aria-label="Edit section source code"
        >
            <div class="s-modal-backdrop" x-show="open" x-transition.opacity.duration.200ms @click="close()"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-[0.97] translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                class="s-modal flex h-[720px] max-h-[90vh] w-[1080px] max-w-full flex-col overflow-hidden"
            >
                {{-- Header --}}
                <div class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-[15px] font-semibold text-ink" x-text="title"></h2>
                        <p class="truncate font-mono text-[11px] text-faint" x-text="tab === 'html' ? paths.html : paths.yaml"></p>
                    </div>

                    <div class="s-seg ml-auto">
                        <button class="s-seg-btn !w-14 text-[11.5px] font-medium" :class="tab === 'html' && 'is-active'" @click="tab = 'html'">Blade</button>
                        <button class="s-seg-btn !w-14 text-[11.5px] font-medium" :class="tab === 'yaml' && 'is-active'" @click="tab = 'yaml'">YAML</button>
                    </div>

                    <button @click="close()" class="s-icon-btn" title="Close (Esc)">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                    </button>
                </div>

                {{-- Editors --}}
                <div class="relative min-h-0 flex-1">
                    <div x-show="loading" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-panel/70">
                        <svg class="h-5 w-5 animate-spin text-soft" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1.5A10.5 10.5 0 0 0 1.5 12H4Z"/></svg>
                    </div>
                    <div x-ref="htmlHost" x-show="tab === 'html'" class="s-code-pane h-full"></div>
                    <div x-ref="yamlHost" x-show="tab === 'yaml'" class="s-code-pane h-full"></div>
                </div>

                {{-- Footer --}}
                <div class="flex shrink-0 items-center gap-3 border-t border-line px-4 py-2.5">
                    <p class="min-w-0 flex-1 truncate text-[11.5px] text-faint">
                        <span x-show="!error">Sections must stay inside the supported Blade subset — see <span class="font-mono">docs/authoring-sections.md</span>. Saving updates every page using this section.</span>
                        <span x-show="error" x-cloak class="text-danger" x-text="error"></span>
                    </p>
                    <button @click="close()" class="s-btn-ghost">Cancel</button>
                    <button @click="save()" :disabled="saving || loading" class="s-btn-accent">
                        <span x-show="!saving">Save</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                        <span class="s-kbd !border-line-strong !bg-transparent !text-white/80">⌘S</span>
                    </button>
                </div>
            </div>
    </div>
    @endif
</x-studio::layouts.app>
