@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge([
    'class' => 'bg-white border border-gray-200 rounded-lg shadow-sm'
]) }}>
    @if ($title || $subtitle)
        <div class="p-4 border-b border-gray-200">
            @if ($title)
                <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            @endif

            @if ($subtitle)
                <p class="text-sm text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    <div class="p-4">
        {{ $slot }}
    </div>
</div>