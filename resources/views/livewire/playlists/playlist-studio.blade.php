<div class="playlist-studio flex h-screen h-[100dvh] min-h-0 flex-col overflow-hidden bg-slate-950 text-slate-200"
    x-data="playlistStudio" data-user-id="{{ auth()->id() }}" data-width="{{ $playlist->canvas_width }}" data-height="{{ $playlist->canvas_height }}" data-playback-debug="{{ app()->environment(['local', 'testing']) ? '1' : '0' }}"
    @studio-playback-invalidated.window="invalidatePlayback()"
    @keydown.window="mediaShortcut($event); playbackKey($event)"
    @studio-editor-sync.window="syncEditor($event.detail.data)"
    @studio-select-window.window="requestSelection($event.detail.id)"
    @studio-canvas-resized.window="applyCanvas($event.detail.width, $event.detail.height)"
    @studio-saved.window="applyCanvas($event.detail.width, $event.detail.height); dirty = false"
    @keydown.ctrl.s.window.prevent="@can('playlists.edit') saveAll() @endcan" @keydown.meta.s.window.prevent="@can('playlists.edit') saveAll() @endcan"
    @keydown.escape.window="if (preview) closePreview(); settings = false"
    @beforeunload.window="if (dirty || zoneDirty || mediaDirty || busy || gesture) { $event.preventDefault(); $event.returnValue = ''; }">
    <script type="application/json" x-ref="editorData">@json($editorData)</script>
    @include('livewire.playlists.studio.toolbar')
    <p class="border-b border-slate-800 bg-slate-900 px-4 py-2 text-xs text-slate-400">1. Pilih Page → 2. Tarik media → 3. Atur Window → 4. Klik media untuk zoom/crop → 5. Simpan dan Pratinjau</p>

    <div class="border-b border-slate-800 bg-slate-900 px-4 py-2 text-xs" role="status" aria-live="polite">
        <span wire:loading class="text-cyan-300">Memperbarui Studio...</span>
        <span wire:loading.remove>
            @if (session()->has('success')) <span class="text-emerald-300">{{ session('success') }}</span>
            @else <span class="text-slate-400">{{ $playlist->company->name }} workspace / {{ $pages->count() }} pages</span> @endif
        </span>
        <span x-cloak x-show="dirty" class="ml-3 text-amber-300">Perubahan belum disimpan. Klik Simpan.</span>
        <span x-cloak x-show="busy" class="ml-2 text-cyan-300">Menyimpan perubahan...</span>
        <span x-cloak x-show="zoneDirty" class="ml-2 text-amber-300">Perubahan Window belum disimpan</span>
        <span x-cloak x-show="error" x-text="error" class="ml-2 text-red-300" role="alert"></span>
        <span x-cloak x-show="extentX > width || extentY > height" class="ml-2 text-amber-300">Ada Window di luar kanvas. Perbesar kanvas atau atur kembali posisinya.</span>
        @if ($errors->any())
            <div role="alert" class="mt-1 text-red-300">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="flex min-h-0 flex-1 overflow-hidden">
        <aside x-show="showPages" :style="{ width: leftWidth + 'px' }" class="studio-panel shrink-0 overflow-y-auto border-r border-slate-800 bg-slate-900" aria-label="Playlist pages">
            @include('livewire.playlists.studio.pages')
            <section class="border-t border-slate-700 p-3">@include('livewire.playlists.studio.library')</section>
        </aside>
        <main class="flex min-w-0 flex-1 flex-col overflow-hidden" aria-label="Canvas workspace">
            <div x-ref="viewport" class="studio-workspace min-h-0 flex-1 overflow-auto p-8" tabindex="0" aria-label="Scrollable canvas">
                <div class="flex w-max min-h-full min-w-full items-center justify-center">
                    <div class="relative shrink-0" :style="{ width: width * scale + 'px', height: height * scale + 'px' }">
                        <div x-show="!playbackMode" x-ref="canvas" @dragover="dragOver($event)" @drop="dropMedia($event)" @dragleave.self="dropGhost = null" @pointerdown.self="selectWindow(null)" class="absolute left-0 top-0 origin-top-left overflow-hidden outline outline-1 outline-dashed outline-slate-500 shadow-2xl"
                            :style="{ width: width + 'px', height: height + 'px', transform: 'scale(' + scale + ')', backgroundImage: gridEnabled ? 'linear-gradient(#64748b44 1px, transparent 1px), linear-gradient(90deg, #64748b44 1px, transparent 1px)' : 'none', backgroundSize: gridSize + 'px ' + gridSize + 'px' }"
                            style="background-color: {{ $playlist->background_color }}" aria-label="Playlist output canvas">
                            @include('livewire.playlists.studio.windows', ['interactive' => true])
                            <div x-cloak x-show="dropGhost" class="pointer-events-none absolute border-2 border-dashed border-cyan-300 bg-cyan-500/30" :style="dropGhost ? { left: dropGhost.x + 'px', top: dropGhost.y + 'px', width: dropGhost.width + 'px', height: dropGhost.height + 'px', zIndex: 2000000 } : {}"></div>
                        </div>
                        <div x-show="playbackMode" x-ref="inlinePlayback" wire:ignore class="absolute inset-0 z-30 overflow-hidden"></div>
                        <div x-show="!playbackMode">@include('livewire.playlists.studio.empty-canvas')</div>
                        @can('playlists.edit')
                            @foreach (['e' => 'right: -6px; top: 25%; width: 12px; height: 50%; cursor: ew-resize', 's' => 'bottom: -6px; left: 25%; height: 12px; width: 50%; cursor: ns-resize', 'se' => 'right: -6px; bottom: -6px; width: 14px; height: 14px; cursor: nwse-resize'] as $handle => $style)
                                <button type="button" x-show="advanced && !playbackMode" @pointerdown="startCanvas($event, '{{ $handle }}')" :disabled="busy" class="absolute z-20 touch-none rounded border border-cyan-300 bg-cyan-500/50 hover:bg-cyan-400" style="{{ $style }}" title="Ubah resolusi kanvas {{ $handle }}" aria-label="Resize canvas {{ $handle }}"></button>
                            @endforeach
                        @endcan
                    </div>
                </div>
            </div>
            <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 bg-slate-900 px-4 py-2 text-xs text-slate-400">
                <span>{{ $activePage?->name ?? 'Pilih Page terlebih dahulu' }} / {{ $playlist->canvas_width }} &times; {{ $playlist->canvas_height }} px</span>
                <span x-cloak x-show="gesture" class="text-cyan-300" x-text="gesture?.type === 'canvas' ? width + ' x ' + height + ' px' : (zoneForm ? 'X ' + zoneForm.x + ' Y ' + zoneForm.y + ' / ' + zoneForm.width + ' x ' + zoneForm.height + ' px' : '')"></span>
                <span>Zoom <span x-text="Math.round(scale * 100) + '%'"></span><span x-show="fitMode"> / Fit</span></span>
            </footer>
            <div class="flex flex-wrap justify-between gap-2 border-t border-slate-800 px-4 py-2 text-xs"><button type="button" @click="timelineOpen = !timelineOpen" :aria-expanded="timelineOpen" class="text-cyan-300">Timeline <span x-text="timelineOpen ? '− Tutup' : '+ Buka'"></span></button><button type="button" @click="advanced = !advanced" :aria-expanded="advanced" class="text-slate-300">Pilihan lanjutan <span x-text="advanced ? '−' : '+'"></span></button></div>
            <div x-cloak x-show="timelineOpen">@include('livewire.playlists.studio.timeline', ['inPreview' => false])</div>
        </main>
        <aside x-show="showProperties" :style="{ width: rightWidth + 'px' }" class="studio-panel shrink-0 overflow-y-auto border-l border-slate-800 bg-slate-900" aria-label="Studio library and properties">
            <div class="p-4">
                <p x-show="!selectedZoneId" class="rounded-lg border border-dashed border-slate-600 p-4 text-sm text-slate-400">Klik Window pada kanvas atau daftar di bawah untuk mengatur posisi dan isi medianya.</p>
                @include('livewire.playlists.studio.window-properties')
                @include('livewire.playlists.studio.media-properties')
                <details open class="mt-5 border-t border-slate-700 pt-4"><summary class="mb-4 cursor-pointer text-sm font-semibold">Daftar Window &amp; urutan media</summary>@include('livewire.playlists.studio.file-window')</details>
                <section x-cloak x-show="advanced" class="mt-5 border-t border-slate-700 pt-4">@include('livewire.playlists.studio.properties')</section>
            </div>
        </aside>
    </div>

    @include('livewire.playlists.studio.playback-preview')
</div>
