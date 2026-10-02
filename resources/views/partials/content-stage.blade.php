{{-- The Content view: the collections docked on the left, the chosen one on
     the right as a table with a drawer to edit an entry
     (livewire/content-table). It is the whole stage while the top bar's
     Content is chosen ($store.studio.view). A light surface in both themes,
     like the inspector (.s-content + .s-light in studio.css). --}}
<div
    x-show="$store.studio.view === 'content'"
    x-cloak
    class="s-content s-light flex h-full min-h-0 min-w-0 flex-1"
>
    <div
        class="s-dock"
        x-show="$store.studio.docks.content"
        :style="{ width: $store.studio.dockWidth + 'px' }"
    >
        <livewire:studio::content-panel />
        @include('studio::partials.dock-seam')
    </div>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <livewire:studio::content-table />
    </div>
</div>
