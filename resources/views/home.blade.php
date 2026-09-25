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
            // The pinned toolbar's page switcher reads this list
            window.__studioPageList = @js($pages->map(fn ($p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'path' => $p->slug === $homeSlug ? '/' : '/' . $p->slug,
                'home' => $p->slug === $homeSlug,
                'current' => $p->slug === $page->slug,
            ])->values());

            document.addEventListener('alpine:init', () => {
                Alpine.store('studio', {
                    device: 'desktop',
                    widths: { desktop: '100%', tablet: '768px', mobile: '390px' },
                    // A panel is open (the floating surface is showing). On
                    // for the first run: the Sidebar workspace opens with
                    // its panel docked beside the site.
                    sidebar: localStorage.getItem('studio.sidebar') !== '0',
                    toggleSidebar() {
                        this.sidebar = !this.sidebar;
                        localStorage.setItem('studio.sidebar', this.sidebar ? '1' : '0');
                    },
                    closePanel() {
                        if (!this.sidebar) return;
                        this.sidebar = false;
                        localStorage.setItem('studio.sidebar', '0');
                    },
                    // A floating popover behaves like one: pressing the site
                    // behind it closes it (the canvas iframe reports its own
                    // presses as studio:canvas-pointerdown). Nothing covers
                    // the site to catch the press, so it keeps scrolling.
                    // Armed, the Assistant's pick tool owns that press — the
                    // panel stays. A docked column is part of the layout, and
                    // a sheet already has its scrim. The Sections panel stays
                    // too: clicking the site is how you pick what it shows.
                    dismissFloating() {
                        if (window.Studio?.picking) return;
                        // The floating chat's conversation folds like a popover
                        if (this.chatFloating && this.chatOpen) this.setChatOpen(false);
                        if (!this.sidebar || this.docked || this.frame !== 'popover') return;
                        if (this.rail === 'sections') return;
                        this.closePanel();
                    },
                    // The rail: which panel the floating surface shows.
                    rail: (s => ['sections', 'pages', 'content', 'media', 'assistant'].includes(s) && !(s === 'assistant' && !(@js($devModeAvailable) && localStorage.getItem('studio.devmode') !== '0')) ? s : (@js($devModeAvailable) && localStorage.getItem('studio.devmode') !== '0' ? 'assistant' : 'sections'))(localStorage.getItem('studio.rail')),
                    setRail(name, force = false) {
                        // Floating, the chat is not a rail panel: its button
                        // opens the conversation over the site instead
                        if (name === 'assistant' && !this.chatAvailable) return;
                        if (name === 'assistant' && this.chatFloating) {
                            force ? this.setChatOpen(true) : this.toggleChat();
                            return;
                        }
                        // Pinned to the right the chat is always showing: its
                        // button closes it (back to the composer)
                        if (name === 'assistant' && this.chatSide) {
                            if (!force) this.setChatPlace('float');
                            return;
                        }
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

                    /* --- the chat ------------------------------------------
                       Where the Assistant lives: in the panel, like every
                       other rail item, or floating — a composer over the
                       bottom of the site that is always there, with the
                       conversation opening upward on demand. Floating, it is
                       independent of the rail, so the inspector and the chat
                       can be open at the same time. Only where the Assistant
                       exists at all (the server's dev-mode gate). */
                    // The Assistant is a developer surface — it runs a coding
                    // agent against the app — so it exists only in developer
                    // mode: the server's gate AND the menu's switch. Off, the
                    // editor is the one a marketing team uses.
                    get chatAvailable() { return this.developer },
                    // The preference (on unless turned off: where the
                    // Assistant exists the first-run arrangement is the
                    // Composer workspace) and whether it applies right now.
                    // Leaving developer mode puts the chat away without
                    // forgetting where it was.
                    /* Three homes: `panel` (a rail item like the others),
                       `float` (the composer card over the bottom of the
                       site) and `side` (a full-height column pinned to the
                       right of the site, the site pushed over by its width).
                       The old boolean key is read once for anyone upgrading. */
                    chatPlace: (() => {
                        const saved = localStorage.getItem('studio.chat-place');
                        if (['panel', 'float', 'side'].includes(saved)) return saved;
                        return localStorage.getItem('studio.chat-float') === '1' ? 'float' : 'panel';
                    })(),
                    get chatFloating() { return this.chatPlace === 'float' && this.chatAvailable },
                    get chatSide() { return this.chatPlace === 'side' && this.chatAvailable },
                    // Out of the panel, either way: the aside is then a ghost
                    get chatOut() { return this.chatFloating || this.chatSide },
                    chatOpen: localStorage.getItem('studio.chat-open') === '1',
                    chatBusy: false,   // a turn is streaming
                    /* Leaving the side column for the composer is two beats:
                       the column slides out to the right while the site
                       widens back (chatSideOut holds it in place for that),
                       and only then does the composer card appear below. */
                    chatSideOut: false,
                    chatSideTimer: null,
                    setChatPlace(place) {
                        if (!this.chatAvailable || !['panel', 'float', 'side'].includes(place)) return;
                        if (place === this.chatPlace && !this.chatSideOut) return;
                        clearTimeout(this.chatSideTimer);
                        const apply = () => {
                            const from = this.chatPlace;
                            this.chatPlace = place;
                            this.chatSideOut = false;
                            localStorage.setItem('studio.chat-place', place);
                            if (place === 'panel') {
                                this.setChatOpen(false);
                            } else {
                                // The float or the column takes over from the panel
                                if (this.sidebar && this.rail === 'assistant') this.closePanel();
                            }
                            // Back from the column the card lands folded, just the composer
                            if (place === 'float' && from === 'side') this.setChatOpen(false);
                            window.dispatchEvent(new CustomEvent('studio:chat', { detail: { floating: this.chatFloating, place } }));
                            window.dispatchEvent(new CustomEvent('studio:reflow'));
                        };
                        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        if (this.chatPlace === 'side' && place === 'float' && !reduced) {
                            this.chatSideOut = true;
                            this.chatSideTimer = setTimeout(apply, 340);
                            return;
                        }
                        apply();
                    },
                    setChatFloating(on) { this.setChatPlace(on ? 'float' : 'panel') },
                    // The composer's sidebar button and the column's close
                    toggleChatSide() { this.setChatPlace(this.chatSide ? 'float' : 'side') },
                    // The column's width, set by the seam on its inner edge
                    chatSideWidth: (n => (n >= 320 && n <= 640) ? n : 400)(parseInt(localStorage.getItem('studio.chat-side-width'), 10)),
                    setChatSideWidth(px) {
                        this.chatSideWidth = Math.round(Math.min(640, Math.max(320, px)));
                        localStorage.setItem('studio.chat-side-width', String(this.chatSideWidth));
                    },
                    // A floating toolbar on the right edge would sit on the
                    // column: the column stands in from it (12 + 46 + 12,
                    // less the gutter the row already keeps)
                    get chatSideClear() {
                        return this.dock.edge === 'right' && !this.dock.pinned && !this.dockHidden ? 65 : 0;
                    },
                    // What the app row reserves on its right for the column
                    get chatSideSpace() {
                        return this.chatSide && !this.chatSideOut ? this.chatSideWidth + this.chatSideClear : 0;
                    },
                    setChatOpen(on) {
                        this.chatOpen = !!on;
                        localStorage.setItem('studio.chat-open', this.chatOpen ? '1' : '0');
                    },
                    toggleChat() { this.setChatOpen(!this.chatOpen) },
                    // Bring the chat forward wherever it lives, caret in it (⌘J)
                    focusChat() {
                        if (!this.chatAvailable) return;
                        if (this.chatFloating) this.setChatOpen(true);
                        else if (!this.chatSide) this.setRail('assistant', true);
                        window.dispatchEvent(new CustomEvent('studio:focus-chat'));
                    },
                    // The floating card's distance from the bottom edge: it
                    // never sits on the dock, pinned or floating
                    get chatBottom() {
                        if (this.dockHidden) return 16;
                        if (this.dock.pinned) return this.dock.edge === 'bottom' ? this.barSize + 14 : 16;
                        return this.dock.edge === 'bottom' ? 46 + 24 : 16;
                    },

                    /* --- joined ---------------------------------------------
                       The toolbar and the composer as one object: a single
                       shell with one radius around both, the toolbar tucked
                       behind the composer with a strip of it showing, rising
                       out from behind it when you point at either.

                       `chatJoined` is the preference; `joined` is whether it
                       can apply right now. Everything that would break the
                       shape — pinning the toolbar, docking the chat, dragging
                       to a side edge — therefore un-joins on its own and
                       joins again by itself, and nothing has to clear the
                       flag. The numbers are the CSS's too (--joined-*). */
                    chatJoined: @js($devModeAvailable) && localStorage.getItem('studio.chat-joined') !== '0',
                    joinedBar: 46,    // the row at full height
                    joinedPeek: 14,   // what clears the composer at rest
                    // The row's box, computed by the chat card (the only
                    // thing that knows the composer's height) and consumed
                    // by the dock's own place(), so one element still has
                    // exactly one writer. Null whenever it is not joined.
                    joinedBox: null,
                    setChatJoined(on) {
                        if (!this.chatAvailable) return;
                        this.chatJoined = !!on;
                        localStorage.setItem('studio.chat-joined', this.chatJoined ? '1' : '0');
                        window.dispatchEvent(new CustomEvent('studio:reflow'));
                    },
                    get joined() {
                        return this.chatJoined && this.chatAvailable && this.chatFloating
                            && !this.dock.pinned && this.dockHorizontal;
                    },
                    /* Pointer or caret on either surface brings the row
                       out. The row and the composer butt together, so there
                       is no dead zone between them: the delay only covers
                       the instant the pointer spends crossing the seam, and
                       arriving on the other surface cancels the close
                       outright. One timer, shared by both. */
                    joinedPeeked: false,
                    joinTimer: null,
                    joinIn() {
                        clearTimeout(this.joinTimer);
                        this.joinedPeeked = true;
                    },
                    joinOut() {
                        clearTimeout(this.joinTimer);
                        this.joinTimer = setTimeout(() => { this.joinedPeeked = false }, 150);
                    },
                    menuOpen: false,
                    publishOpen: false,
                    /* Joined, the row sits under the composer (it tucks
                       behind it), which also puts everything it opens — the
                       menu, its flyouts, Publish — under the composer and
                       under an open panel. While one of those is up the row
                       is out, clear of the composer, so it can take the
                       dock's usual place on top (is-raised). */
                    get dockPopOpen() { return this.menuOpen || this.publishOpen },
                    /* The grip can only be grabbed while the row is out, and
                       the drag then puts a shield over the whole window —
                       over the row too, which fires its mouseleave. Without
                       this the row would collapse out from under the very
                       drag that is carrying it. */
                    joinedDragging: false,
                    // Held out while anything is open off the row — a panel
                    // popover, the menu — or while it is being dragged, any
                    // of which would otherwise slide away under you.
                    get joinedOut() {
                        if (!this.joined || this.dockHidden) return false;
                        if (this.dockPopOpen || this.joinedDragging) return true;
                        if (this.sidebar && this.frame === 'popover') return true;
                        return this.joinedPeeked;
                    },
                    // How much of the row clears the composer right now
                    get joinedShown() {
                        if (!this.joined) return 0;
                        if (this.dockHidden) return 0;
                        return this.joinedOut ? this.joinedBar : this.joinedPeek;
                    },

                    /* --- workspaces ----------------------------------------
                       Arrangements of the same chrome, one click each. A
                       workspace is three answers — where the toolbar is,
                       where the chat is, whether the panel is docked — and
                       never the mode or the canvas width. The menu shows
                       which one you are in (none, when you have moved things
                       yourself). Listed from the most chat-led to the most
                       panel-led; the two with `chat` only exist where the
                       Assistant does.

                       The first-run arrangement is one of these too, and it
                       comes from the defaults rather than from a call here:
                       Sidebar — the dock pinned left, the panel open and
                       docked (the Assistant where it exists, Sections
                       elsewhere), the chat in the column rather than
                       floating. */
                    workspaces: {
                        composer: { label: 'Composer', hint: 'The toolbar and the chat as one object', edge: 'bottom', pinned: false, chat: true, panel: false },
                        chat: { label: 'Chat focused', hint: 'Header bar, the chat floating over the site', edge: 'top', pinned: true, chat: true, panel: false },
                        minimal: { label: 'Minimal', hint: 'A floating toolbar and nothing else', edge: 'bottom', pinned: false, chat: false, panel: false },
                        classic: { label: 'Classic', hint: 'Header bar with the panel docked beside the site', edge: 'top', pinned: true, chat: false, panel: true },
                        sidebar: { label: 'Sidebar', hint: 'A rail on the left with the panel beside it', edge: 'left', pinned: true, chat: false, panel: true },
                    },
                    applyWorkspace(name) {
                        const to = this.workspaces[name];
                        if (!to || (to.chat && !this.chatAvailable)) return;
                        if (!to.panel && this.sidebar) this.closePanel();
                        this.setDock(to.edge, 0.5, to.pinned);
                        this.setChatFloating(to.chat);
                        // Joining is a preference the other workspaces leave
                        // alone — pinned or docked it simply cannot apply
                        if (name === 'composer') this.setChatJoined(true);
                        if (to.panel) this.setRail('sections', true);
                        if (this.dockHidden) this.toggleDock();
                        window.dispatchEvent(new CustomEvent('studio:workspace', { detail: { name } }));
                    },
                    // Read back from the real state. An open popover or a
                    // closed column is a moment, not an arrangement, so the
                    // panel is not part of the match.
                    get workspace() {
                        const { edge, pinned } = this.dock;
                        if (this.chatSide) return null;
                        if (this.chatFloating) {
                            if (this.joined) return 'composer';
                            return pinned && edge === 'top' ? 'chat' : null;
                        }
                        if (!pinned) return 'minimal';
                        return { top: 'classic', left: 'sidebar' }[edge] ?? null;
                    },
                    // Which frame the open panel takes: the two data screens
                    // are sheets, everything else a popover on the dock.
                    get frame() { return ['content', 'media'].includes(this.rail) ? 'sheet' : 'popover' },
                    get floatWidth() { return this.rail === 'assistant' ? 380 : 320 },

                    /* --- the dock ----------------------------------------
                       Where the toolbar sits: which window edge, how far
                       along it (0..1, the dock's centre — it slides freely),
                       and whether it is pinned. Floating, it is a pill over
                       the site; pinned, it is a flush rail on that edge and
                       the site is pushed over by exactly its size, so nothing
                       is ever covered. Pinned top or bottom the bar runs the
                       full width and carries the page switcher and device
                       widths — that is the "header bar". */
                    dock: (() => {
                        try {
                            const saved = JSON.parse(localStorage.getItem('studio.dock') || 'null');
                            if (saved && ['bottom', 'left', 'right', 'top'].includes(saved.edge)) {
                                return {
                                    edge: saved.edge,
                                    along: Math.min(1, Math.max(0, Number(saved.along) || 0.5)),
                                    pinned: saved.pinned === true,
                                };
                            }
                        } catch (e) { /* fall through */ }
                        // First run: the Sidebar workspace — a rail pinned left
                        return { edge: 'left', along: 0.5, pinned: true };
                    })(),
                    setDock(edge, along = null, pinned = null) {
                        if (!['bottom', 'left', 'right', 'top'].includes(edge)) return;
                        this.dock = {
                            edge,
                            along: along === null ? 0.5 : Math.min(1, Math.max(0, along)),
                            pinned: pinned === null ? this.dock.pinned : !!pinned,
                        };
                        localStorage.setItem('studio.dock', JSON.stringify(this.dock));
                        window.dispatchEvent(new CustomEvent('studio:dock', { detail: this.dock }));
                    },
                    // Pin to the current edge / let it float again
                    togglePin() {
                        this.setDock(this.dock.edge, this.dock.along, !this.dock.pinned);
                    },
                    // Pinned bar sizes — the CSS rails are drawn to these
                    get dockHorizontal() { return this.dock.edge === 'top' || this.dock.edge === 'bottom' },
                    get barSize() { return this.dockHorizontal ? 48 : 52 },
                    // The space the pinned rail takes: padding on the app
                    // root, on the rail's edge only. A hidden dock (⌘.)
                    // gives the space back.
                    get appInsets() {
                        if (!this.dock.pinned || this.dockHidden) return {};
                        const side = { top: 'paddingTop', bottom: 'paddingBottom', left: 'paddingLeft', right: 'paddingRight' }[this.dock.edge];
                        return { [side]: this.barSize + 'px' };
                    },
                    // A pinned toolbar docks the open panel as a column
                    // beside it (sheets stay sheets): next to a left or
                    // right rail, on the left under a top or bottom bar.
                    // Only while a panel is open: with the chat floating the
                    // aside stays mounted as a ghost, and a ghost is no column
                    get docked() { return this.sidebar && this.dock.pinned && this.frame === 'popover' },
                    // The column's slot: while the toolbar is pinned it stays
                    // in the row open or shut, so closing the panel collapses
                    // it to nothing (width, then gone) instead of cutting it
                    get column() { return this.dock.pinned && this.frame === 'popover' },
                    get panelSide() { return this.dock.edge === 'right' ? 'right' : 'left' },
                    panelWidth: (n => (n >= 260 && n <= 560) ? n : 320)(parseInt(localStorage.getItem('studio.panel-width'), 10)),
                    setPanelWidth(px) {
                        this.panelWidth = Math.round(Math.min(560, Math.max(260, px)));
                        localStorage.setItem('studio.panel-width', String(this.panelWidth));
                    },
                    dockHidden: localStorage.getItem('studio.dock-hidden') === '1',
                    toggleDock() {
                        this.dockHidden = !this.dockHidden;
                        localStorage.setItem('studio.dock-hidden', this.dockHidden ? '1' : '0');
                    },

                    /* --- View ------------------------------------------------
                       Which optional parts of the toolbar are drawn, from the
                       menu's View submenu. Only these switches live here — the
                       canvas width, dock position and theme keep their own
                       state and the submenu just edits it. A key missing from
                       the saved object takes its default, so a new switch
                       never needs a migration. */
                    viewDefaults: { grip: true, pin: true, pages: true, devices: true, nav: false, live: true, tips: true },
                    view: (() => {
                        const defaults = { grip: true, pin: true, pages: true, devices: true, nav: false, live: true, tips: true };
                        try {
                            const saved = JSON.parse(localStorage.getItem('studio.view') || 'null');
                            if (saved && typeof saved === 'object') {
                                return Object.fromEntries(Object.keys(defaults).map((key) => [key, typeof saved[key] === 'boolean' ? saved[key] : defaults[key]]));
                            }
                        } catch (e) { /* fall through */ }
                        return defaults;
                    })(),
                    toggleView(key) {
                        if (!(key in this.viewDefaults)) return;
                        this.view = { ...this.view, [key]: !this.view[key] };
                        localStorage.setItem('studio.view', JSON.stringify(this.view));
                    },
                    resetView() {
                        this.view = { ...this.viewDefaults };
                        localStorage.removeItem('studio.view');
                    },
                    get viewCustomized() {
                        return Object.keys(this.viewDefaults).some((key) => this.view[key] !== this.viewDefaults[key]);
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
                    /* --- developer mode -------------------------------------
                       One switch, two editors. On: Code mode, the Assistant,
                       edit-code buttons, source lines on the canvas, field
                       bindings, collection schemas, a page's raw head HTML.
                       Off: the editor a marketing team uses — the page, its
                       fields, pages, content rows, media, Publish — with
                       nothing that reads as code. `developer` is THE gate for
                       chrome (the switch, where the server allows one at
                       all); the canvas mirrors it as html.studio-devmode. */
                    devMode: localStorage.getItem('studio.devmode') !== '0',
                    get developer() { return this.devModeAvailable && this.devMode },
                    toggleDevMode() {
                        this.devMode = !this.devMode;
                        localStorage.setItem('studio.devmode', this.devMode ? '1' : '0');
                        // The Assistant goes with it: a panel showing it has
                        // nothing left to show
                        if (!this.devMode && this.sidebar && this.rail === 'assistant') this.closePanel();
                        if (!this.devMode && this.rail === 'assistant') this.rail = 'sections';
                        window.dispatchEvent(new CustomEvent('studio:reflow'));
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
                    get codeAvailable() { return this.developer },
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

        {{-- This page on the live site, in a new tab --}}
        <a
            href="{{ $liveUrl }}"
            target="_blank"
            rel="noopener"
            class="s-dock-btn"
            x-show="$store.studio.view.live"
            data-tip="Open the live page"
            aria-label="Open the live page in a new tab"
        >
            <svg class="h-[16px] w-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 13.5v5A1.5 1.5 0 0 1 16.5 20h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6h5"/>
                <path d="M14 4h6v6M20 4l-8.5 8.5"/>
            </svg>
        </a>

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
        {{-- Joined, the row stays out — and on top — while this is up --}}
        x-effect="$store.studio.publishOpen = open"
        @studio:status.window="if ($event.detail.state === 'saved' && status) status.dirty = true"
        @studio:open-publish.window="open = true; refreshStatus()"
        >
            <button
                @click="open = !open; if (open) refreshStatus()"
                class="s-dock-publish"
                :class="open && 'is-open'"
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
                // The open flyout — 'view' or 'workspace'. One position string
                // per flyout: a string :style rewrites the attribute, so a
                // shared one would wipe the display:none x-show wrote on the
                // other flyout every time this one was placed.
                sub: null,
                subStyles: { view: '', workspace: '' },
                subTimer: null,

                // A submenu flies out beside the menu, on whichever side has
                // room, its first row level with the row that opened it.
                placeSub() {
                    if (!this.sub) return;
                    const menu = this.$refs.menu.getBoundingClientRect();
                    const row = this.$refs[this.sub + 'Row'].getBoundingClientRect();
                    const width = 300, gap = 2, pad = 12;
                    const h = this.$refs[this.sub].offsetHeight || 420;
                    let left = menu.right + gap;
                    if (left + width > window.innerWidth - pad) left = menu.left - gap - width;
                    left = Math.max(pad, left);
                    const top = Math.max(pad, Math.min(row.top - 5, window.innerHeight - h - pad));
                    this.subStyles[this.sub] = `left:${Math.round(left)}px; top:${Math.round(top)}px; width:${width}px`;
                },
                showSub(name, delay = 0) {
                    clearTimeout(this.subTimer);
                    this.subTimer = setTimeout(() => {
                        this.sub = name;
                        this.$nextTick(() => this.placeSub());
                    }, delay);
                },
                hideSub(delay = 0) {
                    clearTimeout(this.subTimer);
                    this.subTimer = setTimeout(() => { this.sub = null }, delay);
                },

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
            @click.outside="open = false; sub = null"
            @keydown.escape.window="if (sub) sub = null; else open = false"
            {{-- Joined, the row must stay out while the menu is up: the
                 pointer leaves the row for the flyout, which is a fixed
                 sibling, and the row would otherwise slide away under it --}}
            x-effect="if (!open) sub = null; $store.studio.menuOpen = open"
            @studio:dock-moved.window="if (open) { popStyle = window.StudioDock.anchor($refs.menu, $refs.menu.previousElementSibling, 272); if (sub) $nextTick(() => placeSub()) }"
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
                class="s-pop s-pop-inverse fixed z-50 w-[272px]"
                :style="popStyle"
                x-ref="menu"
                x-effect="open; $nextTick(() => { if (open) popStyle = window.StudioDock.anchor($el, $el.previousElementSibling, 272) })"
                {{-- Pointing at any other row closes an open flyout --}}
                @mouseover="if ($event.target.closest('.s-menu-item:not([data-sub-row])')) hideSub(120)"
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
                <button x-show="$store.studio.developer" @click="open = false; window.Livewire?.dispatch('studio:new-layout')" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                    New layout…
                </button>
                <button @click="duplicatePage()" class="s-menu-item">
                    <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z"/><path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z"/></svg>
                    Duplicate this page
                </button>
        
                <div class="s-divider my-1"></div>
                @if(\Designer\Studio\Support\DevMode::enabled())
                    <button @click="$store.studio.toggleDevMode()" class="s-menu-item justify-between" role="switch" :aria-checked="$store.studio.devMode" title="On: Code mode, the Assistant, source lines and field bindings. Off: the editor your marketing team uses — content only, nothing that reads as code.">
                        <span class="flex items-center gap-2.5">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 1 0 1.06L2.56 10l3.72 3.72a.75.75 0 0 1-1.06 1.06L.97 10.53a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Zm7.44 0a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 0 1 0-1.06ZM11.377 2.011a.75.75 0 0 1 .612.867l-2.5 14.5a.75.75 0 0 1-1.478-.255l2.5-14.5a.75.75 0 0 1 .866-.612Z" clip-rule="evenodd"/></svg>
                            Developer mode
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
                {{-- View: canvas width, toolbar position and which toolbar
                     parts are drawn, in a submenu beside this one --}}
                <button
                    type="button"
                    class="s-menu-item justify-between"
                    :class="sub === 'view' && 'bg-wash !text-ink'"
                    data-sub-row
                    x-ref="viewRow"
                    @mouseenter="showSub('view', 80)"
                    @mouseleave="if (!$event.relatedTarget?.closest?.('[data-sub-menu]')) hideSub(250)"
                    @click="sub === 'view' ? hideSub() : showSub('view')"
                    @keydown.arrow-right.prevent="showSub('view'); $nextTick(() => $refs.view.querySelector('button')?.focus())"
                    aria-haspopup="menu"
                    :aria-expanded="sub === 'view'"
                >
                    <span class="flex items-center gap-2.5">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd"/></svg>
                        View
                    </span>
                    <svg class="h-3.5 w-3.5 shrink-0 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                </button>
                {{-- Workspace: arrangements of the chrome, in a flyout like View --}}
                <button
                    type="button"
                    class="s-menu-item justify-between"
                    :class="sub === 'workspace' && 'bg-wash !text-ink'"
                    data-sub-row
                    x-ref="workspaceRow"
                    @mouseenter="showSub('workspace', 80)"
                    @mouseleave="if (!$event.relatedTarget?.closest?.('[data-sub-menu]')) hideSub(250)"
                    @click="sub === 'workspace' ? hideSub() : showSub('workspace')"
                    @keydown.arrow-right.prevent="showSub('workspace'); $nextTick(() => $refs.workspace.querySelector('button')?.focus())"
                    aria-haspopup="menu"
                    :aria-expanded="sub === 'workspace'"
                >
                    <span class="flex items-center gap-2.5">
                        @include('studio::partials.activity-bar-glyph', ['position' => 'chat'])
                        Workspace
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="text-[11px] text-faint" x-text="$store.studio.workspace ? $store.studio.workspaces[$store.studio.workspace].label : 'Custom'"></span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-faint" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                    </span>
                </button>
                @if($liveUrl)
                    <div class="s-divider my-1"></div>
                    <a href="{{ $liveUrl }}" target="_blank" class="s-menu-item">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd"/></svg>
                        View live site
                    </a>
                @endif
            </div>

            {{-- The View submenu. A sibling of the menu, not inside it: the
                 menu's enter transition transforms it, and a transformed
                 ancestor would anchor this fixed panel to itself. --}}
            @php
                $viewSwitches = [
                    'grip' => ['Drag handle', 'Drag the toolbar to any edge'],
                    'pin' => ['Pin button', 'Pin the toolbar flush to its edge'],
                    'nav' => ['Back, Forward & Reload', 'Browser-style buttons in the toolbar'],
                    'live' => ['Open live page', 'A button that opens this page in a new tab'],
                    'pages' => ['Page switcher', 'In the pinned top or bottom bar'],
                    'devices' => ['Canvas width buttons', 'In the pinned top or bottom bar'],
                    'tips' => ['Tooltips', 'Button labels on hover'],
                ];
            @endphp
            <div
                x-show="open && sub === 'view'"
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="s-pop s-pop-inverse fixed z-50"
                :style="subStyles.view"
                x-ref="view"
                data-sub-menu
                role="menu"
                aria-label="View"
                @mouseenter="clearTimeout(subTimer)"
                @mouseleave="if (!$event.relatedTarget?.closest?.('[data-sub-row]')) hideSub(250)"
                @keydown.arrow-left.prevent="sub = null; $refs.viewRow.focus()"
            >
                <p class="s-microlabel px-2.5 pb-1 pt-2">Canvas</p>
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span class="flex items-center gap-2.5">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><rect x="1.75" y="2.25" width="12.5" height="11.5" rx="2.25"/><path d="M5.5 13.75v-2.5m5 2.5v-2.5"/></svg>
                        Width
                    </span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Canvas width">
                        @foreach(['desktop' => 'Desktop (⌥1)', 'tablet' => 'Tablet — 768px (⌥2)', 'mobile' => 'Mobile — 390px (⌥3)'] as $value => $label)
                            <button
                                type="button"
                                class="flex h-6 cursor-pointer items-center justify-center rounded-md px-2 text-[11.5px] transition-colors duration-150"
                                :class="$store.studio.device === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.device = '{{ $value }}'"
                                role="radio"
                                :aria-checked="$store.studio.device === '{{ $value }}'"
                                title="{{ $label }}"
                            >{{ ucfirst($value) }}</button>
                        @endforeach
                    </span>
                </div>

                <div class="s-divider my-1"></div>

                <p class="s-microlabel px-2.5 pb-1 pt-1.5">Toolbar</p>
                {{-- Floating, or pinned to an edge as a rail. Pinned top is the header bar. --}}
                <div class="flex items-center justify-between gap-2 py-1 pl-2.5 pr-1.5 text-[13px] text-soft">
                    <span class="flex items-center gap-2.5">
                        @include('studio::partials.activity-bar-glyph', ['position' => 'bottom'])
                        Position
                    </span>
                    <span class="flex items-center gap-0.5 rounded-lg bg-wash p-0.5" role="radiogroup" aria-label="Toolbar placement">
                        <button
                            type="button"
                            class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md transition-colors duration-150"
                            :class="!$store.studio.dock.pinned ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                            @click="$store.studio.setDock($store.studio.dock.edge, $store.studio.dock.along, false)"
                            role="radio"
                            :aria-checked="!$store.studio.dock.pinned"
                            title="Floating — a pill you can drag to any edge"
                            aria-label="Floating"
                        >
                            @include('studio::partials.activity-bar-glyph', ['position' => 'floating'])
                        </button>
                        @foreach(['left' => 'Pin left — an icon rail', 'top' => 'Pin top — the header bar', 'right' => 'Pin right — an icon rail', 'bottom' => 'Pin bottom — a full-width bar'] as $value => $label)
                            <button
                                type="button"
                                class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md transition-colors duration-150"
                                :class="$store.studio.dock.pinned && $store.studio.dock.edge === '{{ $value }}' ? 'bg-wash-strong text-ink' : 'text-faint hover:text-ink'"
                                @click="$store.studio.setDock('{{ $value }}', 0.5, true)"
                                role="radio"
                                :aria-checked="$store.studio.dock.pinned && $store.studio.dock.edge === '{{ $value }}'"
                                title="{{ $label }}"
                                aria-label="{{ $label }}"
                            >
                                @include('studio::partials.activity-bar-glyph', ['position' => $value])
                            </button>
                        @endforeach
                    </span>
                </div>

                @if($devModeAvailable)
                    {{-- Joined: the toolbar and the composer in one shell,
                         the toolbar tucked behind the composer until you
                         point at it. It needs the toolbar floating on a
                         horizontal edge and the chat floating with it, so
                         the row says which of those is missing rather than
                         going quiet — the same way the Page switcher and
                         Canvas width rows say "pinned bar". --}}
                    <button
                        type="button"
                        class="s-menu-item"
                        x-show="$store.studio.chatAvailable"
                        role="menuitemcheckbox"
                        :aria-checked="$store.studio.joined"
                        @click="$store.studio.setChatJoined(!$store.studio.chatJoined)"
                        title="Draw the toolbar and the chat composer as one panel, with the toolbar sliding out from behind it on hover"
                    >
                        <span class="flex h-3.5 w-3.5 shrink-0 items-center justify-center">
                            <svg x-show="$store.studio.chatJoined" class="h-3.5 w-3.5 text-ink" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate" :class="$store.studio.joined && 'text-ink'">Join the chat composer</span>
                        <span
                            class="shrink-0 text-[10.5px] text-faint"
                            x-show="$store.studio.chatJoined && !$store.studio.joined"
                            x-cloak
                            x-text="!$store.studio.chatFloating ? 'floating chat' : ($store.studio.dock.pinned ? 'unpinned toolbar' : 'top or bottom')"
                        ></span>
                    </button>
                @endif

                <div class="mt-0.5">
                    @foreach($viewSwitches as $key => [$label, $hint])
                        <button
                            type="button"
                            class="s-menu-item"
                            role="menuitemcheckbox"
                            :aria-checked="$store.studio.view.{{ $key }}"
                            @click="$store.studio.toggleView('{{ $key }}')"
                            title="{{ $hint }}"
                        >
                            <span class="flex h-3.5 w-3.5 shrink-0 items-center justify-center">
                                <svg x-show="$store.studio.view.{{ $key }}" class="h-3.5 w-3.5 text-ink" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate" :class="$store.studio.view.{{ $key }} && 'text-ink'">{{ $label }}</span>
                            @if(in_array($key, ['pages', 'devices'], true))
                                <span class="shrink-0 text-[10.5px] text-faint" x-show="!($store.studio.dock.pinned && $store.studio.dockHorizontal)">pinned bar</span>
                            @endif
                        </button>
                    @endforeach
                    <button type="button" class="s-menu-item" role="menuitemcheckbox" :aria-checked="!$store.studio.dockHidden" @click="$store.studio.toggleDock()" title="Hide the toolbar; point at its edge to peek it back">
                        <span class="flex h-3.5 w-3.5 shrink-0 items-center justify-center">
                            <svg x-show="!$store.studio.dockHidden" class="h-3.5 w-3.5 text-ink" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate" :class="!$store.studio.dockHidden && 'text-ink'">Show toolbar</span>
                        <span class="s-kbd">⌘.</span>
                    </button>
                </div>

                <div x-show="$store.studio.viewCustomized" x-cloak>
                    <div class="s-divider my-1"></div>
                    <button type="button" class="s-menu-item" @click="$store.studio.resetView()">
                        <span class="h-3.5 w-3.5 shrink-0"></span>
                        Reset toolbar parts
                    </button>
                </div>
            </div>

            {{-- The Workspace flyout: the presets. The check follows the real
                 state, so moving the toolbar or the chat by hand leaves none
                 of them checked. --}}
            <div
                x-show="open && sub === 'workspace'"
                x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="s-pop s-pop-inverse fixed z-50"
                :style="subStyles.workspace"
                x-ref="workspace"
                data-sub-menu
                role="menu"
                aria-label="Workspace"
                @mouseenter="clearTimeout(subTimer)"
                @mouseleave="if (!$event.relatedTarget?.closest?.('[data-sub-row]')) hideSub(250)"
                @keydown.arrow-left.prevent="sub = null; $refs.workspaceRow.focus()"
            >
                <p class="s-microlabel px-2.5 pb-1 pt-2">Workspace</p>
                @foreach(['composer', 'chat', 'minimal', 'classic', 'sidebar'] as $workspace)
                    @if($devModeAvailable || ! in_array($workspace, ['composer', 'chat']))
                        <button
                            type="button"
                            class="s-menu-item s-workspace-item"
                            @if(in_array($workspace, ['composer', 'chat'])) x-show="$store.studio.chatAvailable" @endif
                            :class="$store.studio.workspace === '{{ $workspace }}' && 'is-active'"
                            role="menuitemradio"
                            :aria-checked="$store.studio.workspace === '{{ $workspace }}'"
                            @click="open = false; $store.studio.applyWorkspace('{{ $workspace }}')"
                        >
                            @include('studio::partials.activity-bar-glyph', ['position' => $workspace])
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate" x-text="$store.studio.workspaces['{{ $workspace }}'].label"></span>
                                <span class="truncate text-[11px] text-faint" x-text="$store.studio.workspaces['{{ $workspace }}'].hint"></span>
                            </span>
                            <svg x-show="$store.studio.workspace === '{{ $workspace }}'" x-cloak class="h-3.5 w-3.5 shrink-0 text-ink" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                        </button>
                    @endif
                @endforeach
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
            {{-- Floating, this wrapper leaves the panel's column and becomes
                 the card over the site (s-chat-host): one Livewire instance,
                 two homes. The aside stays in the DOM as a ghost for it. --}}
            <div
                x-data="{
                    style: {},
                    observer: null,
                    slideTimer: null,

                    init() {
                        // Joined, the shell's box is written from this card's
                        // height, and that height changes without any store
                        // key moving: a keystroke grows the textarea, a
                        // context chip appears, the thread folds open. One
                        // observer catches all of it — no polling, and the
                        // shell can never be caught lagging behind what it
                        // is wrapping.
                        this.observer = new ResizeObserver(() => this.place());
                        this.observer.observe(this.$el);
                        // A reveal is the one change allowed to animate the
                        // shell's box, so it is the only one that arms the
                        // transition. The re-place itself comes from the
                        // effect below, which runs after this watcher.
                        this.$watch('$store.studio.joinedOut', () => {
                            if (this.$store.studio.joined) this.slide();
                        });
                    },
                    destroy() { this.observer?.disconnect() },

                    // Centred in the stage — not the window — so a docked
                    // panel beside the site never sits under the card
                    frame() {
                        const stage = document.querySelector('main.s-stage');
                        const r = stage ? stage.getBoundingClientRect() : { left: 0, width: window.innerWidth };
                        // 530px: the floating toolbar's width, so the two read as one family
                        const w = Math.round(Math.min(530, Math.max(320, r.width - 32)));
                        return { w, left: Math.round(r.left + (r.width - w) / 2) };
                    },

                    place() {
                        if ($store.studio.chatSide) return this.placeSide();
                        if (!$store.studio.chatFloating) { this.style = {}; return this.joinedReset() }
                        const { w, left } = this.frame();
                        if (!$store.studio.joined) {
                            this.joinedReset();
                            this.style = { left: left + 'px', width: w + 'px', top: 'auto', bottom: $store.studio.chatBottom + 'px' };
                            return;
                        }
                        this.placeJoined(w, left);
                    },

                    // Pinned to the right: a column as tall as the app row,
                    // flush with its right edge (the row's padding is what
                    // keeps the site a gutter away from it). Its own frame
                    // is fixed, so the site slides under it, not with it.
                    placeSide() {
                        this.joinedReset();
                        const row = document.querySelector('.s-app-row');
                        if (!row) return;
                        const r = row.getBoundingClientRect();
                        this.style = {
                            left: 'auto',
                            top: Math.round(r.top) + 'px',
                            bottom: Math.round(window.innerHeight - r.bottom) + 'px',
                            right: Math.round(window.innerWidth - r.right + $store.studio.chatSideClear) + 'px',
                            width: $store.studio.chatSideWidth + 'px',
                        };
                    },

                    /* Joined, this card is the whole unit's placer: it is the
                       only thing that knows the composer's height, and
                       writing all three boxes from one pass in one frame is
                       what keeps the shell, the row and the composer from
                       ever drifting apart by a pixel.

                       The composer is the anchor and never moves. A composer
                       that slid out from under the pointer on a reveal would
                       un-hover itself, collapse, re-hover and flicker — so
                       instead its edge is fixed a whole row's height from the
                       window's, and the reveal only moves the shell's far
                       edge and the row's contents. */
                    placeJoined(w, left) {
                        const shell = document.getElementById('studio-joined');
                        const top = $store.studio.dock.edge === 'top';
                        const bar = $store.studio.dockHidden ? 0 : $store.studio.joinedBar;
                        const shown = $store.studio.joinedShown;
                        const gap = 12;

                        this.style = top
                            ? { left: left + 'px', width: w + 'px', top: (gap + bar) + 'px', bottom: 'auto' }
                            : { left: left + 'px', width: w + 'px', bottom: gap + 'px' };

                        // The card's own box, now that it has been written
                        const h = this.$el.offsetHeight;
                        const cardTop = top ? gap + bar : Math.round(window.innerHeight - gap - h);

                        // The row's box goes through the store rather than
                        // straight onto the element: the dock binds its own
                        // style attribute, and writing behind that binding
                        // would leave it unpositioned the moment joining
                        // ends and Alpine's diff sees no change to re-apply.
                        $store.studio.joinedBox = { left, width: w, top: cardTop - bar };

                        // The shell has no style binding of its own, so it
                        // is written directly — but never through cssText,
                        // which would wipe the display x-show put there
                        if (shell) {
                            shell.style.left = left + 'px';
                            shell.style.width = w + 'px';
                            shell.style.top = (cardTop - shown) + 'px';
                            shell.style.height = (h + shown) + 'px';
                        }
                        // The open popover anchors to the unit, not the row
                        this.$dispatch('studio:dock-moved');
                    },

                    // Leaving joined mode, hand the shell back. Only the
                    // properties placeJoined set — cssText would take the
                    // display x-show wrote with it. The row needs nothing:
                    // clearing the box makes its own place() take over.
                    joinedReset() {
                        $store.studio.joinedBox = null;
                        const shell = document.getElementById('studio-joined');
                        if (!shell) return;
                        for (const prop of ['left', 'width', 'top', 'height']) shell.style[prop] = '';
                    },

                    /* A reveal is the one thing allowed to animate the
                       shell's box. It is on for its own 260ms and off again,
                       so the content-driven resizes that come through the
                       observer stay instant. */
                    slide() {
                        const shell = document.getElementById('studio-joined');
                        if (!shell) return;
                        shell.classList.add('is-sliding');
                        clearTimeout(this.slideTimer);
                        this.slideTimer = setTimeout(() => shell.classList.remove('is-sliding'), 320);
                    },
                }"
                class="flex h-full min-h-0 flex-col"
                :class="{ 's-chat-host': $store.studio.chatOut, 'is-side': $store.studio.chatSide, 'is-leaving': $store.studio.chatSideOut }"
                :style="style"
                x-effect="$store.studio.chatFloating; $store.studio.chatSide; $store.studio.chatSideWidth; $store.studio.chatSideOut; $store.studio.sidebar; $store.studio.docked; $store.studio.panelWidth; $store.studio.dock; $store.studio.dockHidden; $store.studio.mode; $store.studio.codeSplit; $store.studio.joined; $store.studio.joinedOut; $nextTick(() => place())"
                @resize.window.debounce.50ms="place()"
                @studio:reflow.window="place()"
                {{-- Every frame while a docked panel slides the site over --}}
                @studio:stage-resized.window="place()"
                {{-- Joined, this card is the placer and fires that event
                     itself — listening to it here would be a loop --}}
                @studio:dock-moved.window="if (!$store.studio.joined) place()"
                {{-- Floating, the card steps aside while a sheet is up: the
                     sheet is centred with a transform, which would capture a
                     fixed child, and the scrim covers the card anyway --}}
                x-show="$store.studio.chatOut ? !($store.studio.sidebar && $store.studio.frame === 'sheet') : $store.studio.rail === 'assistant'"
                x-cloak
            >
                <livewire:studio::assistant-panel :page-slug="$page->slug" />

                {{-- Pinned to the right: a drag seam on the column's inner
                     edge sets its width, the site following live. The shield
                     keeps the canvas iframe from swallowing the pointer. --}}
                <div
                    x-show="$store.studio.chatSide"
                    x-cloak
                    class="s-chat-side-seam"
                    role="separator"
                    aria-label="Resize the chat"
                    @mousedown.prevent="
                        const host = $el.parentElement;
                        const shield = document.createElement('div');
                        shield.className = 's-drag-shield is-col-resize';
                        document.body.appendChild(shield);
                        const move = (event) => {
                            $store.studio.setChatSideWidth(host.getBoundingClientRect().right - event.clientX);
                        };
                        const stop = () => {
                            shield.remove();
                            document.removeEventListener('mousemove', move);
                            document.removeEventListener('mouseup', stop);
                            window.removeEventListener('blur', stop);
                            document.body.classList.remove('select-none');
                            window.dispatchEvent(new CustomEvent('studio:reflow'));
                        };
                        document.body.classList.add('select-none');
                        document.addEventListener('mousemove', move);
                        document.addEventListener('mouseup', stop);
                        window.addEventListener('blur', stop);
                    "
                ></div>
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
                    { label: 'New layout…', hint: 'Create', when: studio.developer, run: () => window.Livewire?.dispatch('studio:new-layout') },
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
                    { label: 'Assistant panel', hint: 'Panel', when: studio.chatAvailable && !studio.chatFloating, run: () => studio.setRail('assistant', true) },
                    { label: 'Focus the chat', hint: 'Chat', when: studio.chatAvailable, run: () => studio.focusChat() },
                    { label: studio.chatFloating ? 'Dock the chat as a panel' : 'Float the chat over the site', hint: 'Chat', when: studio.chatAvailable, run: () => studio.setChatFloating(!studio.chatFloating) },
                    { label: studio.chatSide ? 'Close the chat column' : 'Pin the chat to the right', hint: 'Chat', when: studio.chatAvailable, run: () => studio.toggleChatSide() },
                    { label: studio.devMode ? 'Turn developer mode off' : 'Turn developer mode on', hint: 'Editor', run: () => studio.toggleDevMode() },
                    @endif
                    // One per workspace, minus the one you are in and the
                    // chat-led ones where there is no Assistant
                    ...Object.entries(studio.workspaces).map(([name, to]) => ({
                        label: 'Workspace: ' + to.label, hint: 'Workspace',
                        when: studio.workspace !== name && (!to.chat || studio.chatAvailable),
                        run: () => studio.applyWorkspace(name),
                    })),
                    @if($devModeAvailable)
                    { label: studio.filesOpen ? 'Hide the file tree' : 'Show the file tree', hint: 'Code', when: studio.mode === 'code', run: () => studio.toggleFiles() },
                    @endif
                    { label: studio.sidebar ? 'Close the panel' : 'Open the panel', hint: 'Layout', run: () => studio.toggleSidebar() },
                    { label: studio.dock.pinned ? 'Unpin the toolbar — let it float' : 'Pin the toolbar to its edge', hint: 'Layout', run: () => studio.togglePin() },
                    { label: 'Toolbar: header bar (pin top)', hint: 'Layout', when: !(studio.dock.pinned && studio.dock.edge === 'top'), run: () => studio.setDock('top', 0.5, true) },
                    { label: 'Toolbar: pin left', hint: 'Layout', when: !(studio.dock.pinned && studio.dock.edge === 'left'), run: () => studio.setDock('left', 0.5, true) },
                    { label: 'Toolbar: pin right', hint: 'Layout', when: !(studio.dock.pinned && studio.dock.edge === 'right'), run: () => studio.setDock('right', 0.5, true) },
                    { label: 'Toolbar: pin bottom', hint: 'Layout', when: !(studio.dock.pinned && studio.dock.edge === 'bottom'), run: () => studio.setDock('bottom', 0.5, true) },
                    { label: 'Toolbar: floating', hint: 'Layout', when: studio.dock.pinned, run: () => studio.setDock(studio.dock.edge, studio.dock.along, false) },
                    { label: studio.dockHidden ? 'Show the toolbar' : 'Hide the toolbar', hint: 'Layout', run: () => studio.toggleDock() },
                    { label: studio.view.nav ? 'Hide Back, Forward & Reload' : 'Show Back, Forward & Reload', hint: 'View', run: () => studio.toggleView('nav') },
                    { label: studio.view.grip ? 'Hide the drag handle' : 'Show the drag handle', hint: 'View', run: () => studio.toggleView('grip') },
                    { label: studio.view.pin ? 'Hide the pin button' : 'Show the pin button', hint: 'View', run: () => studio.toggleView('pin') },
                    { label: studio.view.live ? 'Hide the live-page button' : 'Show the live-page button', hint: 'View', run: () => studio.toggleView('live') },
                    { label: studio.view.tips ? 'Turn toolbar tooltips off' : 'Turn toolbar tooltips on', hint: 'View', run: () => studio.toggleView('tips') },
                    { label: 'Go back', hint: 'Navigate', run: () => history.back() },
                    { label: 'Go forward', hint: 'Navigate', run: () => history.forward() },
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

    {{-- Code mode takes the canvas's slot; the split gives half of it back.
         Both fill the canvas edge to edge as square frames. --}}
    <div class="s-canvas flex h-full w-full min-w-0" x-data>
        @if($devModeAvailable)
            @include('studio::partials.code-pane')

            {{-- Drag seam between the code pane and the preview. While it is
                 held, a transparent shield covers the window: a pointer that
                 crosses into the preview iframe hands its mousemove/mouseup to
                 that document, so a quick drag toward the preview would stall. --}}
            <div
                x-show="$store.studio.mode === 'code' && $store.studio.codeSplit"
                x-cloak
                class="s-code-seam"
                @mousedown.prevent="
                    const surface = $el.parentElement;
                    const shield = document.createElement('div');
                    shield.className = 's-drag-shield is-col-resize';
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

        <div
            class="min-w-0 flex-1 overflow-auto"
            x-show="$store.studio.canvasVisible"
            @pointerdown="if ($event.button === 0) $store.studio.dismissFloating()"
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
                treeGeneration: 0,   // bumped per reload, so a stale folder fetch lands nowhere
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
                    const generation = ++this.treeGeneration;
                    this.loading = true;
                    try {
                        const data = await this.fetchTree();
                        if (generation !== this.treeGeneration) return;
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
                        await this.expandOpen(generation);
                    } catch (e) {
                        this.error = e.message;
                    }
                    if (generation === this.treeGeneration) this.loading = false;
                },

                /** One tree request: the whole Designer view, or one Laravel folder (the root without `dir`). */
                async fetchTree(dir = null) {
                    const query = new URLSearchParams({ view: this.view });
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
                    const open = !this.openFolders[path];
                    this.openFolders = { ...this.openFolders, [path]: open };
                    this.persistFolders();
                    // Opening a lazy folder fetches it, and any folders inside it left open last time
                    if (open) this.expandOpen(this.treeGeneration);
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
