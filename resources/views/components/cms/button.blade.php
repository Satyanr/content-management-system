@props([
    'type' => 'button',
    'color' => 'primary',
])

@php
    $classes = match ($color) {
        'danger' => 'text-white bg-red-700 hover:bg-red-800',
        'secondary' => 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-100',
        default => 'text-white bg-blue-700 hover:bg-blue-800',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge([
    'class' => $classes . ' font-medium rounded-lg text-sm px-5 py-2.5'
]) }}>
    {{ $slot }}
</button>