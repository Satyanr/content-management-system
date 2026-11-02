<div class="playlist-studio flex h-screen h-[100dvh] min-h-0 flex-col overflow-hidden bg-slate-950 text-slate-200"
    x-data="playlistStudio" data-width="{{ $playlist->canvas_width }}" data-height="{{ $playlist->canvas_height }}"
    @studio-editor-sync.window="syncEditor($event.detail.data)"
    @studio-select-window.window="requestSelection($event.detail.id)"
    @studio-canvas-resized.window="applyCanvas($event.detail.width, $event.detail.height)"
    @studio-saved.window="applyCanvas($event.detail.width, $event.detail.height); dirty = false"
    @keydown.ctrl.s.window.prevent="@can('playlists.edit') saveAll() @endcan" @keydown.meta.s.window.prevent="@can('playlists.edit') saveAll() @endcan"
    @keydown.escape.window="if (preview) closePreview(); settings = false"
    @beforeunload.window="if (dirty || zoneDirty || busy || gesture) { $event.preventDefault(); $event.returnValue = ''; }">
    <script type="application/json" x-ref="editorData">@json($editorData)</script>
    @include('livewire.playlists.studio.toolbar')

    <div class="border-b border-slate-800 bg-slate-900 px-4 py-2 text-xs" role="status" aria-live="polite">
        <span wire:loading class="text-cyan-300">Updating Studio...</span>
        <span wire:loading.remove>
            @if (session()->has('success')) <span class="text-emerald-300">{{ session('success') }}</span>
            @else <span class="text-slate-400">{{ $playlist->company->name }} workspace / {{ $pages->count() }} pages</span> @endif
        </span>
        <span x-cloak x-show="dirty" class="ml-3 text-amber-300">Unsaved properties - Save to apply.</span>
        <span x-cloak x-show="busy" class="ml-2 text-cyan-300">Saving interaction...</span>
        <span x-cloak x-show="zoneDirty" class="ml-2 text-amber-300">Unsaved Window properties</span>
        <span x-cloak x-show="error" x-text="error" class="ml-2 text-red-300" role="alert"></span>
        <span x-cloak x-show="extentX > width || extentY > height" class="ml-2 text-amber-300">Some Windows are outside this canvas. Enlarge it or correct their geometry.</span>
        @if ($errors->any())
            <div role="alert" class="mt-1 text-red-300">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="flex min-h-0 flex-1 overflow-hidden">
        <aside x-show="showPages" :style="{ width: leftWidth + 'px' }" class="studio-panel shrink-0 overflow-y-auto border-r border-slate-800 bg-slate-900" aria-label="Playlist pages">
            @include('livewire.playlists.studio.pages')
        </aside>
        <main class="flex min-w-0 flex-1 flex-col overflow-hidden" aria-label="Canvas workspace">
            <div x-ref="viewport" class="studio-workspace min-h-0 flex-1 overflow-auto p-8" tabindex="0" aria-label="Scrollable canvas">
                <div class="flex w-max min-h-full min-w-full items-center justify-center">
                    <div class="relative shrink-0" :style="{ width: width * scale + 'px', height: height * scale + 'px' }">
                        <div x-ref="canvas" @dragover="dragOver($event)" @drop="dropMedia($event)" @dragleave.self="dropGhost = null" @pointerdown.self="selectWindow(null)" class="absolute left-0 top-0 origin-top-left overflow-hidden outline outline-1 outline-dashed outline-slate-500 shadow-2xl"
                            :style="{ width: width + 'px', height: height + 'px', transform: 'scale(' + scale + ')', backgroundImage: gridEnabled ? 'linear-gradient(#64748b44 1px, transparent 1px), linear-gradient(90deg, #64748b44 1px, transparent 1px)' : 'none', backgroundSize: gridSize + 'px ' + gridSize + 'px' }"
                            style="background-color: {{ $playlist->background_color }}" aria-label="Playlist output canvas">
                            @include('livewire.playlists.studio.windows', ['interactive' => true])
                            <div x-cloak x-show="dropGhost" class="pointer-events-none absolute border-2 border-dashed border-cyan-300 bg-cyan-500/30" :style="dropGhost ? { left: dropGhost.x + 'px', top: dropGhost.y + 'px', width: dropGhost.width + 'px', height: dropGhost.height + 'px', zIndex: 2000000 } : {}"></div>
                        </div>
                        @include('livewire.playlists.studio.empty-canvas')
                        @can('playlists.edit')
                            @foreach (['e' => 'right: -6px; top: 25%; width: 12px; height: 50%; cursor: ew-resize', 's' => 'bottom: -6px; left: 25%; height: 12px; width: 50%; cursor: ns-resize', 'se' => 'right: -6px; bottom: -6px; width: 14px; height: 14px; cursor: nwse-resize'] as $handle => $style)
                                <button type="button" @pointerdown="startCanvas($event, '{{ $handle }}')" :disabled="busy" class="absolute z-20 touch-none rounded border border-cyan-300 bg-cyan-500/50 hover:bg-cyan-400" style="{{ $style }}" title="Resize canvas {{ $handle }}" aria-label="Resize canvas {{ $handle }}"></button>
                            @endforeach
                        @endcan
                    </div>
                </div>
            </div>
            <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 bg-slate-900 px-4 py-2 text-xs text-slate-400">
                <span>{{ $activePage?->name ?? 'No page selected' }} / {{ $playlist->canvas_width }} &times; {{ $playlist->canvas_height }} px</span>
                <span x-cloak x-show="gesture" class="text-cyan-300" x-text="gesture?.type === 'canvas' ? width + ' x ' + height + ' px' : (zoneForm ? 'X ' + zoneForm.x + ' Y ' + zoneForm.y + ' / ' + zoneForm.width + ' x ' + zoneForm.height + ' px' : '')"></span>
                <span>Zoom <span x-text="Math.round(scale * 100) + '%'"></span><span x-show="fitMode"> / Fit</span></span>
            </footer>
        </main>
        <aside x-show="showProperties" :style="{ width: rightWidth + 'px' }" class="studio-panel shrink-0 overflow-y-auto border-l border-slate-800 bg-slate-900" aria-label="Studio library and properties">
            <div class="sticky top-0 z-10 grid grid-cols-3 border-b border-slate-800 bg-slate-900 p-2" role="tablist" aria-label="Studio panels" @keydown.right.prevent="cycleTab(1)" @keydown.left.prevent="cycleTab(-1)">
                @foreach (['library' => 'Content Library', 'windows' => 'File Window', 'properties' => 'Properties'] as $key => $label)
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}" :aria-selected="tab === '{{ $key }}'" :tabindex="tab === '{{ $key }}' ? 0 : -1" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'" class="rounded-md px-1 py-2 text-xs">{{ $label }}</button>
                @endforeach
            </div>
            <section x-show="tab === 'library'" id="panel-library" role="tabpanel" aria-labelledby="tab-library" class="p-4">@include('livewire.playlists.studio.library')</section>
            <section x-cloak x-show="tab === 'windows'" id="panel-windows" role="tabpanel" aria-labelledby="tab-windows" class="p-4">@include('livewire.playlists.studio.file-window')</section>
            <section x-cloak x-show="tab === 'properties'" id="panel-properties" role="tabpanel" aria-labelledby="tab-properties" class="p-4">@include('livewire.playlists.studio.properties')</section>
        </aside>
    </div>

    <div x-cloak x-show="preview" class="fixed inset-0 z-50 flex flex-col bg-slate-950 p-6" role="dialog" aria-modal="true" aria-label="Layout preview" @keydown.tab.prevent="$refs.closePreview.focus()">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div><h2 class="font-semibold">Layout Preview - {{ $playlist->name }}</h2><p class="text-xs text-slate-400">Saved layout / {{ $playlist->canvas_width }} &times; {{ $playlist->canvas_height }} / {{ $activePage?->name }} / No playback</p></div>
            <button x-ref="closePreview" type="button" class="studio-button" @click="closePreview()"><x-studio-icon name="close" /> Close</button>
        </div>
        <div class="flex min-h-0 flex-1 items-center justify-center overflow-hidden">
            <div class="relative overflow-hidden outline outline-1 outline-slate-600" :style="{ width: width * previewScale + 'px', height: height * previewScale + 'px', backgroundColor: '{{ $playlist->background_color }}' }">
                @include('livewire.playlists.studio.windows', ['interactive' => false])
                @include('livewire.playlists.studio.empty-canvas')
            </div>
        </div>
    </div>
</div>
