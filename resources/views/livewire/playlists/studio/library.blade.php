<h2 class="mb-1 text-sm font-semibold">Pustaka Media</h2>
<p class="mb-4 text-xs leading-relaxed text-slate-400">Tarik media ke kanvas untuk tampil bersamaan di Window baru. Tambahkan ke Window yang dipilih untuk urutan bergantian.</p>
@can('playlists.edit')<button type="button" @click="createWindow()" :disabled="busy || !pageId" class="studio-button mb-3 w-full justify-center">+ Tambah Window Baru</button>@endcan
@can('media.view')
    <label class="sr-only" for="studio-search">Search media</label>
    <input id="studio-search" type="search" wire:model.live.debounce.300ms="mediaSearch" placeholder="Cari gambar atau video..." maxlength="200" class="studio-input mb-3" />
    <label class="sr-only" for="studio-filter">Media type</label>
    <select id="studio-filter" wire:model.live="mediaType" class="studio-input mb-4"><option value="">Gambar & video</option><option value="image">Gambar</option><option value="video">Video</option></select>
    <div class="grid grid-cols-2 gap-2" wire:loading.class="opacity-50" wire:target="mediaSearch,mediaType,gotoPage">
        @forelse ($media as $asset)
            <article draggable="{{ auth()->user()->can('playlists.edit') ? 'true' : 'false' }}" @dragstart="startMedia($event, { id: {{ $asset->id }}, width: {{ (int) $asset->width }}, height: {{ (int) $asset->height }} })" @dragend="endMedia()" wire:key="studio-media-{{ $asset->id }}" class="min-w-0 overflow-hidden rounded-lg border border-slate-700 bg-slate-800">
                <div class="flex h-20 items-center justify-center bg-slate-950">
                    @if ($asset->type === 'image') <img draggable="false" src="{{ $asset->url }}" alt="" loading="lazy" class="h-full w-full object-contain" />
                    @else <x-studio-icon name="video" class="h-7 w-7 text-slate-400" /> @endif
                </div>
                <div class="p-2"><p class="truncate text-xs" title="{{ $asset->title }}">{{ $asset->title }}</p><p class="mt-1 text-[10px] uppercase tracking-wide text-slate-500">{{ $asset->type }} / {{ $asset->size_formatted }}</p></div>
                @can('playlists.edit')
                    <button type="button" :disabled="busy" @click="createMediaWindow({ id: {{ $asset->id }}, width: {{ (int) $asset->width }}, height: {{ (int) $asset->height }} })" title="Tampil bersamaan dengan Window lain" class="studio-button m-1 !px-1 !text-[10px]">Buat Window Baru</button>
                    <button type="button" x-cloak x-show="selectedZoneId" :disabled="busy" @click="addMediaToSelected({{ $asset->id }})" title="Diputar bergantian dalam urutan Window ini" class="studio-button m-1 !px-1 !text-[10px]">Tambahkan ke Window Ini</button>
                @endcan
            </article>
        @empty <p class="col-span-2 rounded border border-dashed border-slate-700 p-5 text-center text-xs text-slate-400">Belum ada media yang cocok. Unggah file di Pustaka Media atau ubah pencarian.</p> @endforelse
    </div>
    @if ($media->hasPages())
        <div class="mt-4 flex items-center justify-between gap-2 text-xs">
            <button type="button" wire:click="previousPage('mediaPage')" @disabled($media->onFirstPage()) class="studio-button">Sebelumnya</button>
            <span>{{ $media->currentPage() }}/{{ $media->lastPage() }}</span>
            <button type="button" wire:click="nextPage('mediaPage')" @disabled(!$media->hasMorePages()) class="studio-button">Berikutnya</button>
        </div>
    @endif
@else <p class="rounded-lg border border-slate-700 p-4 text-xs text-slate-400">Anda belum memiliki izin melihat media. Hubungi admin.</p> @endcan
