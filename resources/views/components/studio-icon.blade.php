@props(['name'])
@php
    $paths = [
        'back' => 'm12 19-7-7 7-7M5 12h14',
        'save' => 'M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2ZM7 3v6h10V3M7 21v-8h10v8',
        'eye' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'plus' => 'M12 5v14M5 12h14',
        'minus' => 'M5 12h14',
        'fit' => 'M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5M8 8h8v8H8Z',
        'settings' => 'M4 7h16M4 17h16M8 4v6m8 4v6',
        'page' => 'M14 2H6a2 2 0 0 0-2 2v16h16V8ZM14 2v6h6M8 13h8m-8 4h5',
        'monitor' => 'M3 4h18v13H3ZM8 21h8m-4-4v4',
        'trash' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7',
        'image' => 'M3 3h18v18H3ZM3 16l5-5 4 4 3-3 6 6M16 7h.01',
        'video' => 'M3 5h13v14H3ZM16 10l5-4v12l-5-4',
        'close' => 'm6 6 12 12M6 18 18 6',
        'layers' => 'm12 3 10 5-10 5L2 8Zm-10 9 10 5 10-5M2 16l10 5 10-5',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['monitor'] }}" /></svg>
