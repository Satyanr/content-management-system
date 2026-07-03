@props([
    'color' => 'blue',
])

@php
    $classes = match ($color) {
        'red' => 'text-red-600 hover:text-red-800',
        'green' => 'text-green-600 hover:text-green-800',
        'gray' => 'text-gray-600 hover:text-gray-800',
        default => 'text-blue-600 hover:text-blue-800',
    };
@endphp

<button {{ $attributes->merge([
    'type' => 'button',
    'class' => $classes . ' font-medium hover:underline'
]) }}>
    {{ $slot }}
</button>