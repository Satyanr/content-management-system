@props([
    'header' => false,
    'align' => 'left',
])

@php
    $alignClass = $align === 'right' ? 'text-right' : 'text-left';
@endphp

@if ($header)
    <th {{ $attributes->merge([
        'class' => "px-6 py-3 {$alignClass}"
    ]) }}>
        {{ $slot }}
    </th>
@else
    <td {{ $attributes->merge([
        'class' => "px-6 py-4 {$alignClass}"
    ]) }}>
        {{ $slot }}
    </td>
@endif