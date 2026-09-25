{{-- A floating panel's close control: a quiet × at the far right of the
     panel's top row, set off from the row's own actions by a hairline. --}}
<button type="button" class="s-close" @click="$store.studio.closePanel()" title="Close (Esc)" aria-label="Close panel">
    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4.5 4.5l7 7M11.5 4.5l-7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
</button>
