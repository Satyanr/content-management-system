<h2 class="mb-1 text-sm font-semibold">Daftar Window</h2>
<p class="mb-4 text-xs text-slate-400">Windows on {{ $activePage?->name ?? 'the active page' }}.</p>
<div class="space-y-2">
    @forelse ($activePage?->zones ?? [] as $zone)
        <div wire:key="window-{{ $zone->id }}" :class="selectedZoneId === {{ $zone->id }} ? '!border-cyan-500' : ''" class="rounded-lg border border-slate-700 bg-slate-800 p-3">
            <button type="button" @click="selectWindow({{ $zone->id }})" :aria-pressed="selectedZoneId === {{ $zone->id }}" class="flex w-full items-center gap-2 text-left text-sm text-cyan-300"><x-studio-icon name="layers" /><span class="truncate">{{ $zone->name }}</span></button>
            <dl x-show="advanced" class="mt-2 space-y-1 text-xs text-slate-400">
                <div>Position: {{ $zone->x }}, {{ $zone->y }} px</div>
                <div>Size: {{ $zone->width }} &times; {{ $zone->height }} px</div>
                <div>Layer: {{ $zone->z_index }} / {{ $zone->fit_mode }} / {{ $zone->keep_aspect_ratio ? 'Keep ratio' : 'Free ratio' }}</div>
                <div>{{ $zone->is_visible ? 'Tampilkan' : 'Hidden' }} / {{ $zone->is_locked ? 'Locked' : 'Unlocked' }}</div>
            </dl>
            <p class="mt-2 text-[10px] text-slate-500">Item di Window ini diputar bergantian. Pilih item untuk mengatur zoom/crop.</p>
            <ol class="mt-2 space-y-2">
                @foreach ($zone->items as $item)
                    <li wire:key="window-item-{{ $item->id }}" class="rounded bg-slate-900 p-2 text-xs">
                        <p class="truncate">{{ $loop->iteration }}. {{ auth()->user()->can('media.view') ? ($item->mediaAsset?->title ?? 'Media tidak tersedia') : 'Media' }}</p>
                        <button type="button" @click="selectWindowMedia({{ $zone->id }}, {{ $item->id }})" class="mt-1 text-cyan-300">Atur isi media</button>
                        <p class="mt-1 text-[10px] text-slate-400" x-text="fileDurationLabel({{ $item->id }})">{{ $item->duration_seconds !== null ? $item->duration_seconds.'s' : ($item->mediaAsset?->type === 'image' ? '10s' : 'Auto / intrinsic video duration') }}</p>
                        @can('playlists.edit')
                            <div x-show="advanced" class="mt-2 flex flex-wrap items-center gap-1">
                                <input x-init="if (durationDrafts[{{ $item->id }}] === undefined) durationDrafts[{{ $item->id }}] = {{ $item->duration_seconds ?? ($item->mediaAsset?->type === 'image' ? 10 : "''") }}" aria-label="Duration seconds for item {{ $item->id }}" type="number" min="1" max="86400" :value="durationDrafts[{{ $item->id }}] ?? {{ $item->duration_seconds ?? ($item->mediaAsset?->type === 'image' ? 10 : 'null') }}" @input="durationDrafts[{{ $item->id }}] = $event.target.value" placeholder="Auto" class="studio-input !w-20 !p-1 !text-xs">
                                <button type="button" @click="saveDuration({{ $zone->id }}, {{ $item->id }})" :disabled="busy" class="studio-button !p-1">Simpan durasi</button>
                                @if ($item->mediaAsset?->type === 'video')<button type="button" @click="saveDuration({{ $zone->id }}, {{ $item->id }}, true)" :disabled="busy" class="studio-button !p-1">Auto</button>@endif
                            </div>
                            <div x-show="advanced" class="mt-2 flex gap-1">
                                <button type="button" @click="moveItem({{ $zone->id }}, {{ $item->id }}, -1)" :disabled="busy || {{ $loop->first ? 'true' : 'false' }}" class="studio-button !p-1" title="Putar lebih awal">Naik</button>
                                <button type="button" @click="moveItem({{ $zone->id }}, {{ $item->id }}, 1)" :disabled="busy || {{ $loop->last ? 'true' : 'false' }}" class="studio-button !p-1" title="Putar lebih akhir">Turun</button>
                                <button type="button" @click="removeItem({{ $zone->id }}, {{ $item->id }})" :disabled="busy" class="studio-button !p-1 !text-red-300" title="Keluarkan dari urutan tanpa menghapus file asli">Keluarkan</button>
                            </div>
                        @endcan
                    </li>
                @endforeach
            </ol>
            @can('playlists.edit')
                <div x-show="advanced" class="mt-2 flex gap-2"><button type="button" @click="layerWindow({{ $zone->id }}, 'forward')" :disabled="busy" class="studio-button">Maju</button><button type="button" @click="layerWindow({{ $zone->id }}, 'backward')" :disabled="busy" class="studio-button">Mundur</button><button type="button" @click="deleteWindow({{ $zone->id }})" :disabled="busy" class="studio-button !text-red-300"><x-studio-icon name="trash" /></button></div>
            @endcan
        </div>
    @empty <div class="rounded-lg border border-dashed border-slate-700 p-6 text-center text-xs text-slate-400">Page ini belum memiliki Window. Tarik media dari panel kiri atau klik Tambah Window Baru.</div> @endforelse
</div>
