<label for="field-{{ $sectionId }}-{{ $key }}" class="s-label">
    {{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}
</label>
<div class="flex items-center gap-2">
    <label class="relative h-8 w-9 shrink-0 cursor-pointer overflow-hidden rounded-lg border border-line" style="background: {{ $value ?: '#000000' }}">
        <input
            id="field-{{ $sectionId }}-{{ $key }}"
            type="color"
            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            value="{{ $value ?: '#000000' }}"
            x-on:input="preview('{{ $sectionId }}', '{{ $key }}', $event.target.value); $el.closest('label').style.background = $event.target.value"
            x-on:change="$wire.setVariable('{{ $sectionId }}', '{{ $key }}', $event.target.value)"
        >
    </label>
    <input
        type="text"
        class="s-input !font-mono !text-xs uppercase"
        value="{{ $value }}"
        placeholder="#000000"
        wire:model.blur="variables.{{ $sectionId }}.{{ $key }}"
        x-on:input="preview('{{ $sectionId }}', '{{ $key }}', $event.target.value)"
    >
</div>
