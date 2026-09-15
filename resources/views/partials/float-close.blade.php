{{-- A floating panel's close control: the macOS red traffic light, first in
     the panel's top row. Its × shows on hover. --}}
<button type="button" class="s-close-dot" @click="$store.studio.closePanel()" title="Close (Esc)" aria-label="Close panel">
    <svg viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M3.75 3.75l4.5 4.5M8.25 3.75l-4.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
</button>
