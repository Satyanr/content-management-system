<div class="dashboard-card space-y-5">
<h1 class="text-xl font-semibold">Tayangkan Playlist: {{ $playlist->name }}</h1>
<x-cms.alert/>
<p class="text-sm text-slate-500">Satu Playlist utama per perangkat. Jadwal hanya mengganti tayangan pada waktunya. Perintah dikonfirmasi setelah Player menerapkan paket lengkap.</p>
<label class="block text-sm">Filter grup<select wire:model.live="group" class="ml-3 rounded-lg border-slate-200"><option value="">Semua grup</option>@foreach($groups as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
@can('devices.assign')<button type="button" wire:click="selectAll" class="rounded-lg bg-slate-100 px-4 py-3">Pilih semua perangkat perusahaan</button>@endcan
<div class="grid gap-3 sm:grid-cols-2">@foreach($devices as $device)<label class="flex items-center gap-3 rounded-lg border p-3"><input type="checkbox" wire:model="selected" value="{{ $device->id }}" @cannot('devices.assign') disabled @endcannot><span>{{ $device->name }}<small class="block text-slate-500">{{ $device->primary_playlist_id === $playlist->id ? 'Playlist utama ini' : 'Playlist utama lain / belum dipilih' }}</small></span></label>@endforeach</div>
@error('ids')<p class="text-red-700">{{ $message }}</p>@enderror
@can('devices.assign')<button type="button" wire:click="apply" wire:confirm="Ganti Playlist utama pada perangkat terpilih dan tayangkan saat sinkronisasi berikutnya?" class="rounded-lg bg-blue-600 px-5 py-3 text-white">Terapkan / Tayangkan Sekarang</button>@endcan
<div><h2 class="font-medium">Menggunakan Playlist ini</h2><ul class="mt-3 space-y-2">@forelse($assigned as $device)<li><a class="text-blue-700" href="{{ route('admin.devices.show',$device->public_id) }}">{{ $device->name }}</a> <span class="text-xs text-slate-500">{{ $device->primary_playlist_id === $playlist->id ? ($device->reported_command_id === $device->playlist_command_id ? 'Utama - perintah dikonfirmasi Player' : 'Utama - menunggu sinkronisasi') : 'Playlist yang terakhir dilaporkan Player (jadwal / override)' }}</span></li>@empty<li class="text-sm text-slate-500">Belum ada perangkat yang memilih Playlist ini.</li>@endforelse</ul></div>
</div>
