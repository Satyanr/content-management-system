<template x-if="zoneForm">
    <form @submit.prevent="saveWindow()" @input="zoneDirty = true" class="space-y-3">
        <h2 class="text-sm font-semibold text-cyan-300"><span x-text="zoneForm.name"></span> · Posisi &amp; ukuran</h2>
        <p class="text-xs text-slate-400" x-show="zones[selectedZoneId]?.is_locked">Window dikunci. Hapus centang Kunci lalu simpan agar dapat dipindah atau diubah ukurannya.</p>
        <fieldset :disabled="!canEdit || busy || !!gesture" class="space-y-3">
            <label x-show="advanced" class="studio-label block">Nama Window<input type="text" required maxlength="100" x-model="zoneForm.name" class="studio-input mt-1"></label>
            <div class="grid grid-cols-2 gap-2">
                <label class="studio-label">X<input type="number" min="0" max="16384" required x-model.number="zoneForm.x" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
                <label class="studio-label">Y<input type="number" min="0" max="16384" required x-model.number="zoneForm.y" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
                <label class="studio-label">Lebar<input type="number" min="1" max="16384" required x-model.number="zoneForm.width" @input="changeDimension('width')" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
                <label class="studio-label">Tinggi<input type="number" min="1" max="16384" required x-model.number="zoneForm.height" @input="changeDimension('height')" :disabled="zones[selectedZoneId]?.is_locked" class="studio-input mt-1"></label>
            </div>
            <label class="studio-label flex items-center gap-2"><input type="checkbox" x-model="zoneForm.keep_aspect_ratio" class="rounded">Jaga rasio Window</label>
            <div class="grid grid-cols-2 gap-2">@foreach (['fit'=>'Fit / Tengah','full'=>'Kanvas penuh','left'=>'Separuh kiri','right'=>'Separuh kanan'] as $preset=>$label)<button type="button" @click="windowPreset('{{ $preset }}')" :disabled="zones[selectedZoneId]?.is_locked" class="studio-button justify-center !px-1 !text-[10px]">{{ $label }}</button>@endforeach</div>
            <label x-show="advanced" class="studio-label block">Tampilan default item lama<select x-model="zoneForm.fit_mode" class="studio-input mt-1"><option value="contain">Fit</option><option value="cover">Crop</option><option value="stretch">Stretch</option></select></label>
            <label x-show="advanced" class="studio-label block">Layer / Z-index<input type="number" min="-1000000" max="1000000" required x-model.number="zoneForm.z_index" class="studio-input mt-1"></label>
            <div x-show="advanced || zones[selectedZoneId]?.is_locked" class="flex gap-3 text-xs"><label><input type="checkbox" x-model="zoneForm.is_visible" class="rounded"> Tampilkan</label><label><input type="checkbox" x-model="zoneForm.is_locked" class="rounded"> Kunci</label></div>
            <button type="button" x-show="zoneDirty" @click="zoneDirty = false; selectWindow(selectedZoneId)" class="studio-button w-full justify-center">Batalkan perubahan Window</button>
            <button type="submit" class="studio-button w-full justify-center">Simpan Window</button>
            <div x-show="advanced" class="grid grid-cols-2 gap-2">
                @foreach (['forward'=>'Maju satu layer','backward'=>'Mundur satu layer','front'=>'Paling depan','back'=>'Paling belakang'] as $direction => $label)
                    <button type="button" @click="layerWindow(selectedZoneId, '{{ $direction }}')" class="studio-button justify-center !px-1 !text-[10px]">{{ $label }}</button>
                @endforeach
            </div>
            <button type="button" x-show="advanced" @click="deleteWindow(selectedZoneId)" class="studio-button w-full justify-center !text-red-300"><x-studio-icon name="trash" />Hapus Window</button>
        </fieldset>
        <p class="text-xs text-slate-400">Posisi kotak Window terpisah dari zoom isi media. Buka kunci sebelum mengubah ukuran.</p>
    </form>
</template>
