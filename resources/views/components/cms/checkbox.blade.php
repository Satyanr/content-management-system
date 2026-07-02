@props([
    'label' => null,
    'name' => null,
])

<label class="flex items-center gap-2 text-sm text-gray-700">
    <input
        type="checkbox"
        {{ $attributes->merge([
            'class' => 'rounded border-gray-300 text-blue-600 focus:ring-blue-500'
        ]) }}
    >

    @if ($label)
        <span>{{ $label }}</span>
    @endif
</label>

@if ($name)
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
@endif