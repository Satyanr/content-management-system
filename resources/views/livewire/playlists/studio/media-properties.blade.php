<template x-if="mediaForm && selectedMediaId">
    <form @submit.prevent="saveMedia()" @input="mediaDirty = true" class="mt-5 space-y-3 border-t border-slate-700 pt-4">
        <h2 class="text-sm font-semibold text-cyan-300">Isi gambar / video</h2>
        <p class="text-xs leading-relaxed text-slate-400">Zoom dan geser hanya mengubah isi media ini. Kotak Window dan file asli tetap sama.</p>
        <label class="studio-label block">Item dalam Window<select :value="selectedMediaId" @change="selectMedia(Number($event.target.value)); $event.target.value = selectedMediaId" class="studio-input mt-1"><template x-for="item in zones[selectedZoneId]?.items || []" :key="item.id"><option :value="item.id" x-text="item.title"></option></template></select></label>
        <fieldset :disabled="!canEdit || !canMedia || busy || !!gesture" class="space-y-3">
            <label class="studio-label block">Zoom <span x-text="Math.round(mediaForm.scale * 100) + '%'"></span><input aria-label="Zoom media" type="range" min="1" max="5" step="0.05" x-model.number="mediaForm.scale" class="mt-2 w-full"></label>
            <label class="studio-label block">Geser horizontal <span x-text="mediaForm.offset_x + '%'"></span><input aria-label="Geser media horizontal" type="range" min="-100" max="100" step="1" x-model.number="mediaForm.offset_x" class="mt-2 w-full"></label>
            <label class="studio-label block">Geser vertikal <span x-text="mediaForm.offset_y + '%'"></span><input aria-label="Geser media vertikal" type="range" min="-100" max="100" step="1" x-model.number="mediaForm.offset_y" class="mt-2 w-full"></label>
            <label class="studio-label block">Tampilan media<select x-model="mediaForm.fit_mode" @change="mediaFitChanged()" class="studio-input mt-1"><option value="contain">Fit — tampil utuh</option><option value="cover">Fill / Crop — isi Window</option><option value="stretch">Stretch — regangkan</option></select></label>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" x-model="mediaForm.keep_aspect_ratio" @change="if (!mediaForm.keep_aspect_ratio) mediaForm.fit_mode = 'stretch'; else if (mediaForm.fit_mode === 'stretch') mediaForm.fit_mode = 'contain'" class="rounded">Jaga rasio media</label>
            <div class="grid grid-cols-2 gap-2"><button type="button" @click="resetMedia()" title="Reset Media / Fit ke Window (Alt+R atau Alt+F)" class="studio-button justify-center">Reset / Fit</button><button type="submit" class="studio-button justify-center !bg-cyan-700">Simpan Media</button></div>
            <button type="button" x-show="mediaDirty" @click="mediaDirty = false; syncMediaSelection()" class="studio-button w-full justify-center">Batalkan perubahan media</button>
        </fieldset>
    </form>
</template>
