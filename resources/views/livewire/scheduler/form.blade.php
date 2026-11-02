<x-cms.modal :show="$showModal" :title="$scheduleId ? 'Ubah Jadwal' : 'Buat Jadwal'" maxWidth="max-w-2xl">
    <x-slot name="close"><button type="button" wire:click="closeModal" aria-label="Close schedule form" class="text-xl text-gray-500">&times;</button></x-slot>
    @if($showModal)
        <form wire:submit="save({{ $conflictReview && count($conflicts) ? 'true' : 'false' }})" class="max-h-[80vh] overflow-y-auto">
            <div class="space-y-4 p-5">
                @if($errors->any())<p class="rounded bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>@endif
                <x-cms.input label="Nama jadwal" name="form.name" wire:model="form.name" maxlength="150" required />
                <x-cms.select label="Playlist" name="form.playlist_id" wire:model="form.playlist_id" required><option value="">Pilih Playlist aktif</option>@foreach($playlists as $playlist)<option value="{{ $playlist->id }}" @disabled(!$playlist->is_active && (int)($form['playlist_id'] ?? 0) !== $playlist->id)>{{ $playlist->name }}{{ $playlist->is_active ? '' : ' (nonaktif ? pilih Playlist aktif)' }}</option>@endforeach</x-cms.select>
                <div class="grid grid-cols-2 gap-4">
                    <x-cms.select label="Jenis jadwal" name="form.schedule_type" wire:model.live="form.schedule_type"><option value="once">Sekali tayang</option><option value="weekly">Mingguan</option></x-cms.select>
                    <x-cms.input label="Prioritas (angka lebih besar didahulukan)" name="form.priority" type="number" min="-1000000" max="1000000" wire:model="form.priority" required />
                </div>
                <x-cms.input label="Zona waktu (IANA)" name="form.timezone" list="scheduler-timezones" wire:model="form.timezone" required />
                <datalist id="scheduler-timezones">@foreach(\App\Services\ScheduleClock::timezones() as $tz)<option value="{{ $tz }}">@endforeach</datalist>
                <p class="text-xs text-gray-500">Zona waktu disimpan pada jadwal ini. Perubahan zona waktu perusahaan tidak mengubah jadwal yang sudah dibuat.</p>
                @if(($form['schedule_type'] ?? 'once') === 'once')
                    <div class="grid grid-cols-2 gap-4"><x-cms.input label="Mulai (waktu lokal)" name="form.once_start" type="datetime-local" wire:model="form.once_start" required /><x-cms.input label="Selesai (waktu lokal)" name="form.once_end" type="datetime-local" wire:model="form.once_end" required /></div>
                @else
                    <div class="grid grid-cols-2 gap-4"><x-cms.input label="Tanggal mulai" name="form.start_date" type="date" wire:model="form.start_date" required /><x-cms.input label="Tanggal selesai (opsional)" name="form.end_date" type="date" wire:model="form.end_date" /></div>
                    <div class="grid grid-cols-2 gap-4"><x-cms.input label="Waktu mulai" name="form.start_time" type="time" wire:model="form.start_time" required /><x-cms.input label="Waktu selesai" name="form.end_time" type="time" wire:model="form.end_time" required /></div>
                    <fieldset>
<div class="my-4 space-y-3 rounded-lg border border-blue-100 bg-blue-50 p-4">
<label class="block text-sm">Perilaku<select wire:model="form.playback_mode" class="mt-1 w-full rounded-lg border-slate-200"><option value="persistent">Persistent Switch - teruskan sampai pergantian berikutnya</option><option value="temporary">Temporary Override - kembali setelah rentang selesai</option><option value="legacy">Legacy - perilaku jadwal lama</option></select></label>
<p class="text-xs text-slate-500">Persistent memakai waktu mulai sebagai pemicu. Akhir rentang membatasi recurrence, bukan menghentikan Playlist yang sudah dipilih.</p>
<label class="block text-sm">Target<select wire:model.live="form.target_type" class="mt-1 w-full rounded-lg border-slate-200"><option value="all">Semua perangkat perusahaan</option><option value="device">Perangkat tertentu</option><option value="group">Grup perangkat</option></select></label>
@if(($form['target_type'] ?? 'all') !== 'all')<label class="block text-sm">Pilih target (beberapa pilihan)<select multiple wire:model="form.target_ids" class="mt-1 min-h-28 w-full rounded-lg border-slate-200">@foreach((($form['target_type'] ?? '') === 'device' ? \App\Models\Device::forCompany($workspaceId)->orderBy('name')->get() : \App\Models\DeviceGroup::where('company_id',$workspaceId)->orderBy('name')->get()) as $target)<option value="{{ $target->id }}">{{ $target->name }}</option>@endforeach</select></label>@error('form.target_ids')<p class="text-red-700">{{ $message }}</p>@enderror @endif
</div>
<legend class="mb-2 text-sm font-medium text-gray-800">Hari mulai tayang</legend><div class="flex flex-wrap gap-3">@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $day=>$name)<label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="form.weekdays" value="{{ $day }}" class="rounded border-gray-300">{{ $name }}</label>@endforeach</div>@error('form.weekdays')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @error('form.weekdays.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</fieldset>
                    <p class="text-xs text-gray-500">Jika waktu selesai lebih awal, tayangan berakhir keesokan hari. Hari dan rentang tanggal mengacu pada waktu mulai.</p>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300">Aktif</label>
                <details class="text-xs text-gray-500"><summary class="cursor-pointer">Catatan zona waktu</summary><p class="mt-2">Pada pergantian waktu musiman, waktu yang tidak tersedia akan ditolak atau dilewati. Waktu berulang memakai awal pertama dan akhir terakhir.</p></details>
                @include('livewire.scheduler.conflicts')
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t p-4">
                <x-cms.button color="secondary" wire:click="closeModal">Batal</x-cms.button>
                <x-cms.button color="secondary" wire:click="checkConflicts" wire:loading.attr="disabled">Periksa bentrok</x-cms.button>
                <x-cms.button type="submit" wire:loading.attr="disabled">{{ $conflictReview && count($conflicts) ? 'Simpan meski bentrok' : 'Simpan Jadwal' }}</x-cms.button>
            </div>
        </form>
    @endif
</x-cms.modal>
