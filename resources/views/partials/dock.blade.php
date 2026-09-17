{{-- The dock: the editor's only chrome. Floating, it is a pill over the
     site with the menu, one button per panel, the Preview/Edit/Code pill and
     Publish; drag the grip to any window edge. Pinned (the pin button, or
     the menu's Toolbar row), it becomes a flush rail on that edge — the app
     root pads by its size so the site moves over instead of being covered —
     and a pinned top or bottom bar also carries the page switcher and the
     device widths, which is the header bar. The position lives in
     $store.studio.dock. Panels open from it as popovers (or sheets), or as a
     docked column while it is pinned. --}}
@php
    $dev = \Designer\Studio\Support\DevMode::enabled();
    $panels = array_values(array_filter([
        $dev ? ['assistant', 'Assistant', '<path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>'] : null,
        ['sections', 'Sections', '<path d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>'],
        ['pages', 'Pages', '<path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>'],
        ['content', 'Content', '<path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 3.75v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75"/>'],
        ['media', 'Media', '<path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>'],
    ]));
@endphp

<nav
    id="studio-dock"
    class="s-dock"
    :class="{
        'is-vertical': edge === 'left' || edge === 'right',
        'is-pinned': $store.studio.dock.pinned && !dragging,
        'is-hidden': $store.studio.dockHidden && !peek,
        'is-dragging': dragging,
        'is-snapping': snap,
        'no-tips': !$store.studio.view.tips,
        ['at-' + edge]: true,
    }"
    :style="style"
    aria-label="Editor"
    x-data="{
        dragging: false,
        drag: null,      // while dragging: pointer offset inside the dock + the edge it rides
        peek: false,
        over: false,
        peekTimer: null,
        style: '',
        snap: false,      // land at once instead of sliding (a pin flip)
        pinnedAt: null,   // the pin state place() last landed in

        // The edge the dock is on right now: the one it rides mid-drag, else the saved one
        get edge() {
            return this.dragging && this.drag ? this.drag.edge : $store.studio.dock.edge;
        },

        // Position from the store: the dock's centre sits `along` the edge
        place() {
            if (this.dragging) return;
            const { edge, along, pinned } = $store.studio.dock;
            // Pinning and unpinning change the toolbar's frame — they are not
            // a slide along an edge. Land at once, so the open panel anchors
            // to where the dock really is instead of where it was sliding from.
            if (this.pinnedAt !== null && this.pinnedAt !== pinned) {
                this.snap = true;
                // Two frames: the new position must be painted with the
                // transition off, or re-enabling it animates the jump anyway
                requestAnimationFrame(() => requestAnimationFrame(() => { this.snap = false }));
            }
            this.pinnedAt = pinned;
            // Pinned: the rail is drawn flush by CSS, nothing to place
            if (pinned) { this.style = ''; this.settled(); return; }
            const gap = 12;
            const w = this.$root.offsetWidth, h = this.$root.offsetHeight;
            const W = window.innerWidth, H = window.innerHeight;
            let left, top;
            if (edge === 'bottom' || edge === 'top') {
                left = Math.round(Math.min(W - gap - w, Math.max(gap, along * W - w / 2)));
                top = edge === 'bottom' ? H - gap - h : gap;
            } else {
                top = Math.round(Math.min(H - gap - h, Math.max(gap, along * H - h / 2)));
                left = edge === 'left' ? gap : W - gap - w;
            }
            this.style = `left:${left}px; top:${top}px`;
            this.settled();
        },

        // Whatever is anchored to the dock — the open panel, a popover —
        // measures it, and the dock's own effect runs after theirs. Tell them
        // once the new position has actually been written to the element.
        settled() {
            this.$nextTick(() => this.$dispatch('studio:dock-moved'));
        },

        // Dragging: the dock stays glued to its edge and slides along it
        // freely (x on top/bottom, y on left/right). Pulling it clearly
        // away from that edge hops it to whichever edge is nearest.
        startDrag(event) {
            event.preventDefault();
            this.dragging = true;
            const rect = this.$root.getBoundingClientRect();
            this.drag = { dx: event.clientX - rect.left, dy: event.clientY - rect.top, edge: $store.studio.dock.edge };
            if ($store.studio.dock.pinned) {
                // The flush rail turns back into a pill as the drag starts —
                // re-measure it and carry it centred under the pointer
                this.$nextTick(() => {
                    this.drag.dx = this.$root.offsetWidth / 2;
                    this.drag.dy = this.$root.offsetHeight / 2;
                    this.slide(event.clientX, event.clientY);
                });
            }
        },
        moveDrag(event) {
            if (!this.dragging) return;
            const x = event.clientX, y = event.clientY;
            const W = window.innerWidth, H = window.innerHeight;
            const d = { left: x, right: W - x, top: y, bottom: H - y };
            if (d[this.drag.edge] > 80) {
                const nearest = Object.keys(d).reduce((a, k) => d[k] < d[a] ? k : a, 'bottom');
                if (nearest !== this.drag.edge) {
                    // Hop: re-measure once the orientation class has applied,
                    // then carry the dock centred under the pointer
                    this.drag.edge = nearest;
                    this.$nextTick(() => {
                        this.drag.dx = this.$root.offsetWidth / 2;
                        this.drag.dy = this.$root.offsetHeight / 2;
                        this.slide(x, y);
                    });
                    return;
                }
            }
            this.slide(x, y);
        },
        slide(x, y) {
            const gap = 12;
            const w = this.$root.offsetWidth, h = this.$root.offsetHeight;
            const W = window.innerWidth, H = window.innerHeight;
            const edge = this.drag.edge;
            let left, top;
            if (edge === 'bottom' || edge === 'top') {
                left = Math.min(W - gap - w, Math.max(gap, x - this.drag.dx));
                top = edge === 'bottom' ? H - gap - h : gap;
            } else {
                top = Math.min(H - gap - h, Math.max(gap, y - this.drag.dy));
                left = edge === 'left' ? gap : W - gap - w;
            }
            this.style = `left:${Math.round(left)}px; top:${Math.round(top)}px`;
            this.$dispatch('studio:dock-moved'); // an open popover follows live
        },
        endDrag() {
            if (!this.dragging) return;
            const edge = this.drag.edge;
            const rect = this.$root.getBoundingClientRect();
            this.dragging = false;
            // Remember the dock's own centre along its edge, any pixel
            const along = (edge === 'bottom' || edge === 'top')
                ? (rect.left + rect.width / 2) / window.innerWidth
                : (rect.top + rect.height / 2) / window.innerHeight;
            // A pinned toolbar re-pins on whichever edge it was dropped at
            $store.studio.setDock(edge, along, $store.studio.dock.pinned);
            this.$nextTick(() => this.place());
        },
    }"
    x-init="place(); $nextTick(() => place())"
    x-effect="$store.studio.dock; $store.studio.mode; $store.studio.codeAvailable; $store.studio.view; $nextTick(() => place())"
    @resize.window.debounce.50ms="place()"
    @studio:reflow.window="place()"
    @transitionend.self="$dispatch('studio:dock-moved')"
    {{-- A peeked dock hides again when the pointer leaves it. The canvas
         iframe swallows pointer events, so this is per-element enter/leave,
         not a window-level watch. --}}
    @mouseenter="over = true; clearTimeout(peekTimer)"
    @mouseleave="over = false; if (!dragging) peek = false"
    {{-- Moves and the release are tracked on the window: with the shield
         over the canvas iframe, the parent document sees every event --}}
    @pointermove.window="moveDrag($event)"
    @pointerup.window="endDrag($event)"
    @pointercancel.window="if (dragging) { dragging = false; place() }"
