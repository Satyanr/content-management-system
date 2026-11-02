<div class="flex items-center justify-between p-4"><h2 class="text-sm font-semibold">Pages</h2><span class="text-xs text-slate-500">{{ $pages->count() }}</span></div>
<div class="space-y-3 px-3 pb-4">
    @forelse ($pages as $page)
        <div wire:key="studio-page-{{ $page->id }}" class="rounded-lg border p-2 {{ $page->id === $activePageId ? 'border-cyan-500 bg-cyan-950/30' : 'border-slate-700 hover:border-slate-500' }}">
            <button type="button" @click="selectPage({{ $page->id }})" :disabled="busy || !!gesture" wire:loading.attr="disabled" aria-pressed="{{ $page->id === $activePageId ? 'true' : 'false' }}" class="block w-full text-left">
                <div class="flex h-24 items-center justify-center overflow-hidden rounded bg-slate-800 p-3">
                    <div class="flex items-center justify-center border border-slate-600" style="width: min(100%, {{ 72 * $playlist->canvas_width / $playlist->canvas_height }}px); aspect-ratio: {{ $playlist->canvas_width }}/{{ $playlist->canvas_height }}; background-color: {{ $playlist->background_color }}"><x-studio-icon name="monitor" class="h-5 w-5 text-slate-400" /></div>
                </div>
                <span class="mt-2 block truncate text-xs font-medium">{{ $page->name }}</span>
                <span class="mt-1 block text-xs text-slate-400">{{ $page->duration_seconds }}s / {{ $page->zones_count }} windows</span>
            </button>
            @can('playlists.edit')
                <div class="mt-2 flex justify-between border-t border-slate-700 pt-2 text-xs">
                    <button type="button" class="text-cyan-300 hover:text-cyan-100" @click="selectPage({{ $page->id }}).then(ok => { if (ok) { tab = 'properties'; showProperties = true; } })">Properties</button>
                    <button type="button" wire:click="deletePage({{ $page->id }})" wire:confirm="Delete this page and its windows?" :disabled="busy || !!gesture || {{ $pages->count() <= 1 ? 'true' : 'false' }}" wire:loading.attr="disabled" class="text-slate-400 hover:text-red-300 disabled:cursor-not-allowed disabled:opacity-30" title="Delete page" aria-label="Delete {{ $page->name }}"><x-studio-icon name="trash" /></button>
                </div>
            @endcan
        </div>
    @empty <p class="p-2 text-xs text-slate-400">This playlist has no pages. Add a page to begin.</p> @endforelse
    @can('playlists.edit')
        <button type="button" @click="addPage()" :disabled="busy || !!gesture" wire:loading.attr="disabled" class="studio-button w-full justify-center border-dashed"><x-studio-icon name="plus" />Add Page</button>
    @endcan
    @error('pages') <p class="text-xs text-red-300" role="alert">{{ $message }}</p> @enderror
</div>
