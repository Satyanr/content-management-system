@props([
    'color' => 'blue',
])

@php
    $classes = match ($color) {
        'green' => 'text-green-800 bg-green-100',
        'red' => 'text-red-800 bg-red-100',
        'yellow' => 'text-yellow-800 bg-yellow-100',
        'gray' => 'text-gray-800 bg-gray-100',
        default => 'text-blue-800 bg-blue-100',
    };
@endphp

<span {{ $attributes->merge([
    'class' => $classes . ' inline-flex items-center px-2 py-1 text-xs font-medium rounded'
]) }}>
    {{ $slot }}
</span>