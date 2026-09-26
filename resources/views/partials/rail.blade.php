{{-- The activity rail — 44px on the ground, the DevDojo builder's chrome.
     Top: the brand mark, hover-swapping into the hamburger (the menu).
     Then one small icon tab per panel: Assistant (developer mode) ·
     Sections · Pages · Content · Media. A press on the open panel's tab
     closes the sidebar; any other opens it on that panel. --}}
@php
    $railItems = [
        ['assistant', 'Assistant', '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/>'],
        ['sections', 'Sections', '<path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>'],
        ['pages', 'Pages', '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>'],
        ['content', 'Content', '<ellipse cx="12" cy="5.5" rx="7.5" ry="3"/><path stroke-linecap="round" d="M4.5 5.5v13c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-13"/><path stroke-linecap="round" d="M4.5 12c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3"/>'],
        ['media', 'Media', '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>'],
    ];
@endphp
<nav class="s-rail" aria-label="Panels">
    {{ $menu ?? '' }}

    <span class="s-rail-sep" aria-hidden="true"></span>

    @foreach($railItems as [$name, $label, $icon])
        <button
            type="button"
            class="s-rail-btn s-tip s-tip-right"
            data-tip="{{ $label }}"
            aria-label="{{ $label }}"
            :class="{ 'is-active': $store.studio.sidebar && $store.studio.rail === '{{ $name }}', 'is-busy': {{ $name === 'assistant' ? '$store.studio.chatBusy' : 'false' }} }"
            :aria-pressed="$store.studio.sidebar && $store.studio.rail === '{{ $name }}'"
            @click="$store.studio.setRail('{{ $name }}')"
            @if($name === 'assistant') x-show="$store.studio.chatAvailable" x-cloak @endif
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">{!! $icon !!}</svg>
        </button>
    @endforeach
</nav>
