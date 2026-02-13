<x-dynamic-component :component="$layout">
    @foreach($renderedSections as $html)
        {!! $html !!}
    @endforeach
</x-dynamic-component>
