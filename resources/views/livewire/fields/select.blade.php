<label for="field-{{ $sectionId }}-{{ $key }}" class="s-label">
    {{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}
</label>
<select
    id="field-{{ $sectionId }}-{{ $key }}"
    class="s-input"
    wire:model="variables.{{ $sectionId }}.{{ $key }}"
    x-on:change="preview('{{ $sectionId }}', '{{ $key }}', $event.target.value); $wire.setVariable('{{ $sectionId }}', '{{ $key }}', $event.target.value)"
>
    @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
    @endforeach
</select>
