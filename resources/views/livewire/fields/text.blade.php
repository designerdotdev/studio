<label for="field-{{ $sectionId }}-{{ $key }}" class="s-label">
    {{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}@if(!empty($field['required']))<span class="ml-0.5 text-danger">*</span>@endif
</label>
<input
    id="field-{{ $sectionId }}-{{ $key }}"
    type="text"
    class="s-input"
    @if(isset($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
    wire:model.blur="variables.{{ $sectionId }}.{{ $key }}"
    x-on:input="preview('{{ $sectionId }}', '{{ $key }}', $event.target.value)"
>
