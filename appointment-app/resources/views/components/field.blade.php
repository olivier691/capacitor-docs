@props(['name', 'label', 'type' => 'text', 'required' => false, 'live' => false])

<div>
    <label for="{{ $name }}" @class(['field-label', 'required' => $required])>{{ $label }}</label>
    <input id="{{ $name }}" type="{{ $type }}"
           @if ($live) wire:model.live.debounce.300ms="{{ $name }}" @else wire:model="{{ $name }}" @endif
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
           {{ $attributes->merge(['class' => 'field-input']) }}>
    @error($name) <p id="{{ $name }}-error" class="field-error">{{ $message }}</p> @enderror
</div>
