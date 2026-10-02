{{-- The Design view's rail: the tools that act on the page, on the frame
     beside it. Add section opens the library; Pages and Media open as a
     flyout over the canvas (the layout holds it) and close again — nothing
     is pushed aside. The palette sits at the foot. --}}
<nav
    class="s-rail"
    :class="$store.studio.view !== 'design' && 'is-collapsed'"
    :inert="$store.studio.view !== 'design'"
    aria-label="Design tools"
>
    <button
        type="button"
        class="s-tb-btn s-tip is-right"
        data-tip="Add section"
        aria-label="Add section"
        @click="$store.studio.closeDrawer(); window.dispatchEvent(new CustomEvent('studio:open-library', { detail: {} }))"
    >
        <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M12 5.25v13.5M5.25 12h13.5"/></svg>
    </button>

    <span class="s-rail-rule" aria-hidden="true"></span>

    <button
        type="button"
        class="s-tb-btn s-tip is-right"
        :class="$store.studio.drawer === 'pages' && 'is-active'"
        :data-tip="$store.studio.drawer === 'pages' ? '' : 'Pages'"
        aria-label="Pages"
        :aria-expanded="$store.studio.drawer === 'pages'"
        @click="$store.studio.toggleDrawer('pages')"
    >
        <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
    </button>

    <button
        type="button"
        class="s-tb-btn s-tip is-right"
        :class="$store.studio.drawer === 'media' && 'is-active'"
        :data-tip="$store.studio.drawer === 'media' ? '' : 'Media'"
        aria-label="Media"
        :aria-expanded="$store.studio.drawer === 'media'"
        @click="$store.studio.toggleDrawer('media')"
    >
        <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
    </button>

    <span class="flex-1"></span>

    <button
        type="button"
        class="s-tb-btn s-tip is-right"
        data-tip="Search and commands  ⌘K"
        aria-label="Search and commands"
        @click="window.dispatchEvent(new CustomEvent('studio:open-palette'))"
    >
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="10.75" cy="10.75" r="6"/><path d="m15.25 15.25 4.5 4.5"/></svg>
    </button>
</nav>
