<div wire:poll.60s x-data="{ activation: null }" @device-pairing-code.window="activation = $event.detail" @device-secret-clear.window="activation = null">
    <x-cms.alert />
    <div wire:loading class="mb-3 text-sm text-blue-600" role="status">Updating Devices...</div>
    <div wire:ignore x-cloak x-show="activation" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900" role="alert">
        <div class="flex items-center justify-between"><h2 class="font-semibold">Activation code - shown once</h2><button type="button" @click="activation = null" aria-label="Dismiss activation code">&times;</button></div>
        <p class="mt-2" x-text="activation?.name"></p>
        <input readonly aria-label="One-time activation code" :value="activation?.code || ''" @click="$el.select()" class="mt-2 w-full max-w-sm rounded border-amber-300 bg-white font-mono text-lg tracking-wider">
        <p class="mt-2" x-text="'Expires: ' + (activation?.expires || '')"></p>
        <p class="mt-1 text-xs">Copy this code now. It activates one Device once; it is not the Device credential. Closing or refreshing this page will hide it.</p>
    </div>
    @if(!$company)
        <x-cms.card><p class="text-amber-700">Pilih workspace perusahaan untuk melihat atau mengelola perangkat.</p></x-cms.card>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <x-cms.input aria-label="Cari perangkat" wire:model.live.debounce.350ms="search" placeholder="Cari perangkat..." />
            <x-cms.select aria-label="Platform filter" wire:model.live="platformFilter"><option value="">Semua platform</option>@foreach(['windows','android','linux','other'] as $platform)<option value="{{ $platform }}">{{ ucfirst($platform) }}</option>@endforeach</x-cms.select>
            <x-cms.select aria-label="Activation status filter" wire:model.live="statusFilter"><option value="">Semua status aktivasi</option><option value="pending">Menunggu aktivasi</option><option value="active">Aktif</option><option value="disabled">Dinonaktifkan</option><option value="online">Online</option><option value="offline">Offline</option><option value="never">Belum pernah terhubung</option><option value="revoked">Revoked credential</option></x-cms.select>
            <x-cms.select aria-label="Group filter" wire:model.live="groupFilter"><option value="">Semua Grup</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</x-cms.select>
            <x-cms.select aria-label="Sort Devices" wire:model.live="sortBy">@foreach(['name'=>'Nama','platform'=>'Platform','status'=>'Activation','last_seen_at'=>'Terakhir terhubung','created_at'=>'Created'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</x-cms.select>
            <x-cms.select aria-label="Sort direction" wire:model.live="sortDirection"><option value="asc">Ascending</option><option value="desc">Descending</option></x-cms.select>
            <x-cms.button wire:click="$set('view','grid')" :color="$view==='grid'?'primary':'secondary'">Grid</x-cms.button><x-cms.button wire:click="$set('view','list')" :color="$view==='list'?'primary':'secondary'">Daftar</x-cms.button>
            @can('devices.create')<x-cms.button wire:click="openCreate">+ Tambah Perangkat</x-cms.button>@endcan
        </div>
        <p class="mb-4 text-xs text-gray-500">Status koneksi perangkat / {{ $timezone }} / Interval kabar perangkat: {{ config('devices.heartbeat_interval_seconds') }}s; batas status online: {{ config('devices.heartbeat_freshness_seconds') }}s.</p>
        @if($view==='list')
            <x-cms.table><x-slot name="head"><tr>@foreach(['Perangkat / Lokasi','Platform / Resolusi','Groups','Aktivasi / Koneksi','Terakhir terhubung','Tindakan'] as $label)<x-cms.table-header>{{ $label }}</x-cms.table-header>@endforeach</tr></x-slot>
                @forelse($devices as $device)
                    <x-cms.table-row wire:key="device-row-{{ $device->id }}">
                        <x-cms.table-cell><a href="{{ route('admin.devices.show',$device->public_id) }}" class="font-semibold text-blue-700">{{ $device->name }}</a><p class="mt-1 text-xs text-gray-500">{{ $device->location ?: 'Lokasi belum diisi' }}</p></x-cms.table-cell>
                        <x-cms.table-cell>{{ ucfirst($device->platform) }}<p class="text-xs text-gray-500">{{ $device->screen_width ? $device->screen_width.' x '.$device->screen_height : 'Belum dilaporkan' }} / {{ $device->orientation }}</p></x-cms.table-cell>
                        <x-cms.table-cell>{{ $device->groups->pluck('name')->implode(', ') ?: 'Tanpa Grup' }}</x-cms.table-cell>
                        <x-cms.table-cell><span class="capitalize">{{ $device->status }}</span><p class="mt-1 text-xs {{ $device->connectionStatus()==='Online'?'text-green-700':'text-gray-500' }}">{{ $device->connectionStatus() }}</p></x-cms.table-cell>
                        <x-cms.table-cell>{{ $device->last_seen_at?->setTimezone($timezone)->format('d M Y H:i:s') ?? 'Belum pernah terhubung' }}</x-cms.table-cell>
                        <x-cms.table-cell>@include('livewire.devices.actions')</x-cms.table-cell>
                    </x-cms.table-row>
                @empty<x-cms.empty-state colspan="6" message="Belum ada perangkat. Klik Tambah Perangkat untuk memulai." />@endforelse
            </x-cms.table>
        @else
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @forelse($devices as $device)
                    <x-cms.card wire:key="device-card-{{ $device->id }}">
                        <div class="flex items-start justify-between gap-3"><a href="{{ route('admin.devices.show',$device->public_id) }}" class="truncate text-lg font-semibold text-gray-900">{{ $device->name }}</a><x-cms.badge :color="$device->connectionStatus()==='Online'?'green':'gray'">{{ $device->connectionStatus() }}</x-cms.badge></div>
                        <dl class="my-4 space-y-1 text-xs text-gray-500"><div>{{ ucfirst($device->platform) }} / {{ $device->screen_width ? $device->screen_width.' x '.$device->screen_height : 'Resolusi belum dilaporkan' }} / {{ $device->orientation }}</div><div>Lokasi: {{ $device->location ?: '-' }}</div><div>Groups: {{ $device->groups->pluck('name')->implode(', ') ?: 'Belum ada' }}</div><div class="capitalize">Activation: {{ $device->status }}</div><div>Terakhir terhubung: {{ $device->last_seen_at?->setTimezone($timezone)->format('d M Y H:i:s') ?? 'Belum pernah terhubung' }}</div></dl>
                        @include('livewire.devices.actions')
                    </x-cms.card>
                @empty<x-cms.card><p class="text-sm text-gray-500">Belum ada perangkat. Klik Tambah Perangkat untuk memulai.</p></x-cms.card>@endforelse
            </div>
        @endif
        <div class="my-4">{{ $devices->links() }}</div>
        @include('livewire.devices.groups')
    @endif
    @include('livewire.devices.form')
</div>