>
    {{-- The optional parts — grip, pin, page switcher, device widths,
         Back/Forward/Reload, the live-page link, tooltips — follow
         $store.studio.view, switched from the menu's View submenu --}}
    <span
        x-show="$store.studio.view.grip"
        class="s-dock-grip"
        title="Drag to move the dock"
        aria-label="Drag to move the dock"
        @pointerdown="startDrag($event)"
    >
        <svg viewBox="0 0 8 14" width="8" height="14" fill="currentColor" aria-hidden="true"><circle cx="2" cy="2" r="1.3"/><circle cx="6" cy="2" r="1.3"/><circle cx="2" cy="7" r="1.3"/><circle cx="6" cy="7" r="1.3"/><circle cx="2" cy="12" r="1.3"/><circle cx="6" cy="12" r="1.3"/></svg>
    </span>

    {{-- Pin: a flush rail on this edge, the site pushed over by its size --}}
    <button
        x-show="$store.studio.view.pin"
        type="button"
        class="s-dock-btn s-dock-pin"
        :class="$store.studio.dock.pinned && 'is-active'"
        :data-tip="$store.studio.dock.pinned ? 'Unpin — let the toolbar float' : 'Pin the toolbar to this edge'"
        :aria-pressed="$store.studio.dock.pinned"
        @click="$store.studio.togglePin()"
        aria-label="Pin the toolbar"
    >
        <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 3.5h6l-.6 6.2 2.6 2.3v1.5H7v-1.5l2.6-2.3L9 3.5Z"/>
            <path d="M12 13.5V21"/>
        </svg>
    </button>

    {{ $menu ?? '' }}

    <span class="s-dock-sep"></span>

    @foreach($panels as [$name, $label, $icon])
        <button
            type="button"
            class="s-dock-btn"
            data-panel="{{ $name }}"
            data-tip="{{ $label }}"
            :class="$store.studio.rail === '{{ $name }}' && $store.studio.sidebar && 'is-active'"
            :aria-pressed="$store.studio.rail === '{{ $name }}' && $store.studio.sidebar"
            @click="$store.studio.setRail('{{ $name }}')"
            aria-label="{{ $label }}"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
        </button>
    @endforeach

    {{-- Back / Forward / Reload — off by default (View → Navigation
         buttons). Back and Forward walk the editor window's history, which
         is where page switches and Preview-mode link clicks land. --}}
    <div class="s-dock-nav" x-show="$store.studio.view.nav" x-cloak>
        <span class="s-dock-sep"></span>
        <button type="button" class="s-dock-btn" data-tip="Back" aria-label="Back" @click="history.back()">
            <svg class="h-[16px] w-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 5.5 8 12l6.5 6.5"/></svg>
        </button>
        <button type="button" class="s-dock-btn" data-tip="Forward" aria-label="Forward" @click="history.forward()">
            <svg class="h-[16px] w-[16px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.5 5.5 16 12l-6.5 6.5"/></svg>
        </button>
        <button type="button" class="s-dock-btn" data-tip="Reload the preview" aria-label="Reload the preview" @click="window.dispatchEvent(new CustomEvent('studio:refresh-preview'))">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 3.75V6.9a.6.6 0 0 1-.6.6h-3.15"/></svg>
        </button>
    </div>

    {{-- Pinned top or bottom, the bar is the full window width: the page
         switcher sits in its centre and the device widths join the right
         group. Floating and vertical rails stay icons-only. --}}
    <div
        x-show="$store.studio.view.pages && $store.studio.dock.pinned && $store.studio.dockHorizontal && !dragging"
        x-cloak
        class="s-dock-page"
        x-data="{
            open: false,
            popStyle: '',
            pages: window.__studioPageList || [],
            get current() { return this.pages.find((p) => p.current) || { title: 'Page', path: '/' } },
            go(slug) { window.location.href = window.__studioEditorUrl + '?page=' + encodeURIComponent(slug) },
        }"
        @click.outside="open = false"
        @keydown.escape.window="open = false"
    >
        <button
            type="button"
            class="s-dock-page-btn"
            :class="open && 'is-open'"
            @click="open = !open"
            x-data="{ state: 'idle' }"
            @studio:status.window="state = $event.detail.state"
            :title="state === 'saving' ? 'Saving…' : state === 'error' ? 'Offline — changes are not being saved' : 'All changes saved'"
            aria-haspopup="menu"
            :aria-expanded="open"
        >
            <span class="s-dock-status" :class="{ 'is-saving': state === 'saving', 'is-error': state === 'error' }"></span>
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
            class="s-pop fixed z-50 w-64 p-1"
            :style="popStyle"
            x-effect="open; $nextTick(() => { if (open) popStyle = window.StudioDock.anchor($el, $el.previousElementSibling, 256) })"
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
            <button type="button" class="s-menu-item" role="menuitem" @click="open = false; $store.studio.setRail('pages', true)">
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v11.5A2.25 2.25 0 0 1 15.75 18H4.25A2.25 2.25 0 0 1 2 15.75V4.25Zm1.5 2.75v8.75c0 .414.336.75.75.75h11.5a.75.75 0 0 0 .75-.75V7H3.5Z" clip-rule="evenodd"/></svg>
                Manage pages…
            </button>
        </div>
    </div>

    {{-- Pushes the modes and Publish to the far end of a pinned rail --}}
    <span class="s-dock-spacer" x-show="$store.studio.dock.pinned && !dragging" x-cloak></span>

    {{-- Device widths — only where the bar has the room --}}
    <div
        class="s-dock-seg s-dock-devices"
        x-show="$store.studio.view.devices && $store.studio.dock.pinned && $store.studio.dockHorizontal && !dragging"
        x-cloak
        role="radiogroup"
        aria-label="Canvas width"
    >
        <button type="button" class="s-dock-seg-btn" :class="$store.studio.device === 'desktop' && 'is-active'" @click="$store.studio.device = 'desktop'" title="Desktop (⌥1)" aria-label="Desktop width" role="radio" :aria-checked="$store.studio.device === 'desktop'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/></svg>
        </button>
        <button type="button" class="s-dock-seg-btn" :class="$store.studio.device === 'tablet' && 'is-active'" @click="$store.studio.device = 'tablet'" title="Tablet — 768px (⌥2)" aria-label="Tablet width" role="radio" :aria-checked="$store.studio.device === 'tablet'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2.25"/><path d="M11 17.75h2"/></svg>
        </button>
        <button type="button" class="s-dock-seg-btn" :class="$store.studio.device === 'mobile' && 'is-active'" @click="$store.studio.device = 'mobile'" title="Mobile — 390px (⌥3)" aria-label="Mobile width" role="radio" :aria-checked="$store.studio.device === 'mobile'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2.25"/><path d="M11 17.75h2"/></svg>
        </button>
    </div>

    <span class="s-dock-sep"></span>

    {{-- Preview / Edit / Code --}}
    <div class="s-dock-seg" role="radiogroup" aria-label="Mode">
        <button type="button" class="s-dock-seg-btn" :class="$store.studio.mode === 'preview' && 'is-active'" @click="$store.studio.setMode('preview')" title="Preview — browse the site as a visitor" aria-label="Preview mode" role="radio" :aria-checked="$store.studio.mode === 'preview'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9.25"/><path d="M2.9 12h18.2M12 2.75c2.2 2.5 3.3 5.6 3.3 9.25S14.2 18.75 12 21.25C9.8 18.75 8.7 15.65 8.7 12S9.8 5.25 12 2.75Z"/></svg>
        </button>
        <button type="button" class="s-dock-seg-btn is-edit" :class="$store.studio.mode === 'edit' && 'is-active'" @click="$store.studio.setMode('edit')" title="Edit — select and change sections" aria-label="Edit mode" role="radio" :aria-checked="$store.studio.mode === 'edit'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" aria-hidden="true"><path d="M4 3.5 19 11l-6.5 1.8L9 19 4 3.5Z"/></svg>
        </button>
        <button x-show="$store.studio.codeAvailable" x-cloak type="button" class="s-dock-seg-btn is-code" :class="$store.studio.mode === 'code' && 'is-active'" @click="$store.studio.setMode('code')" title="Code — edit the section and site source files" aria-label="Code mode" role="radio" :aria-checked="$store.studio.mode === 'code'">
            <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8.5 7.5 4 12l4.5 4.5M15.5 7.5 20 12l-4.5 4.5"/></svg>
        </button>
    </div>

    {{ $actions ?? '' }}

    {{-- While dragging, a shield covers the window (and the canvas iframe,
         which would otherwise swallow pointer events) so the drag stays
         fluid wherever the pointer goes --}}
    <template x-teleport="body">
        <div x-show="dragging" x-cloak class="s-drag-shield" aria-hidden="true"></div>
    </template>

    {{-- Hidden dock (⌘.): a hot strip on its edge peeks it back --}}
    <template x-teleport="body">
        <div
            x-show="$store.studio.dockHidden"
            x-cloak
            class="s-dock-peek"
            :style="({ bottom: 'left:0;right:0;bottom:0;height:6px', top: 'left:0;right:0;top:0;height:6px', left: 'top:0;bottom:0;left:0;width:6px', right: 'top:0;bottom:0;right:0;width:6px' })[$store.studio.dock.edge]"
            @mouseenter="peek = true; clearTimeout(peekTimer)"
            @mouseleave="peekTimer = setTimeout(() => { if (!over) peek = false }, 500)"
        ></div>
    </template>
</nav>
