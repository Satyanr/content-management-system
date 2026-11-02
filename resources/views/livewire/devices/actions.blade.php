<div class="flex flex-wrap gap-2 text-xs">
    <a class="text-blue-700 hover:underline" href="{{ route('admin.devices.show',$device->public_id) }}">Detail</a>
    @can('devices.edit')<button type="button" wire:click="edit({{ $device->id }})" class="text-blue-700">Ubah</button>@endcan
    @can('devices.activate')
        @if($device->status==='pending')<button type="button" wire:click="generateCode({{ $device->id }})" wire:confirm="Buat kode aktivasi baru? Kode sebelumnya yang belum dipakai akan dibatalkan." wire:loading.attr="disabled" class="text-blue-700">Buat Kode Aktivasi</button>@endif
        <button type="button" wire:click="cancelCode({{ $device->id }})" wire:confirm="Batalkan semua kode aktivasi yang belum dipakai pada perangkat ini?" wire:loading.attr="disabled" class="text-gray-600">Batalkan kode aktivasi</button>
    @endcan
    @can('devices.revoke')
        <button type="button" wire:click="revoke({{ $device->id }})" wire:confirm="Cabut seluruh akses dan kode aktivasi perangkat ini? Koneksi akan langsung ditolak." wire:loading.attr="disabled" class="text-red-600">Cabut akses</button>
        @can('devices.activate')@if($device->status==='active')<button type="button" wire:click="rePair({{ $device->id }})" wire:confirm="Aktivasi ulang perangkat ini? Akses lama dicabut dan kode baru ditampilkan satu kali." wire:loading.attr="disabled" class="text-blue-700">Aktivasi ulang</button>@endif@endcan
    @endcan
    @can('devices.edit')
        @if($device->status==='disabled')<button type="button" wire:click="enable({{ $device->id }})" wire:confirm="Aktifkan kembali perangkat? Perangkat harus melakukan aktivasi baru." class="text-blue-700">Aktifkan</button>
        @else @can('devices.revoke')<button type="button" wire:click="disable({{ $device->id }})" wire:confirm="Nonaktifkan perangkat dan cabut seluruh aksesnya?" wire:loading.attr="disabled" class="text-red-600">Nonaktifkan</button>@endcan @endif
    @endcan
    @can('devices.delete')<button type="button" wire:click="delete({{ $device->id }})" wire:confirm="Hapus perangkat beserta riwayat dan aksesnya? Grup tetap disimpan." wire:loading.attr="disabled" class="text-red-600">Hapus</button>@endcan
</div>
