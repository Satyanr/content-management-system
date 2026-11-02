<div x-ref="previewDialog" x-cloak x-show="preview" class="fixed inset-0 z-50 flex h-screen flex-col overflow-hidden bg-slate-950" role="dialog" aria-modal="true" aria-label="Playback Preview"
    @keydown.tab="trapPreviewFocus($event)">
    <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-800 p-4">
        <div class="min-w-0"><h2 class="truncate font-semibold">Playback Preview - {{ $playlist->name }}</h2><p class="text-xs text-slate-400" x-text="(playback.page?.name || 'No Page') + ' / Saved configuration / ' + (playbackManifest?.width || 0) + ' x ' + (playbackManifest?.height || 0) + ' px'"></p></div>
        <div class="flex gap-2"><button type="button" @click="enterFullscreen()" :disabled="!fullscreenSupported" class="studio-button">Fullscreen</button><button x-ref="closePreview" type="button" class="studio-button" @click="closePreview()"><x-studio-icon name="close" />Close</button></div>
    </header>
    <div x-ref="previewArea" class="flex min-h-0 flex-1 items-center justify-center overflow-hidden p-2">
        <div class="relative overflow-hidden outline outline-1 outline-slate-700" :style="{ width: (playbackManifest?.width || 1) * previewScale + 'px', height: (playbackManifest?.height || 1) * previewScale + 'px' }">
            <div x-ref="previewPlayback" wire:ignore class="absolute inset-0 overflow-hidden"></div>
        </div>
    </div>
    @include('livewire.playlists.studio.timeline', ['inPreview' => true])
</div>
