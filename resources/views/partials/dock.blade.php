{{-- The dock: the editor's only chrome. A floating pill over the site with
     the menu, one button per panel, the Preview/Edit/Code pill and Publish.
     Drag the grip to any window edge; the position lives in
     $store.studio.dock. Panels open from it as popovers (or sheets),
     anchored by the float in the layout to the button's data-panel. --}}
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
        'is-vertical': $store.studio.dock.edge === 'left' || $store.studio.dock.edge === 'right',
        'is-hidden': $store.studio.dockHidden && !peek,
        'is-dragging': dragging,
        ['at-' + $store.studio.dock.edge]: true,
    }"
    :style="style"
    aria-label="Editor"
    x-data="{
        dragging: false,
        drag: null,      // pointer offset inside the dock while dragging
        peek: false,
        style: '',

        // Position from the store: the dock's centre sits `along` the edge
        place() {
            if (this.dragging) return;
            const { edge, along } = $store.studio.dock;
            const gap = 12;
            const w = this.$el.offsetWidth, h = this.$el.offsetHeight;
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
        },

        startDrag(event) {
            event.preventDefault();
            this.dragging = true;
            event.currentTarget.setPointerCapture(event.pointerId);
            const rect = this.$el.getBoundingClientRect();
            this.drag = { dx: event.clientX - rect.left, dy: event.clientY - rect.top };
        },
        moveDrag(event) {
            if (!this.dragging) return;
            this.style = `left:${Math.round(event.clientX - this.drag.dx)}px; top:${Math.round(event.clientY - this.drag.dy)}px`;
        },
        endDrag(event) {
            if (!this.dragging) return;
            this.dragging = false;
            const x = event.clientX, y = event.clientY;
            const W = window.innerWidth, H = window.innerHeight;
            // Snap to the nearest edge; keep the position along it
            const d = { left: x, right: W - x, top: y, bottom: H - y };
            const edge = Object.keys(d).reduce((a, k) => d[k] < d[a] ? k : a, 'bottom');
            const along = (edge === 'bottom' || edge === 'top') ? x / W : y / H;
            $store.studio.setDock(edge, along);
            this.$nextTick(() => this.place());
        },
    }"
    x-init="place(); $nextTick(() => place())"
    x-effect="$store.studio.dock; $store.studio.mode; $store.studio.codeAvailable; $nextTick(() => place())"
    @resize.window.debounce.50ms="place()"
    @transitionend.self="$dispatch('studio:dock-moved')"
    @mouseleave="peek = false"
>
    <span
        class="s-dock-grip"
        title="Drag to move the dock"
        aria-label="Drag to move the dock"
        @pointerdown="startDrag($event)"
        @pointermove="moveDrag($event)"
        @pointerup="endDrag($event)"
        @pointercancel="dragging = false; place()"
    >
        <svg viewBox="0 0 8 14" width="8" height="14" fill="currentColor" aria-hidden="true"><circle cx="2" cy="2" r="1.3"/><circle cx="6" cy="2" r="1.3"/><circle cx="2" cy="7" r="1.3"/><circle cx="6" cy="7" r="1.3"/><circle cx="2" cy="12" r="1.3"/><circle cx="6" cy="12" r="1.3"/></svg>
    </span>

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

    @if($dev)
        {{-- Files belongs to Code mode: it toggles the tree column in the code pane --}}
        <button
            type="button"
            class="s-dock-btn"
            data-panel="files"
            data-tip="Files"
            x-show="$store.studio.mode === 'code'"
            x-cloak
            :class="$store.studio.filesOpen && 'is-active'"
            :aria-pressed="$store.studio.filesOpen"
            @click="$store.studio.toggleFiles()"
            aria-label="Files"
        >
            <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.25 12.75V12a2.25 2.25 0 0 1 2.25-2.25h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
        </button>
    @endif

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
</nav>
