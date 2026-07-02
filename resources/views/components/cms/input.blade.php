@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
])

<div>
    @if ($label)
        <label class="block mb-2 text-sm font-medium text-gray-900">
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        {{ $attributes->merge([
            'class' => 'w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500'
        ]) }}
    >

    @if ($name)
        @error($name)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>