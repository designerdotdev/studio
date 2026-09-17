{{-- A tiny window diagram for a toolbar placement: the frame, with the bar
     drawn flush on the edge it would pin to, a small floating pill for
     "floating", and a dashed frame for "hidden" --}}
<svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true">
    <rect x="1.75" y="2.25" width="12.5" height="11.5" rx="2.25" stroke="currentColor" stroke-width="1.2" @if($position === 'hidden') stroke-dasharray="2 1.6" @endif/>
    @switch($position)
        @case('left')
            <rect x="1.75" y="2.25" width="3.6" height="11.5" rx="1.6" fill="currentColor"/>
            @break
        @case('top')
            <rect x="1.75" y="2.25" width="12.5" height="3.6" rx="1.6" fill="currentColor"/>
            @break
        @case('bottom')
            <rect x="1.75" y="10.15" width="12.5" height="3.6" rx="1.6" fill="currentColor"/>
            @break
        @case('right')
            <rect x="10.65" y="2.25" width="3.6" height="11.5" rx="1.6" fill="currentColor"/>
            @break
        @case('floating')
        @case('minimal')
            <rect x="4.6" y="9.4" width="6.8" height="2.2" rx="1.1" fill="currentColor"/>
            @break
        @case('chat')
            {{-- header bar + the chat card floating over the bottom --}}
            <rect x="1.75" y="2.25" width="12.5" height="3.2" rx="1.6" fill="currentColor"/>
            <rect x="3.6" y="9" width="8.8" height="3" rx="1.3" fill="currentColor" opacity="0.55"/>
            @break
        @case('classic')
            {{-- header bar + a docked column --}}
            <rect x="1.75" y="2.25" width="12.5" height="3.2" rx="1.6" fill="currentColor"/>
            <rect x="1.75" y="5.9" width="4.4" height="7.85" rx="1.3" fill="currentColor" opacity="0.55"/>
            @break
    @endswitch
</svg>
