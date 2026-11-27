@props(['name', 'label', 'type' => 'text', 'help' => null, 'value' => null, 'autocomplete' => null, 'required' => false])
<div>
    <label for="f-{{ $name }}" class="label">{{ $label }}</label>
    <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($required) required @endif
           @error($name) aria-invalid="true" aria-describedby="e-{{ $name }}" @enderror
           {{ $attributes->class(['input', 'input-error' => $errors->has($name)]) }}>
    @if ($help)<p class="help">{{ $help }}</p>@endif
    @error($name)<p id="e-{{ $name }}" class="error">{{ $message }}</p>@enderror
</div>
