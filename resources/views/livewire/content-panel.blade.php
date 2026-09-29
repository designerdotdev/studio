{{-- The sidebar's Content tab: the collections. Choosing one shows its
     entries on the stage (livewire/content-table), which owns the selection —
     $store.studio.collection mirrors it, so the highlight moves with the
     click instead of waiting on the table. --}}
<div class="flex h-full min-h-0 flex-col" x-data>
    <div class="s-panel-head">
        <p class="s-microlabel flex-1">Content</p>
        <button type="button" x-show="$store.studio.developer" x-cloak class="s-icon-btn" title="New collection" aria-label="New collection" @click="Livewire.dispatch('studio:content-create')">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto p-2">
        @forelse($this->collections as $item)
            <button
                type="button"
                wire:key="collection-{{ $item['name'] }}"
                class="s-section-row w-full text-left"
                :class="$store.studio.collection === @js($item['name']) && 'is-active'"
                :aria-current="$store.studio.collection === @js($item['name']) ? 'true' : null"
                @click="$store.studio.collection = @js($item['name']); Livewire.dispatch('studio:content-open', { name: @js($item['name']) })"
            >
                <svg class="h-4 w-4 shrink-0 text-faint" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 3.75v3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125v-3.75"/></svg>
                <span class="min-w-0 flex-1 truncate text-[12.5px] text-ink/90">{{ $item['title'] }}</span>
                <span class="s-count" title="{{ $item['count'] }} {{ Str::plural('entry', $item['count']) }}">{{ $item['count'] }}</span>
            </button>
        @empty
            <div class="px-3 py-10 text-center">
                <p class="text-[13px] text-soft">No collections yet.</p>
                <p class="mt-1 text-[11.5px] leading-relaxed text-faint">A collection is a list of entries — posts, team members, FAQs — that any repeater can bind to.</p>
                <button type="button" x-show="$store.studio.developer" x-cloak class="s-btn-outline mt-4 !text-[11.5px]" @click="Livewire.dispatch('studio:content-create')">New collection</button>
            </div>
        @endforelse
    </div>
</div>
