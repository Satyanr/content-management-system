@php $interactive = $interactive ?? true; @endphp
@if ($activePage)
    @foreach ($activePage->zones as $zone)
        @php $asset = $zone->items->first()?->mediaAsset; @endphp
        <div wire:key="{{ $interactive ? 'editor' : 'preview' }}-zone-{{ $zone->id }}" data-zone-id="{{ $zone->id }}" data-x="{{ $zone->x }}" data-y="{{ $zone->y }}" data-width="{{ $zone->width }}" data-height="{{ $zone->height }}"
            class="absolute select-none {{ $interactive ? 'touch-none' : '' }}"
            @if ($interactive)
                :style="windowStyle({{ $zone->id }})" @pointerdown.stop="startWindow($event, {{ $zone->id }})"
                style="left:{{ $zone->x }}px;top:{{ $zone->y }}px;width:{{ $zone->width }}px;height:{{ $zone->height }}px;z-index:{{ $zone->z_index }};{{ $zone->is_visible ? '' : 'display:none' }}"
            @else
                style="left:{{ $zone->x / $playlist->canvas_width * 100 }}%;top:{{ $zone->y / $playlist->canvas_height * 100 }}%;width:{{ $zone->width / $playlist->canvas_width * 100 }}%;height:{{ $zone->height / $playlist->canvas_height * 100 }}%;z-index:{{ $zone->z_index }};{{ $zone->is_visible ? '' : 'display:none' }}"
            @endif
            aria-label="Window {{ $zone->name }}">
            <div class="h-full w-full overflow-hidden bg-slate-800/50">
                @if ($asset && auth()->user()->can('media.view'))
                    @if ($asset->type === 'image')
                        <img wire:key="{{ $interactive ? 'editor' : 'preview' }}-image-{{ $zone->items->first()->id }}" src="{{ $asset->url }}" alt="{{ $asset->title }}" draggable="false" class="pointer-events-none h-full w-full" :style="fitMedia({{ $zone->id }})" style="object-fit:{{ $zone->fit_mode === 'stretch' ? 'fill' : $zone->fit_mode }}" />
                    @elseif ($asset->type === 'video')
                        <video wire:ignore wire:key="{{ $interactive ? 'editor' : 'preview' }}-video-{{ $zone->items->first()->id }}" src="{{ route('admin.media-assets.stream', $asset->id) }}" muted playsinline preload="metadata" @if (!$interactive) controls @endif class="h-full w-full {{ $interactive ? 'pointer-events-none' : '' }}" :style="fitMedia({{ $zone->id }})" style="object-fit:{{ $zone->fit_mode === 'stretch' ? 'fill' : $zone->fit_mode }}"></video>
                    @endif
                @else <div class="flex h-full w-full items-center justify-center overflow-hidden text-cyan-100">{{ $zone->name }}</div> @endif
            </div>
            @if ($interactive)
                <div x-cloak x-show="selectedZoneId === {{ $zone->id }}" class="pointer-events-none absolute left-0 top-0 bg-cyan-500 px-1 text-black" :style="{ fontSize: 11 / scale + 'px', transform: 'translateY(-100%)' }">{{ $zone->name }}</div>
                @can('playlists.edit')
                    @foreach (['nw'=>[0,0],'n'=>[50,0],'ne'=>[100,0],'e'=>[100,50],'se'=>[100,100],'s'=>[50,100],'sw'=>[0,100],'w'=>[0,50]] as $handle => [$left,$top])
                        <button type="button" x-cloak x-show="selectedZoneId === {{ $zone->id }} && !zones[{{ $zone->id }}]?.is_locked" @pointerdown.stop="startWindow($event, {{ $zone->id }}, '{{ $handle }}')" :disabled="busy" class="absolute z-10 border border-slate-950 bg-cyan-300" :style="{ width: 10 / scale + 'px', height: 10 / scale + 'px', left: '{{ $left }}%', top: '{{ $top }}%', transform: 'translate(-50%, -50%)', cursor: '{{ $handle }}-resize' }" aria-label="Resize {{ $zone->name }} {{ $handle }}"></button>
                    @endforeach
                @endcan
            @endif
        </div>
    @endforeach
@endif
