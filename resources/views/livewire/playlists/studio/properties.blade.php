<form @submit.prevent="saveProperties()" @input="dirty = true" class="space-y-5">
    <fieldset @cannot('playlists.edit') disabled @endcannot class="space-y-4">
        <legend class="mb-3 text-sm font-semibold">Pengaturan Kanvas</legend>
        <p class="text-xs text-slate-400">Ukuran asli dalam piksel. Zoom tampilan tidak mengubah resolusi output.</p>
        <div class="grid grid-cols-2 gap-3">
            <label class="studio-label">Width (px)<input x-ref="canvasWidth" type="number" min="64" max="16384" required wire:model="canvasWidth" class="studio-input mt-1" /></label>
            <label class="studio-label">Height (px)<input type="number" min="64" max="16384" required wire:model="canvasHeight" class="studio-input mt-1" /></label>
        </div>
        <div class="grid grid-cols-2 gap-2">
            @foreach ([[1920,1080,'FHD'],[1080,1920,'Portrait'],[1920,384,'LED Banner'],[4864,512,'Ultra Wide']] as [$w,$h,$label])
                <button type="button" class="studio-button justify-center" @click="$wire.canvasWidth = {{ $w }}; $wire.canvasHeight = {{ $h }}; dirty = true" title="{{ $w }} &times; {{ $h }} px">{{ $label }}</button>
            @endforeach
        </div>
        <label class="studio-label block">Warna latar
            <div class="mt-2 flex items-center gap-2"><input aria-label="Canvas color picker" type="color" wire:model="backgroundColor" class="h-9 w-12 rounded border border-slate-600 bg-slate-800" /><input aria-label="Canvas background hex" type="text" wire:model="backgroundColor" maxlength="7" class="studio-input" /></div>
        </label>
    </fieldset>
    @if ($activePage)
        <fieldset @cannot('playlists.edit') disabled @endcannot class="space-y-4 border-t border-slate-700 pt-4">
            <legend class="px-1 text-sm font-semibold">Pengaturan Page</legend>
            <label class="studio-label block">Nama Page<input x-ref="pageName" type="text" required maxlength="100" wire:model="pageName" class="studio-input mt-1" /></label>
            <label class="studio-label block">Durasi (detik)<input type="number" min="1" max="86400" required wire:model="pageDuration" class="studio-input mt-1" /></label>
        </fieldset>
    @endif
    @can('playlists.edit') <button type="submit" wire:loading.attr="disabled" :disabled="busy || !!gesture" class="studio-button w-full justify-center !border-cyan-600 !bg-cyan-600 text-white"><x-studio-icon name="save" />Simpan Pengaturan</button> @endcan
</form>
