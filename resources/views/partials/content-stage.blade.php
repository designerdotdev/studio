{{-- Content on the stage: the collection chosen in the sidebar's Content tab,
     as a table, with a drawer to edit an entry (livewire/content-table). It
     takes the canvas's slot while that tab is the sidebar's and gives it back
     the moment another tab is chosen ($store.studio.stage). A light surface
     in both themes, like the sidebar beside it (.s-content in studio.css). --}}
<div
    x-show="$store.studio.stage === 'content'"
    x-cloak
    class="s-frame s-content min-h-0 min-w-0 flex-1"
>
    <livewire:studio::content-table />
</div>
