<header class="shrink-0 border-b border-slate-700 bg-slate-800">
    <div class="flex flex-wrap items-center gap-3 px-4 py-3">
        <a href="{{ route('admin.playlists.index') }}" class="studio-button" title="Back to Playlist" @click="if (dirty && !confirm('Leave without saving properties?')) $event.preventDefault()"><x-studio-icon name="back" /><span class="hidden xl:inline">Playlists</span></a>
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <x-studio-icon name="page" class="h-5 w-5 text-cyan-300" />
            <span class="hidden text-sm text-slate-400 lg:inline">Playlist Studio /</span>
            <input aria-label="Playlist name" wire:model="name" @input="dirty = true" @cannot('playlists.edit') readonly @endcannot maxlength="150" class="min-w-0 flex-1 truncate rounded border-transparent bg-transparent px-2 py-1 text-sm font-semibold text-white focus:border-cyan-500 focus:ring-cyan-500" />
        </div>
        <button x-ref="previewTrigger" type="button" class="studio-button" title="Preview saved layout" @click="openPreview()"><x-studio-icon name="eye" /><span class="hidden sm:inline">Preview Layout</span></button>
        @can('playlists.edit')
            <button type="button" class="studio-button !border-cyan-600 !bg-cyan-600 text-white hover:!bg-cyan-500" wire:click="save" wire:loading.attr="disabled"><x-studio-icon name="save" />Save</button>
        @else <span class="text-xs text-slate-400">View only</span> @endcan
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-700/70 px-4 py-2 text-xs">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="studio-button" title="Toggle Pages panel" :aria-pressed="showPages" @click="showPages = !showPages"><x-studio-icon name="page" />Pages</button>
            <span class="mx-1 hidden h-5 border-l border-slate-600 sm:block"></span>
            <button type="button" class="studio-button" aria-label="Zoom out" title="Zoom out" @click="zoom(-0.05)" :disabled="scale <= 0.25"><x-studio-icon name="minus" /></button>
            <span class="w-12 text-center tabular-nums" x-text="Math.round(scale * 100) + '%'"></span>
            <button type="button" class="studio-button" aria-label="Zoom in" title="Zoom in" @click="zoom(0.05)" :disabled="scale >= 2"><x-studio-icon name="plus" /></button>
            <button type="button" class="studio-button" @click="fit()" title="Fit canvas to available area"><x-studio-icon name="fit" />Fit</button>
            <span class="ml-2 text-slate-400">{{ $playlist->canvas_width }} &times; {{ $playlist->canvas_height }} px</span>
        </div>
        <div class="relative flex gap-2">
            <button type="button" class="studio-button" @click="tab = 'properties'; showProperties = true; $nextTick(() => $refs.canvasWidth.focus())" title="Canvas settings"><x-studio-icon name="monitor" />Canvas</button>
            <button type="button" class="studio-button" :aria-expanded="settings" @click="settings = !settings" title="Panel settings"><x-studio-icon name="settings" /><span class="hidden xl:inline">Panels</span></button>
            <div x-cloak x-show="settings" @click.outside="settings = false" class="absolute right-0 top-10 z-30 w-64 space-y-4 rounded-lg border border-slate-600 bg-slate-800 p-4 shadow-xl">
                <label class="flex items-center gap-2"><input type="checkbox" x-model="showPages" class="rounded"> Pages panel</label>
                <label class="block">Pages width <span x-text="leftWidth + 'px'"></span><input aria-label="Pages panel width" type="range" min="180" max="300" x-model.number="leftWidth" class="mt-2 w-full"></label>
                <label class="flex items-center gap-2"><input type="checkbox" x-model="showProperties" class="rounded"> Right panel</label>
                <label class="block">Right panel width <span x-text="rightWidth + 'px'"></span><input aria-label="Right panel width" type="range" min="260" max="420" x-model.number="rightWidth" class="mt-2 w-full"></label>
            </div>
        </div>
    </div>
</header>
