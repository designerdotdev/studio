{{-- A collection's entries under their page in the Pages panel: each opens
     on the canvas (PagesPanel::openEntry). `$children` rows are
     [title, path, tail, current]; the parent's Alpine scope holds `unfolded`. --}}
<div class="s-page-children !ml-[26px]" x-show="unfolded" x-collapse.duration.150ms @unless(collect($children)->contains('current', true)) x-cloak @endunless>
    @foreach($children as $child)
        <button
            type="button"
            wire:key="entry-{{ $child['path'] }}"
            class="s-page-child {{ $child['current'] ? 'is-current' : '' }}"
            title="Open {{ $child['path'] }}"
            @unless($child['current']) wire:click="openEntry(@js($child['path']))" @endunless
        >
            <span class="min-w-0 flex-1 truncate">{{ $child['title'] }}</span>
            <span class="s-page-child-path">{{ $child['tail'] }}</span>
        </button>
    @endforeach
</div>
