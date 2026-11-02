@if ($activePage && $activePage->zones->where('is_visible', true)->isNotEmpty())
    @foreach ($activePage->zones->where('is_visible', true) as $zone)
        <div data-zone-id="{{ $zone->id }}" data-x="{{ $zone->x }}" data-y="{{ $zone->y }}" data-width="{{ $zone->width }}" data-height="{{ $zone->height }}"
            class="absolute flex items-center justify-center overflow-hidden border border-cyan-400 bg-cyan-900/30 text-center text-cyan-100"
            style="left: {{ $zone->x / $playlist->canvas_width * 100 }}%; top: {{ $zone->y / $playlist->canvas_height * 100 }}%; width: {{ $zone->width / $playlist->canvas_width * 100 }}%; height: {{ $zone->height / $playlist->canvas_height * 100 }}%; z-index: {{ $zone->z_index }}">
            <span class="truncate px-2">{{ $zone->name }}</span>
        </div>
    @endforeach
@endif
