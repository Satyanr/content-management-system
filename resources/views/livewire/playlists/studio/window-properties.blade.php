<template x-if="zoneForm">
    <form @submit.prevent="saveWindow()" @input="zoneDirty = true" class="mt-6 space-y-3 border-t border-slate-700 pt-4">
        <h2 class="text-sm font-semibold text-cyan-300">Selected Window</h2>
        <fieldset :disabled="!canEdit || busy || !!gesture" class="space-y-3">
            <label class="studio-label block">Window name<input type="text" required maxlength="100" x-model="zoneForm.name" class="studio-input mt-1"></label>
            <div class="grid grid-cols-2 gap-2">
                <label class="studio-label">X<input type="number" min="0" max="16384" required x-model.number="zoneForm.x" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
                <label class="studio-label">Y<input type="number" min="0" max="16384" required x-model.number="zoneForm.y" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
                <label class="studio-label">Width<input type="number" min="1" max="16384" required x-model.number="zoneForm.width" @input="changeDimension('width')" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
                <label class="studio-label">Height<input type="number" min="1" max="16384" required x-model.number="zoneForm.height" @input="changeDimension('height')" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
            </div>
            <label class="studio-label flex items-center gap-2"><input type="checkbox" x-model="zoneForm.keep_aspect_ratio" class="rounded">Keep Aspect Ratio</label>
            <label class="studio-label block">Fit mode<select x-model="zoneForm.fit_mode" class="studio-input mt-1"><option value="contain">Contain</option><option value="cover">Cover</option><option value="stretch">Stretch</option></select></label>
            <label class="studio-label block">Layer / Z-index<input type="number" min="-1000000" max="1000000" required x-model.number="zoneForm.z_index" class="studio-input mt-1"></label>
            <div class="flex gap-3 text-xs"><label><input type="checkbox" x-model="zoneForm.is_visible" class="rounded"> Visible</label><label><input type="checkbox" x-model="zoneForm.is_locked" class="rounded"> Lock</label></div>
            <button type="button" x-show="zoneDirty" @click="zoneDirty = false; selectWindow(selectedZoneId)" class="studio-button w-full justify-center">Discard Window changes</button>
            <button type="submit" class="studio-button w-full justify-center">Save Window</button>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['forward'=>'Bring Forward','backward'=>'Send Backward','front'=>'Bring to Front','back'=>'Send to Back'] as $direction => $label)
                    <button type="button" @click="layerWindow(selectedZoneId, '{{ $direction }}')" class="studio-button justify-center !px-1 !text-[10px]">{{ $label }}</button>
                @endforeach
            </div>
            <button type="button" @click="deleteWindow(selectedZoneId)" class="studio-button w-full justify-center !text-red-300"><x-studio-icon name="trash" />Delete Window</button>
        </fieldset>
        <p class="text-xs text-slate-400">Window changes save separately from Playlist/Page properties. Unlock and save before changing locked geometry.</p>
    </form>
</template>
