<div class="flex flex-wrap items-center gap-2 text-xs" aria-label="Playback controls">
    <button type="button" @click="playPlayback()" :disabled="busy || loadingPlayback || !playback.total" class="studio-button">Play</button>
    <button type="button" @click="pausePlayback()" :disabled="playback.state !== 'Playing'" class="studio-button">Pause</button>
    <button type="button" @click="stopPlayback()" :disabled="!playback.total" class="studio-button">Stop</button>
    <button type="button" @click="restartPlayback()" :disabled="busy || !playback.total" class="studio-button">Restart</button>
    <button type="button" @click="pagePlayback(-1)" :disabled="!playback.total || playbackStale" class="studio-button" title="Previous Page">Previous</button>
    <button type="button" @click="pagePlayback(1)" :disabled="!playback.total || playbackStale" class="studio-button" title="Next Page">Next</button>
    <label class="flex items-center gap-1"><input type="checkbox" x-model="loopPlaylist" @change="setLoop()" class="rounded">Loop</label>
    <label class="flex items-center gap-1"><input type="checkbox" x-model="muted" @change="updateAudio()" class="rounded">Mute</label>
    <label class="flex items-center gap-1">Volume<input aria-label="Playback volume" type="range" min="0" max="1" step="0.05" x-model.number="volume" @input="updateAudio()" class="w-16"></label>
    <span class="tabular-nums text-cyan-300" x-text="formatTime(playback.time) + ' / ' + formatTime(playback.total)"></span><span class="text-slate-400">1x</span>
    <span class="text-slate-400" role="status" x-text="playbackStatus()"></span>
</div>
