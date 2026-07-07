@php
    $on = filter_var($value, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="flex items-center justify-between gap-3">
    <span class="s-label !mb-0">
        {{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}
    </span>
    <button
        type="button"
        role="switch"
        aria-checked="{{ $on ? 'true' : 'false' }}"
        class="relative inline-flex h-[18px] w-8 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 {{ $on ? 'bg-accent' : 'bg-white/12' }}"
        wire:click="setVariable('{{ $sectionId }}', '{{ $key }}', {{ $on ? 'false' : 'true' }})"
        x-on:click="preview('{{ $sectionId }}', '{{ $key }}', {{ $on ? 'false' : 'true' }})"
    >
        <span class="sr-only">Toggle {{ $field['label'] ?? $key }}</span>
        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform duration-200 {{ $on ? 'translate-x-[15px]' : 'translate-x-[2px]' }}"></span>
    </button>
</div>
