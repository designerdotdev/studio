{{-- Shown between the layout's header and footer when the page has no sections yet --}}
<button type="button" class="studio-region studio-region--content" onclick="Studio.preview.addAt('page', null, event)">
    <span class="studio-region-label">{{ $page->title }} is empty</span>
    <span class="studio-region-hint">Page sections render here, inside the layout.</span>
    <span class="studio-region-pill">
        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
        Add a section
    </span>
</button>
