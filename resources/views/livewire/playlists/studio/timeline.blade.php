@php $inPreview = $inPreview ?? false; @endphp
<section wire:ignore class="flex shrink-0 flex-col overflow-hidden border-t border-slate-700 bg-slate-900" aria-label="{{ $inPreview ? 'Preview' : 'Editor' }} timeline">
    <div class="space-y-2 px-3 py-2">
        @include('livewire.playlists.studio.playback-controls')
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
            <button type="button" @click="timelineOpen = !timelineOpen" :aria-expanded="timelineOpen" class="text-cyan-300">Timeline <span x-text="timelineOpen ? 'Hide' : 'Show'"></span></button>
            <button type="button" @click="tracksOpen = !tracksOpen" :aria-expanded="tracksOpen" class="text-cyan-300">Urutan tiap Window</button>
            <label>Zoom <input type="range" min="8" max="100" step="2" x-model.number="timelineZoom" aria-label="Timeline zoom" class="w-24 align-middle"></label>
            @if (!$inPreview)<label>Tinggi <input type="range" min="140" max="360" step="10" x-model.number="timelineHeight" aria-label="Timeline height" class="w-20 align-middle"></label>@endif
            <button type="button" @click="loadPlayback()" :disabled="busy || loadingPlayback" class="text-cyan-300 disabled:opacity-40">Muat ulang timeline tersimpan</button>
            <span x-cloak x-show="playbackStale" class="text-amber-300">Data tersimpan berubah</span>
            <button type="button" x-cloak x-show="playbackMode && !preview" @click="stopPlayback()" class="text-cyan-300">Kembali ke editor</button>
        </div>
        <div x-cloak x-show="audioBlocked" class="flex flex-wrap items-center gap-2 text-xs text-amber-300" role="alert">Browser blocked playback/audio.
            <button type="button" @click="muted = true; updateAudio(); playPlayback()" class="studio-button">Play muted</button>
            <button type="button" @click="muted = false; updateAudio(); playPlayback()" class="studio-button">Enable Audio</button>
        </div>
        <p x-cloak x-show="playbackError" x-text="playbackError" class="text-xs text-red-300" role="alert"></p>
    </div>
    <div x-show="timelineOpen" :style="{ height: {{ $inPreview ? '200' : 'timelineHeight' }} + 'px' }" class="overflow-auto border-t border-slate-800"
        @scroll="timelineScroll = $el.scrollLeft; timelineViewport = $el.clientWidth">
        <div class="relative min-h-full touch-none" :style="{ width: Math.max(1, playback.total * timelineScale()) + 'px' }"
            @pointerdown="timelinePointer($event)" role="slider" tabindex="0" aria-label="Seek playlist time" aria-valuemin="0" :aria-valuemax="playback.total" :aria-valuenow="playback.time">
            <div class="relative h-6 border-b border-slate-700 text-[10px] text-slate-400">
                <template x-for="tick in rulerTicks()" :key="tick"><span class="absolute top-0 h-full border-l border-slate-600 pl-1" :style="{ left: tick * timelineScale() + 'px' }" x-text="formatTime(tick)"></span></template>
            </div>
            <div class="relative h-8 border-b border-slate-700">
                <template x-for="page in playbackManifest?.pages || []" :key="page.id"><div class="absolute top-1 h-6 truncate rounded border px-2 text-xs" :class="playback.page?.id === page.id ? 'border-cyan-400 bg-cyan-900 text-white' : 'border-slate-600 bg-slate-800 text-slate-300'" :style="{ left: page.start * timelineScale() + 'px', width: Math.max(1, page.duration * timelineScale() - 2) + 'px' }" :title="page.name + ' / ' + page.duration + 's'" x-text="page.name + ' / ' + page.duration + 's'"></div></template>
            </div>
            <div x-show="tracksOpen">
                <template x-for="track in timelineTracks()" :key="track.zone.id">
                    <div class="relative border-b border-slate-800" :style="{ height: collapsedTracks[track.zone.id] ? '24px' : '54px' }">
                        <button type="button" @pointerdown.stop @click.stop="collapsedTracks[track.zone.id] = !collapsedTracks[track.zone.id]" class="sticky left-0 z-10 block max-w-48 truncate bg-slate-900/90 px-2 text-[10px] text-slate-300" :title="track.zone.name" x-text="track.zone.name + (track.zone.visible ? '' : ' / Hidden')"></button>
                        <div x-show="!collapsedTracks[track.zone.id]">
                            <template x-for="segment in track.segments" :key="segment.key"><div class="absolute top-5 h-7 overflow-hidden rounded border px-1 text-[10px] leading-6 text-white" :class="isActiveSegment(segment, track.zone.id) ? 'border-cyan-300 bg-cyan-700' : segment.item.type === 'image' ? 'border-purple-500 bg-purple-900' : 'border-slate-500 bg-slate-700'" :style="{ left: segment.start * timelineScale() + 'px', width: Math.max(1, segment.duration * timelineScale() - 2) + 'px' }" :title="segment.item.title + ' / ' + durationLabel(segment.item)" x-text="segment.item.title + ' / ' + durationLabel(segment.item)"></div></template>
                            <span x-show="track.pending" class="absolute top-6 rounded border border-dashed border-amber-500 px-2 text-xs text-amber-300" :style="{ left: Math.max(timelineScroll, playback.page.start * timelineScale()) + 'px' }">Auto video: Loading metadata / Unknown length</span>
                            <span x-show="!track.pending && !track.segments.length" class="sticky left-2 text-[10px] text-slate-500">Empty, unknown media, or outside visible time range</span>
                        </div>
                    </div>
                </template>
            </div>
            <div class="pointer-events-none absolute inset-y-0 z-20 border-l-2 border-cyan-300" :style="{ left: playback.time * timelineScale() + 'px' }"><span class="absolute -left-1 top-0 h-3 w-2 rounded-b bg-cyan-300"></span></div>
        </div>
    </div>
</section>
