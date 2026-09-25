{{-- Minimal geometric glyphs per section category --}}
@switch($category)
    @case('banners')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2" width="14" height="3" rx="1"/><rect x="1" y="7" width="14" height="7" rx="1" opacity=".25"/></svg>
        @break
    @case('headers')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2" width="14" height="4" rx="1"/><circle cx="3.5" cy="4" r="1"/><rect x="1" y="8" width="14" height="6" rx="1" opacity=".25"/></svg>
        @break
    @case('heroes')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2" width="14" height="12" rx="1.5" opacity=".25"/><rect x="4" y="5" width="8" height="2" rx="1"/><rect x="5.5" y="8.5" width="5" height="1.5" rx=".75"/></svg>
        @break
    @case('logos')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="6.5" width="2.6" height="3" rx=".8"/><rect x="5.1" y="6.5" width="2.6" height="3" rx=".8" opacity=".7"/><rect x="9.2" y="6.5" width="2.6" height="3" rx=".8" opacity=".5"/><rect x="13.3" y="6.5" width="1.7" height="3" rx=".8" opacity=".3"/></svg>
        @break
    @case('features')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6.4" height="6.4" rx="1.4"/><rect x="8.6" y="1" width="6.4" height="6.4" rx="1.4" opacity=".5"/><rect x="1" y="8.6" width="6.4" height="6.4" rx="1.4" opacity=".5"/><rect x="8.6" y="8.6" width="6.4" height="6.4" rx="1.4" opacity=".25"/></svg>
        @break
    @case('stats')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1.5" y="9" width="3" height="5.5" rx="1"/><rect x="6.5" y="5.5" width="3" height="9" rx="1" opacity=".65"/><rect x="11.5" y="1.5" width="3" height="13" rx="1" opacity=".35"/></svg>
        @break
    @case('content')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2.5" width="10" height="2" rx="1"/><rect x="1" y="7" width="14" height="1.6" rx=".8" opacity=".5"/><rect x="1" y="10" width="14" height="1.6" rx=".8" opacity=".5"/><rect x="1" y="13" width="9" height="1.6" rx=".8" opacity=".5"/></svg>
        @break
    @case('gallery')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1" width="6.4" height="9" rx="1.4"/><rect x="8.6" y="1" width="6.4" height="5.5" rx="1.4" opacity=".5"/><rect x="8.6" y="8" width="6.4" height="7" rx="1.4" opacity=".35"/><rect x="1" y="11.5" width="6.4" height="3.5" rx="1.4" opacity=".25"/></svg>
        @break
    @case('testimonials')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3.5A1.5 1.5 0 0 1 3.5 2h3A1.5 1.5 0 0 1 8 3.5v3A1.5 1.5 0 0 1 6.5 8H5l-2.2 2.4c-.4.44-.8.24-.8-.3V3.5Z"/><path d="M9 8.5A1.5 1.5 0 0 1 10.5 7h3A1.5 1.5 0 0 1 15 8.5v3a1.5 1.5 0 0 1-1.5 1.5H12l-2.2 2.4c-.4.44-.8.24-.8-.3V8.5Z" opacity=".4"/></svg>
        @break
    @case('pricing')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="3" width="4.2" height="10" rx="1.2" opacity=".35"/><rect x="5.9" y="1.5" width="4.2" height="13" rx="1.2"/><rect x="10.8" y="3" width="4.2" height="10" rx="1.2" opacity=".35"/></svg>
        @break
    @case('faq')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2" width="14" height="3" rx="1.2"/><rect x="1" y="6.5" width="14" height="3" rx="1.2" opacity=".5"/><rect x="1" y="11" width="14" height="3" rx="1.2" opacity=".25"/></svg>
        @break
    @case('team')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="5.5" cy="5" r="2.6"/><path d="M1 13.5a4.5 4.5 0 0 1 9 0V14H1v-.5Z"/><circle cx="12" cy="5.5" r="2" opacity=".45"/><path d="M10.5 14v-.4c0-1.2-.34-2.3-.94-3.24A3.5 3.5 0 0 1 15.5 13v1h-5Z" opacity=".45"/></svg>
        @break
    @case('blog')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="1.5" width="14" height="6" rx="1.4" opacity=".35"/><rect x="1" y="9.5" width="9" height="1.8" rx=".9"/><rect x="1" y="12.8" width="12" height="1.6" rx=".8" opacity=".5"/></svg>
        @break
    @case('contact')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><path d="M1.5 4.4A1.9 1.9 0 0 1 3.4 2.5h9.2a1.9 1.9 0 0 1 1.9 1.9v7.2a1.9 1.9 0 0 1-1.9 1.9H3.4a1.9 1.9 0 0 1-1.9-1.9V4.4Z" opacity=".3"/><path d="M2 3.6 8 8l6-4.4a1.9 1.9 0 0 0-1.4-.6H3.4c-.55 0-1.05.23-1.4.6Z"/></svg>
        @break
    @case('newsletter')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><path d="M14.5 1.5 1.5 6.7l4.8 2 5.9-4.9-4.4 5.6 1.6 4.6 5.1-12.5Z"/></svg>
        @break
    @case('cta')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="5" width="14" height="6" rx="3"/></svg>
        @break
    @case('footers')
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="2" width="14" height="7" rx="1" opacity=".25"/><rect x="1" y="10.5" width="14" height="3.5" rx="1"/></svg>
        @break
    @default
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor"><rect x="1" y="3" width="14" height="10" rx="1.5" opacity=".4"/><rect x="4" y="6.5" width="8" height="3" rx="1"/></svg>
@endswitch
