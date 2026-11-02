<x-cms.modal :show="$showModal" :title="$deviceId?'Ubah Perangkat':'Tambah Perangkat'" maxWidth="max-w-xl">
    <x-slot name="close"><button type="button" wire:click="closeModal" aria-label="Close Device form">&times;</button></x-slot>
    @if($showModal)
        <form wire:submit="save" class="max-h-[80vh] overflow-y-auto">
            <div class="space-y-4 p-5">
                @if($errors->any())<p role="alert" class="text-sm text-red-600">{{ $errors->first() }}</p>@endif
                <x-cms.input label="Nama perangkat" name="form.name" wire:model="form.name" maxlength="150" required />
                <x-cms.input label="Deskripsi" name="form.description" wire:model="form.description" maxlength="5000" />
                <x-cms.input label="Lokasi" name="form.location" wire:model="form.location" maxlength="200" />
                <div class="grid grid-cols-2 gap-3"><x-cms.select label="Platform" wire:model="form.platform">@foreach(['windows','android','linux','other'] as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</x-cms.select><x-cms.select label="Orientasi" wire:model="form.orientation"><option value="landscape">Landscape</option><option value="portrait">Portrait</option></x-cms.select></div>
                <div class="grid grid-cols-2 gap-3"><x-cms.input label="Lebar layar (opsional)" type="number" min="1" max="16384" wire:model="form.screen_width" /><x-cms.input label="Tinggi layar (opsional)" type="number" min="1" max="16384" wire:model="form.screen_height" /></div>
                <fieldset><legend class="mb-2 text-sm font-medium">Grup</legend><div class="flex flex-wrap gap-3">@forelse($groups as $group)<label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="form.groups" value="{{ $group->id }}" class="rounded border-gray-300">{{ $group->name }}</label>@empty<p class="text-xs text-gray-500">Belum ada grup. Anda dapat menambahkannya setelah menyimpan perangkat.</p>@endforelse</div></fieldset>
                <p class="text-xs text-gray-500">Simpan perangkat, lalu pilih Buat Kode Aktivasi untuk menghubungkan aplikasi Player.</p>
            </div>
            <div class="flex justify-end gap-2 border-t p-4"><x-cms.button color="secondary" wire:click="closeModal">Batal</x-cms.button><x-cms.button type="submit" wire:loading.attr="disabled">Simpan Perangkat</x-cms.button></div>
        </form>
    @endif
</x-cms.modal>
