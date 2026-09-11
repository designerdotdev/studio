{{-- The activity bar: one icon per sidebar panel, VS Code style. It sits
     where $store.studio.activityBar says — across the top of the sidebar
     (the default), along its bottom, as a strip left of it, or nowhere
     (panels stay reachable from ⌘K). The layout renders this partial once
     per placement; `orientation` is horizontal (top/bottom) or vertical
     (left). A sliding pill marks the active panel; right-click or the ⋯
     button picks the placement. --}}
@php
    $vertical = $orientation === 'vertical';
    $dev = \Designer\Studio\Support\DevMode::enabled();
    $panels = array_values(array_filter([
        $dev ? ['assistant', 'Assistant', '<path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path d="M18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>'] : null,
        ['sections', 'Sections', '<path d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>'],
        ['pages', 'Pages', '<path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>'],
        ['content', 'Content', '<path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75m16.5 3.75v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75"/>'],
        ['media', 'Media', '<path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>'],
        // Files belongs to Code mode; it appears with it
        $dev ? ['files', 'Files', '<path d="M2.25 12.75V12a2.25 2.25 0 0 1 2.25-2.25h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>', 'code'] : null,
    ]));
    $positions = ['left' => 'Left', 'top' => 'Top', 'bottom' => 'Bottom', 'hidden' => 'Hidden'];
@endphp

<nav
    class="s-activity {{ $vertical ? 'is-vertical' : 'is-horizontal' }} {{ $class ?? '' }}"
    aria-label="Panels"
    x-data="{
        pill: null,
        ready: false,
        menu: false,
        menuStyle: '',
        // Slide the pill under the active panel's button (hidden when the
        // active panel has no button here, e.g. Files outside Code mode)
        place() {
            const button = this.$refs.items.querySelector(`[data-panel='${$store.studio.rail}']`);
            this.pill = button && button.offsetParent !== null
                ? { x: button.offsetLeft, y: button.offsetTop }
                : null;
            if (!this.ready) requestAnimationFrame(() => requestAnimationFrame(() => this.ready = true));
        },
        openMenu(event) {
            const anchor = event.currentTarget.getBoundingClientRect();
            const fromButton = event.type === 'click';
            const vertical = {{ $vertical ? 'true' : 'false' }};
            // From the ⋯ button: right-aligned under (or over) a horizontal
            // bar's button, beside the strip's bottom button. From a
            // right-click: at the pointer, flipped up near the bottom.
            let x, top = null, bottom = null;
            if (!fromButton) {
                x = event.clientX;
                if (event.clientY + 240 > window.innerHeight) bottom = window.innerHeight - event.clientY;
                else top = event.clientY;
            } else if (vertical) {
                x = anchor.right + 8;
                bottom = window.innerHeight - anchor.bottom;
            } else {
                x = anchor.right - 208;
                if (anchor.bottom + 240 > window.innerHeight) bottom = window.innerHeight - anchor.top + 6;
                else top = anchor.bottom + 6;
            }
            x = Math.max(8, Math.min(x, window.innerWidth - 216));
            this.menuStyle = `left:${x}px; ` + (top !== null ? `top:${top}px` : `bottom:${bottom}px`);
            this.menu = true;
        },
    }"
    x-effect="$store.studio.rail; $store.studio.mode; $store.studio.sidebar; $store.studio.activityBar; $nextTick(() => place())"
    @resize.window.debounce.100ms="place()"
    @contextmenu.prevent="openMenu($event)"
>
    <div x-ref="items" class="s-activity-items">
        <span
            class="s-activity-pill"
            :class="ready && 'is-ready'"
            x-show="pill && $store.studio.sidebar"
            x-cloak
            :style="pill && `transform: translate(${pill.x}px, ${pill.y}px)`"
            aria-hidden="true"
        ></span>

        @foreach($panels as $panel)
            @php [$name, $label, $icon] = $panel; @endphp
            <button
                type="button"
                class="s-activity-btn"
                data-panel="{{ $name }}"
                data-tip="{{ $label }}"
                @if(($panel[3] ?? null) === 'code') x-show="$store.studio.mode === 'code'" x-cloak @endif
                :class="$store.studio.rail === '{{ $name }}' && $store.studio.sidebar && 'is-active'"
                :aria-current="$store.studio.rail === '{{ $name }}' && $store.studio.sidebar ? 'page' : null"
                @click="$store.studio.setRail('{{ $name }}')"
                aria-label="{{ $label }}"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
            </button>
        @endforeach
    </div>

    <button
        type="button"
        class="s-activity-btn is-more"
        data-tip="Activity bar"
        @click="openMenu($event)"
        aria-label="Activity bar position"
        aria-haspopup="menu"
    >
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM8.5 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM15.5 8.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>
    </button>

    {{-- Placement menu (right-click anywhere on the bar, or the ⋯ button) --}}
    <template x-teleport="body">
        <div
            x-show="menu"
            x-cloak
            x-transition:enter="transition ease-out duration-120"
            x-transition:enter-start="opacity-0 scale-[0.97]"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click.outside="menu = false"
            @keydown.escape.window="menu = false"
            @contextmenu.prevent
            class="s-pop fixed z-[130] w-52"
            :style="menuStyle"
            role="menu"
            aria-label="Activity bar position"
        >
            <p class="s-microlabel px-2.5 pb-1 pt-1.5">Activity bar</p>
            @foreach($positions as $value => $label)
                <button
                    type="button"
                    class="s-menu-item"
                    :class="$store.studio.activityBar === '{{ $value }}' && '!text-ink'"
                    @click="menu = false; $store.studio.setActivityBar('{{ $value }}')"
                    role="menuitemradio"
                    :aria-checked="$store.studio.activityBar === '{{ $value }}'"
                >
                    @include('studio::partials.activity-bar-glyph', ['position' => $value])
                    <span class="flex-1">{{ $label }}</span>
                    <svg x-show="$store.studio.activityBar === '{{ $value }}'" class="h-3.5 w-3.5 text-accent" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                </button>
            @endforeach
            <div class="s-divider my-1"></div>
            <button type="button" class="s-menu-item" @click="menu = false; $store.studio.toggleSidebar()" role="menuitem">
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true"><rect x="1.75" y="2.25" width="12.5" height="11.5" rx="2.25"/><path d="M6 2.5v11"/></svg>
                <span class="flex-1">Hide sidebar</span>
                <span class="s-kbd">⌘B</span>
            </button>
        </div>
    </template>
</nav>
