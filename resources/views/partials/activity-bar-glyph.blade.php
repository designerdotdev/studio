{{-- A tiny window diagram for an activity bar placement: the frame, with
     the bar drawn on the side it would sit (none for "hidden") --}}
<svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true">
    <rect x="1.75" y="2.25" width="12.5" height="11.5" rx="2.25" stroke="currentColor" stroke-width="1.2" @if($position === 'hidden') stroke-dasharray="2 1.6" @endif/>
    @switch($position)
        @case('left')
            <rect x="3.6" y="4.1" width="1.9" height="7.8" rx=".7" fill="currentColor"/>
            @break
        @case('top')
            <rect x="3.6" y="4.1" width="8.8" height="1.9" rx=".7" fill="currentColor"/>
            @break
        @case('bottom')
            <rect x="3.6" y="10" width="8.8" height="1.9" rx=".7" fill="currentColor"/>
            @break
    @endswitch
</svg>
