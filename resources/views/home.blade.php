@php
    $homeSlug = \Designer\Studio\Support\SiteUrls::homeSlug();
    $liveUrl = \Designer\Studio\Support\SiteUrls::pageUrl($page->slug);
    // Where publishing writes this page (index.blade.php for the home page)
    $pageFile = 'resources/designer/views/pages/' . ($page->slug === $homeSlug ? 'index' : $page->slug) . '.blade.php';
    $totalComponents = $library->flatten(1)->count();
    // The server-side gate. Code mode needs this AND the user's dev-mode toggle.
    $devModeAvailable = \Designer\Studio\Support\DevMode::enabled();
@endphp

<x-studio::layouts.app>
    <x-slot:title>{{ $page->title }} — Designer Studio</x-slot:title>

    {{-- ============================================================ --}}
    {{-- Topbar                                                        --}}
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

            document.addEventListener('alpine:init', () => {
                Alpine.store('studio', {
                    device: 'desktop',
                    widths: { desktop: '100%', tablet: '768px', mobile: '390px' },
                    // A panel is open (the floating surface is showing). Off
                    // on first run: the site is the screen until you ask.
                    sidebar: localStorage.getItem('studio.sidebar') === '1',
                    toggleSidebar() {
                        this.sidebar = !this.sidebar;
                        localStorage.setItem('studio.sidebar', this.sidebar ? '1' : '0');
                    },
                    closePanel() {
                        if (!this.sidebar) return;
                        this.sidebar = false;
                        localStorage.setItem('studio.sidebar', '0');
                    },
                    // The rail: which panel the floating surface shows.
                    rail: (s => ['sections', 'pages', 'content', 'media', 'assistant'].includes(s) ? s : 'sections')(localStorage.getItem('studio.rail')),
                    setRail(name, force = false) {
                        // Every dock button toggles its own panel; a forced
                        // call (picker, palette, inspector) always opens it.
                        if (!force && this.rail === name && this.sidebar) {
                            this.closePanel();
                            return;
                        }
                        this.rail = name;
                        localStorage.setItem('studio.rail', name);
                        if (!this.sidebar) this.toggleSidebar();
                        window.dispatchEvent(new CustomEvent('studio:rail', { detail: { name } }));
                    },
                    // The inspector is the Sections panel in its selected
                    // state — the toolbar's Edit-fields button lands here.
                    openInspector() {
                        this.setRail('sections', true);
                    },
                    // Which frame the open panel takes: the two data screens
                    // are sheets, everything else a popover on the dock.
                    get frame() { return ['content', 'media'].includes(this.rail) ? 'sheet' : 'popover' },
                    get floatWidth() { return this.rail === 'assistant' ? 380 : 320 },

                    /* --- the dock ----------------------------------------
                       Where the floating bar sits: which window edge, and how
                       far along it (0..1, the dock's centre). Slides freely along the edge. */
                    dock: (() => {
                        try {
                            const saved = JSON.parse(localStorage.getItem('studio.dock') || 'null');
                            if (saved && ['bottom', 'left', 'right', 'top'].includes(saved.edge)) {
                                return { edge: saved.edge, along: Math.min(1, Math.max(0, Number(saved.along) || 0.5)) };
                            }
                        } catch (e) { /* fall through */ }
                        return { edge: 'bottom', along: 0.5 };
                    })(),
                    setDock(edge, along = null) {
                        if (!['bottom', 'left', 'right', 'top'].includes(edge)) return;
                        this.dock = { edge, along: along === null ? 0.5 : Math.min(1, Math.max(0, along)) };
                        localStorage.setItem('studio.dock', JSON.stringify(this.dock));
                        window.dispatchEvent(new CustomEvent('studio:dock', { detail: this.dock }));
                    },
                    dockHidden: localStorage.getItem('studio.dock-hidden') === '1',
                    toggleDock() {
                        this.dockHidden = !this.dockHidden;
                        localStorage.setItem('studio.dock-hidden', this.dockHidden ? '1' : '0');
                    },
                    // Code mode's file tree column (inside the code pane)
                    filesOpen: localStorage.getItem('studio.files') !== '0',
                    toggleFiles() {
                        this.filesOpen = !this.filesOpen;
                        localStorage.setItem('studio.files', this.filesOpen ? '1' : '0');
                    },
                    // Editor chrome theme: dark by default, 'light' mirrors the Sites builder
                    theme: document.documentElement.classList.contains('studio-light') ? 'light' : 'dark',
                    toggleTheme() {
                        this.theme = this.theme === 'light' ? 'dark' : 'light';
                        localStorage.setItem('studio.theme', this.theme);
                        document.documentElement.classList.toggle('studio-light', this.theme === 'light');
                        // Monaco themes are global and set in JS, not CSS
                        window.StudioMonaco?.syncTheme();
                    },
                    devMode: localStorage.getItem('studio.devmode') !== '0',
                    toggleDevMode() {
                        this.devMode = !this.devMode;
                        localStorage.setItem('studio.devmode', this.devMode ? '1' : '0');
                        window.dispatchEvent(new CustomEvent('studio:to-iframe', {
                            detail: { type: 'studio:devmode', on: this.devMode },
                        }));
                        // Code mode is a developer surface — turning dev mode
                        // off can't leave the canvas hidden behind an editor.
                        if (!this.devMode && this.mode === 'code') this.setMode('edit');
                    },

                    /* --- Preview / Edit / Code ------------------------------
                       Preview is the canvas as the visitor sees it (links
                       navigate, no overlay); Edit is the selectable canvas;
                       Code is the file tree + editor, and only exists when the
                       server gate AND the dev-mode toggle are both on. */
                    devModeAvailable: @js($devModeAvailable),
                    get codeAvailable() { return this.devModeAvailable && this.devMode },
                    mode: (() => {
                        const saved = localStorage.getItem('studio.mode');
                        const codeOk = @js($devModeAvailable) && localStorage.getItem('studio.devmode') !== '0';
                        if (saved === 'edit' || saved === 'preview') return saved;
                        if (saved === 'code' && codeOk) return 'code';
                        return 'preview';
                    })(),
                    setMode(name) {
                        if (name === 'code' && !this.codeAvailable) return;
                        this.mode = name;
                        localStorage.setItem('studio.mode', name);
                        window.dispatchEvent(new CustomEvent('studio:to-iframe', {
                            detail: { type: 'studio:mode', mode: name },
                        }));
                        window.dispatchEvent(new CustomEvent('studio:mode', { detail: { mode: name } }));
                    },
                    // Whether the canvas is on screen at all: Code mode hides it
                    // unless the split is open.
                    get canvasVisible() { return this.mode !== 'code' || this.codeSplit },

                    // Code mode is full-width by default; the split brings the
                    // live preview back beside the editor.
                    codeSplit: localStorage.getItem('studio.code-split') === '1',
                    toggleCodeSplit() {
                        this.codeSplit = !this.codeSplit;
                        localStorage.setItem('studio.code-split', this.codeSplit ? '1' : '0');
                    },
                    codeSize: (n => (n >= 20 && n <= 80) ? n : 50)(parseFloat(localStorage.getItem('studio.code-size'))),
                    setCodeSize(percent) {
                        this.codeSize = Math.min(80, Math.max(20, percent));
                        localStorage.setItem('studio.code-size', String(this.codeSize));
                    },
                });
            });

            // Position a popover (menu, publish) beside its dock button,
            // opening away from the dock's edge and clamped to the window.
            window.StudioDock = {
                anchor(pop, button, width) {
                    const edge = Alpine.store('studio').dock.edge;
                    const b = button.getBoundingClientRect();
                    const gap = 10, pad = 12;
                    const W = window.innerWidth, H = window.innerHeight;
                    const h = pop.offsetHeight || 320;
                    let left, top;
                    if (edge === 'bottom') { left = b.left; top = b.top - gap - h; }
                    else if (edge === 'top') { left = b.left; top = b.bottom + gap; }
                    else if (edge === 'left') { left = b.right + gap; top = b.top; }
                    else { left = b.left - gap - width; top = b.top; }
                    left = Math.max(pad, Math.min(left, W - width - pad));
                    top = Math.max(pad, Math.min(top, H - h - pad));
                    return `left:${Math.round(left)}px; top:${Math.round(top)}px; width:${width}px`;
                },
            };

            window.addEventListener('studio:toast', (event) => {
                const { message, type, action } = event.detail;

                let toastAction = null;

                if (action?.dispatch) {
                    toastAction = { label: action.label, onClick: () => window.Livewire?.dispatch(action.dispatch) };
                } else if (action?.reload) {
                    toastAction = { label: action.label, onClick: () => window.location.reload() };
                }

                window.Studio?.toast(message, type || 'success', undefined, toastAction);
            });
        </script>

        {{-- Publish --}}
        <div class="relative" x-data="{
            open: false,
            copied: false,
            draftMode: @js($draftMode),
            status: @js($publishStatus),
            busy: false,
            popStyle: '',

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
                class="s-dock-publish"
                x-data="{ state: 'idle' }"
                @studio:status.window="state = $event.detail.state"
                :title="state === 'saving' ? 'Saving…' : state === 'error' ? 'Offline — changes are not being saved' : 'All changes saved'"
                aria-label="Publish"
            >
                <span class="s-dock-status" :class="{ 'is-saving': state === 'saving', 'is-error': state === 'error' }"></span>
                <span class="s-dock-publish-label">Publish</span>
                <span x-show="draftMode && status?.dirty" x-cloak x-transition.opacity class="s-dock-dirty"></span>
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
                class="s-pop fixed z-50 w-80 p-3"
                :style="popStyle"
                x-effect="open; $nextTick(() => { if (open) popStyle = window.StudioDock.anchor($el, $el.previousElementSibling, 320) })"
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
    {{-- Menu — the layout renders it first in the topbar             --}}
    {{-- ============================================================ --}}
    <x-slot:menu>
        <div
            class="relative"
            x-data="{
                open: false,
                popStyle: '',

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
            <button @click="open = !open" class="s-dock-btn is-menu s-logo-btn" :class="open && 'is-open'" data-tip="Menu" aria-label="Menu">
                <svg class="s-logo-btn-logo h-[15px] w-auto text-ink" viewBox="0 0 72 75" fill="none"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"/></svg>
                <svg class="s-logo-btn-menu h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" d="M4 6.5h16M4 12h16M4 17.5h16"/></svg>
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
                class="s-pop s-pop-inverse fixed z-50 w-60"
                :style="popStyle"
                x-effect="open; $nextTick(() => { if (open) popStyle = window.StudioDock.anchor($el, $el.previousElementSibling, 240) })"
            >
                <div class="flex items-center gap-2.5 px-2.5 pb-2 pt-2.5 -translate-y-0.5">
                    <svg class="h-[17px] w-auto -translate-y-0.5 text-ink" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 72 75" fill="none"><path fill="currentColor" fill-rule="evenodd" d="M50 49.822C62.393 48.34 72 37.792 72 25 72 11.193 60.807 0 47 0S22 11.193 22 25H5a5 5 0 0 0-5 5v40a5 5 0 0 0 5 5h40a5 5 0 0 0 5-5V49.822ZM47 50c1.015 0 2.016-.06 3-.178V30a5 5 0 0 0-5-5H22c0 13.807 11.193 25 25 25Z" clip-rule="evenodd"></path></svg>
                    <span class="text-[13px] font-semibold text-ink">Designer Studio</span>
                </div>
        
                <div class="s-divider mb-1"></div>
        
                <button @click="open = false; window.dispatchEvent(new CustomEvent('studio:open-create-page'))" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    New page…
                </button>
                <button @click="open = false; window.Livewire?.dispatch('studio:new-layout')" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    New layout…
                </button>
                <button @click="duplicatePage()" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    Duplicate this page
                </button>
        
                <div class="s-divider my-1"></div>
                @if(\Designer\Studio\Support\DevMode::enabled())
                    <button @click="$store.studio.toggleDevMode()" class="s-menu-item justify-between" title="Edit the site's source files — sections, layouts, data — from the editor">
                        <span class="flex items-center gap-2.5">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd"/></svg>
                            Dev mode
                        </span>
                        <span class="s-chip" :class="$store.studio.devMode && '!border-accent/50 !text-accent'" x-text="$store.studio.devMode ? 'On' : 'Off'"></span>
                    </button>
                @endif
                <button @click="$store.studio.toggleTheme()" class="s-menu-item justify-between" title="Switch between the dark and light editor chrome">
                    <span class="flex items-center gap-2.5">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg>
                        Light mode
                    </span>
                    <span class="s-chip" :class="$store.studio.theme === 'light' && '!border-accent/50 !text-accent'" x-text="$store.studio.theme === 'light' ? 'On' : 'Off'"></span>
                </button>
                {{-- Canvas width --}}
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span class="flex items-center gap-2.5">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><rect x="1.75" y="2.25" width="12.5" height="11.5" rx="2.25"/><path d="M5.5 13.75v-2.5m5 2.5v-2.5"/></svg>
                        Canvas width
                    </span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Canvas width">
                        @foreach(['desktop' => 'Desktop', 'tablet' => 'Tablet — 768px', 'mobile' => 'Mobile — 390px'] as $value => $label)
                            <button
                                type="button"
                                class="flex h-6 cursor-pointer items-center justify-center rounded-md px-1.5 text-[11px] transition-colors duration-150"
                                :class="$store.studio.device === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.device = '{{ $value }}'"
                                role="radio"
                                :aria-checked="$store.studio.device === '{{ $value }}'"
                                title="{{ $label }}"
                            >{{ ucfirst($value) }}</button>
                        @endforeach
                    </span>
                </div>
                {{-- Dock placement — a row of little window diagrams --}}
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span class="flex items-center gap-2.5">
                        @include('studio::partials.activity-bar-glyph', ['position' => 'bottom'])
                        Dock
                    </span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Dock position">
                        @foreach(['bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right', 'top' => 'Top'] as $value => $label)
                            <button
                                type="button"
                                class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md transition-colors duration-150"
                                :class="$store.studio.dock.edge === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.setDock('{{ $value }}')"
                                role="radio"
                                :aria-checked="$store.studio.dock.edge === '{{ $value }}'"
                                title="{{ $label }}"
                                aria-label="{{ $label }}"
                            >
                                @include('studio::partials.activity-bar-glyph', ['position' => $value])
                            </button>
                        @endforeach
                    </span>
                </div>
                <div class="s-divider my-1"></div>
                {{-- Browser-style navigation --}}
                <div class="flex items-center gap-1 px-1.5 py-0.5">
                    <button type="button" class="s-menu-item flex-1 !justify-center" @click="open = false; history.back()" title="Back">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 5.5 8 12l6.5 6.5"/></svg>
                        Back
                    </button>
                    <button type="button" class="s-menu-item flex-1 !justify-center" @click="open = false; history.forward()" title="Forward">
                        Forward
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9.5 5.5 16 12l-6.5 6.5"/></svg>
                    </button>
                    <button type="button" class="s-menu-item flex-1 !justify-center" @click="open = false; window.dispatchEvent(new CustomEvent('studio:refresh-preview'))" title="Reload the preview">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 3.75V6.9a.6.6 0 0 1-.6.6h-3.15"/></svg>
                        Reload
                    </button>
                </div>
                @if($liveUrl)
                    <a href="{{ $liveUrl }}" target="_blank" class="s-menu-item">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        View live site
                    </a>
                @endif
            </div>
        </div>
    </x-slot:menu>

    {{-- ============================================================ --}}
    {{-- Sidebar — one panel per rail item                             --}}
    {{-- ============================================================ --}}
    <x-slot:sidebar>
        <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rail === 'sections'">
            <livewire:studio::editor-panel :page-slug="$page->slug" />
        </div>
        @if(\Designer\Studio\Support\DevMode::enabled())
            <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rail === 'assistant'" x-cloak>
                <livewire:studio::assistant-panel :page-slug="$page->slug" />
            </div>
        @endif
        <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rail === 'pages'" x-cloak>
            <livewire:studio::pages-panel :page-slug="$page->slug" />
        </div>
        <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rail === 'content'" x-cloak>
            <livewire:studio::content-panel />
        </div>
        <div x-data class="flex h-full min-h-0 flex-col" x-show="$store.studio.rail === 'media'" x-cloak>
            <livewire:studio::media-panel />
        </div>
    </x-slot:sidebar>

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
                    { label: 'New layout…', hint: 'Create', run: () => window.Livewire?.dispatch('studio:new-layout') },
                    { label: 'Preview mode', hint: 'Mode', when: studio.mode !== 'preview', run: () => studio.setMode('preview') },
                    { label: 'Edit mode', hint: 'Mode', when: studio.mode !== 'edit', run: () => studio.setMode('edit') },
                    { label: 'Code mode', hint: 'Mode', when: studio.codeAvailable && studio.mode !== 'code', run: () => studio.setMode('code') },
                    { label: studio.codeSplit ? 'Hide the preview split' : 'Show the preview beside the code', hint: 'Code', when: studio.mode === 'code', run: () => studio.toggleCodeSplit() },
                    @if($draftMode)
                    { label: 'Publish…', hint: 'Site', run: () => window.dispatchEvent(new CustomEvent('studio:open-publish')) },
                    @endif
                    { label: 'Refresh the preview', hint: 'Canvas', run: () => window.dispatchEvent(new CustomEvent('studio:refresh-preview')) },
                    { label: 'Open in a new tab', hint: 'Canvas', run: () => window.open(@js($draftMode ? route('studio.preview.page', ['slug' => $page->slug]) : ($liveUrl ?? url('/'))), '_blank', 'noopener') },
                    { label: 'Sections panel', hint: 'Panel', run: () => studio.setRail('sections', true) },
                    { label: 'Pages panel', hint: 'Panel', run: () => studio.setRail('pages', true) },
                    { label: 'Content panel', hint: 'Panel', run: () => studio.setRail('content', true) },
                    { label: 'Media panel', hint: 'Panel', run: () => studio.setRail('media', true) },
                    @if($devModeAvailable)
                    { label: 'Assistant panel', hint: 'Panel', run: () => studio.setRail('assistant', true) },
                    { label: studio.filesOpen ? 'Hide the file tree' : 'Show the file tree', hint: 'Code', when: studio.mode === 'code', run: () => studio.toggleFiles() },
                    @endif
                    { label: studio.sidebar ? 'Close the panel' : 'Open the panel', hint: 'Layout', run: () => studio.toggleSidebar() },
                    { label: 'Dock: bottom', hint: 'Layout', when: studio.dock.edge !== 'bottom', run: () => studio.setDock('bottom') },
                    { label: 'Dock: left', hint: 'Layout', when: studio.dock.edge !== 'left', run: () => studio.setDock('left') },
                    { label: 'Dock: right', hint: 'Layout', when: studio.dock.edge !== 'right', run: () => studio.setDock('right') },
                    { label: 'Dock: top', hint: 'Layout', when: studio.dock.edge !== 'top', run: () => studio.setDock('top') },
                    { label: studio.dockHidden ? 'Show the dock' : 'Hide the dock', hint: 'Layout', run: () => studio.toggleDock() },
                    { label: 'Preview: desktop', hint: 'Device', run: () => studio.device = 'desktop' },
                    { label: 'Preview: tablet', hint: 'Device', run: () => studio.device = 'tablet' },
                    { label: 'Preview: mobile', hint: 'Device', run: () => studio.device = 'mobile' },
                    { label: studio.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode', hint: 'Appearance', run: () => studio.toggleTheme() },
                    @if($devModeAvailable)
                    { label: studio.devMode ? 'Turn developer mode off' : 'Turn developer mode on', hint: 'Developer', run: () => studio.toggleDevMode() },
                    @endif
                ].filter((command) => command.when !== false);

                @if($devModeAvailable)
                // Quick-open: while Code mode is on, the workspace files join
                // the list so ⌘K doubles as a file switcher.
                if (studio.mode === 'code') {
                    ($store.code.nodes || []).filter((node) => node.type === 'file').forEach((node) => {
                        all.push({ label: node.path, hint: 'File', run: () => $store.code.openFile(node.path) });
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
                    placeholder="Search commands…"
                    aria-label="Search commands"
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
    {{-- Editor notices (security / storage warnings)                  --}}
    {{-- ============================================================ --}}
    @if(!empty($notices))
        <div class="pointer-events-none absolute inset-x-0 top-3 z-40 flex flex-col items-center gap-2 px-4">
            @foreach($notices as $notice)
                <div
                    x-data="{ show: {{ $notice['dismissible'] ? "localStorage.getItem('studio.notice.{$notice['id']}') !== '1'" : 'true' }} }"
                    x-show="show"
                    class="pointer-events-auto flex max-w-2xl items-center gap-2.5 rounded-xl border px-3.5 py-2.5 shadow-[0_12px_32px_-8px_rgba(0,0,0,0.5)] backdrop-blur-md {{ $notice['tone'] === 'danger' ? 'border-danger/40 bg-danger/15' : 'border-warn/40 bg-warn/10' }}"
                >
                    <svg class="h-4 w-4 shrink-0 {{ $notice['tone'] === 'danger' ? 'text-danger' : 'text-warn' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    <span class="text-[12.5px] leading-snug text-ink/90">{{ $notice['text'] }}</span>
                    @if($notice['dismissible'])
                        <button
                            @click="show = false; localStorage.setItem('studio.notice.{{ $notice['id'] }}', '1')"
                            class="s-icon-btn !h-6 !w-6 shrink-0"
                            title="Dismiss"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Canvas                                                        --}}
    {{-- ============================================================ --}}
    {{-- Code mode takes the canvas's slot; the split gives half of it back.
         Both fill the canvas edge to edge as square frames. --}}
    <div class="s-canvas flex h-full w-full min-w-0" x-data>
        @if($devModeAvailable)
            @include('studio::partials.code-pane')

            {{-- Drag seam between the code pane and the preview --}}
            <div
                x-show="$store.studio.mode === 'code' && $store.studio.codeSplit"
                x-cloak
                class="s-code-seam"
                @mousedown.prevent="
                    const surface = $el.parentElement;
                    const move = (event) => {
                        const box = surface.getBoundingClientRect();
                        $store.studio.setCodeSize(((event.clientX - box.left) / box.width) * 100);
                    };
                    const stop = () => {
                        document.removeEventListener('mousemove', move);
                        document.removeEventListener('mouseup', stop);
                        document.body.classList.remove('select-none');
                    };
                    document.body.classList.add('select-none');
                    document.addEventListener('mousemove', move);
                    document.addEventListener('mouseup', stop);
                "
                role="separator"
                aria-label="Resize the code pane"
            ></div>
        @endif

        <div
            class="min-w-0 flex-1 overflow-auto"
            x-show="$store.studio.canvasVisible"
        >
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
                                {{ str_replace('-', ' ', $categoryName) }}
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
           Code mode's workspace: the file tree in the sidebar and the editor
           pane both read and write this store. Monaco itself is not loaded
           until the first file is opened.
           ------------------------------------------------------------------ */
        /* Monaco's editor and models are large object graphs full of getters.
           Alpine deep-proxies anything it stores, which mangles (and can hang
           on) them — so the buffers live out here, and only plain, printable
           state goes in the store. */
        const studioCodeBuffers = { editor: null, models: {}, saved: {}, mounting: null };

        document.addEventListener('alpine:init', () => {
            Alpine.store('code', {
                base: @js(url(trim(config('studio.path', 'studio'), '/') . '/api/code')),

                host: null,          // the Monaco container, registered by the pane
                dirty: {},
                tabs: [],
                active: null,

                nodes: [],
                // 'designer' — just the site (resources/designer and
                // public/designer); 'laravel' — the whole app, the site's
                // folders tinted.
                view: localStorage.getItem('studio.code-view') === 'laravel' ? 'laravel' : 'designer',
                openFolders: (() => {
                    try { return JSON.parse(localStorage.getItem('studio.code-folders') || '{}') } catch (e) { return {} }
                })(),

                booted: false,
                loading: false,
                saving: false,
                creating: false,
                error: '',
                confirmDelete: null,
                newSectionOpen: false,
                newName: '',
                newCategory: '',
                newLabel: '',

                /** Entering Code mode: show the tree and take the sidebar. */
                boot() {
                    if (!this.booted) {
                        this.booted = true;
                        this.loadTree();
                    }
                },

                setView(view) {
                    if (this.view === view) return;
                    this.view = view;
                    localStorage.setItem('studio.code-view', view);
                    this.loadTree();
                },

                async loadTree() {
                    this.loading = true;
                    try {
                        const response = await fetch(`${this.base}/tree?view=${this.view}`, { headers: { 'Accept': 'application/json' } });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not read the workspace.');
                        this.nodes = data.nodes;
                        // The Designer view is small enough to open down to its
                        // two designer/ folders; the Laravel view starts
                        // collapsed, like any file explorer.
                        if (this.view === 'designer') {
                            this.nodes.filter((n) => n.depth <= 1 && n.type === 'dir').forEach((n) => {
                                if (!(n.path in this.openFolders)) this.openFolders[n.path] = true;
                            });
                            this.persistFolders();
                        }
                    } catch (e) {
                        this.error = e.message;
                    }
                    this.loading = false;
                },

                /** Flat list → tree: a node shows when every ancestor is open. */
                get visibleNodes() {
                    const shown = {};
                    return this.nodes.filter((node) => {
                        const visible = node.depth === 0 || (shown[node.parent] && !!this.openFolders[node.parent]);
                        if (node.type === 'dir') shown[node.path] = visible;
                        return visible;
                    });
                },

                toggleFolder(path) {
                    this.openFolders = { ...this.openFolders, [path]: !this.openFolders[path] };
                    this.persistFolders();
                },

                persistFolders() {
                    try { localStorage.setItem('studio.code-folders', JSON.stringify(this.openFolders)) } catch (e) { /* private mode */ }
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

                        // A file has one path in both views, so opening it
                        // from either lands on the same buffer.
                        const key = data.path || path;

                        if (!studioCodeBuffers.models[key]) {
                            studioCodeBuffers.models[key] = window.StudioMonaco.model(key, data.language, data.contents);
                            studioCodeBuffers.saved[key] = data.contents;
                            this.dirty = { ...this.dirty, [key]: false };
                        }

                        this.addTab(key, data.display);
                        this.activate(key);
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
                        window.dispatchEvent(new CustomEvent('studio:refresh-preview'));
                        if (data.synced) window.Livewire?.dispatch('studio:code-saved');
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
                        await this.loadTree();
                        await this.openFile(data.path);
                        window.Studio.toast(`Created ${data.name} — it is in the section library now`);
                    } catch (e) {
                        this.error = e.message;
                    }

                    this.creating = false;
                },

                async deleteFile(path) {
                    this.confirmDelete = null;
                    this.error = '';

                    try {
                        const response = await fetch(`${this.base}/file`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ path }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) throw new Error(data.message || 'Could not delete that file.');

                        // A section is a pair — both halves leave together
                        (data.removed || [path]).forEach((gone) => {
                            studioCodeBuffers.models[gone]?.dispose?.();
                            delete studioCodeBuffers.models[gone];
                            delete studioCodeBuffers.saved[gone];
                            this.closeTab(gone);
                        });

                        await this.loadTree();
                        window.Studio.toast('Deleted');
                    } catch (e) {
                        this.error = e.message;
                    }
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
                        window.dispatchEvent(new CustomEvent('studio:refresh-preview'));
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
