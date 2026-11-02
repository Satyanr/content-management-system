<h2 class="mb-1 text-sm font-semibold">Content Library</h2>
<p class="mb-4 text-xs text-slate-400">Media from this workspace. Media placement will be available in the next stage.</p>
@can('media.view')
    <label class="sr-only" for="studio-search">Search media</label>
    <input id="studio-search" type="search" wire:model.live.debounce.300ms="mediaSearch" placeholder="Search media..." maxlength="200" class="studio-input mb-3" />
    <label class="sr-only" for="studio-filter">Media type</label>
    <select id="studio-filter" wire:model.live="mediaType" class="studio-input mb-4"><option value="">Images & videos</option><option value="image">Images</option><option value="video">Videos</option></select>
    <div class="grid grid-cols-2 gap-2" wire:loading.class="opacity-50" wire:target="mediaSearch,mediaType,gotoPage">
        @forelse ($media as $asset)
            <article wire:key="studio-media-{{ $asset->id }}" class="min-w-0 overflow-hidden rounded-lg border border-slate-700 bg-slate-800">
                <div class="flex h-20 items-center justify-center bg-slate-950">
                    @if ($asset->type === 'image') <img src="{{ $asset->url }}" alt="" loading="lazy" class="h-full w-full object-contain" />
                    @else <x-studio-icon name="video" class="h-7 w-7 text-slate-400" /> @endif
                </div>
                <div class="p-2"><p class="truncate text-xs" title="{{ $asset->title }}">{{ $asset->title }}</p><p class="mt-1 text-[10px] uppercase tracking-wide text-slate-500">{{ $asset->type }} / {{ $asset->size_formatted }}</p></div>
            </article>
        @empty <p class="col-span-2 rounded border border-dashed border-slate-700 p-5 text-center text-xs text-slate-400">No matching media in this company.</p> @endforelse
    </div>
    @if ($media->hasPages())
        <div class="mt-4 flex items-center justify-between gap-2 text-xs">
            <button type="button" wire:click="previousPage('mediaPage')" @disabled($media->onFirstPage()) class="studio-button">Previous</button>
            <span>{{ $media->currentPage() }}/{{ $media->lastPage() }}</span>
            <button type="button" wire:click="nextPage('mediaPage')" @disabled(!$media->hasMorePages()) class="studio-button">Next</button>
        </div>
    @endif
@else <p class="rounded-lg border border-slate-700 p-4 text-xs text-slate-400">The media.view permission is required to browse Content Library.</p> @endcan
