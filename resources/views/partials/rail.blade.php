{{-- The Design view's rail: the tools that act on the page, on the frame
     beside it. Add section opens the library; Pages and Media open as a
     flyout over the canvas (the layout holds it) and close again — nothing
     is pushed aside. The page's settings and the palette sit at the foot. --}}
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
        :class="$store.studio.inspector === 'page' && $store.studio.rightPanel === 'inspector' && 'is-active'"
        :data-tip="$store.studio.inspector === 'page' ? '' : 'Page settings  ⌘,'"
        aria-label="Page settings"
        :aria-expanded="$store.studio.inspector === 'page'"
        @click="$store.studio.inspector === 'page' ? $store.studio.closeInspector() : $store.studio.openPageSettings()"
    >
        <svg class="h-[17px] w-[17px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
    </button>

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
