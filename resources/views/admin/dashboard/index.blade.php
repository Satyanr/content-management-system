@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="mb-2 text-xs font-medium uppercase tracking-widest text-blue-700">Ringkasan operasional</p><h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Dashboard</h1><p class="mt-2 text-sm text-slate-500">Kelola konten, jadwal tayang, dan kondisi perangkat Anda.</p></div>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm"><p class="text-xs text-slate-400">Workspace aktif</p><p class="mt-1 font-medium text-slate-800">{{ $workspaceName }}</p></div>
    </div>
    @if ($allCompanies)<div class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm leading-relaxed text-blue-900">Ringkasan agregat semua perusahaan. Pilih workspace melalui pemilih perusahaan untuk melihat jadwal tayang dan melakukan perubahan.</div>@endif
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($deviceStats !== null)
            <x-cms.kpi label="Total Perangkat" :value="$deviceStats['total']" :detail="$deviceStats['pending'].' menunggu aktivasi / '.$deviceStats['other'].' nonaktif/dicabut'" icon="monitor"/>
            <x-cms.kpi label="Perangkat Online" :value="$deviceStats['online']" :detail="$deviceStats['offline'].' offline / belum terhubung. Online mengikuti heartbeat dan credential valid.'" icon="activity"/>
        @endif
        @if ($activePlaylists !== null)<x-cms.kpi label="Playlist Aktif" :value="$activePlaylists" detail="Playlist yang diaktifkan dalam workspace ini." icon="video"/>@endif
        @if ($scheduleStats !== null)<x-cms.kpi label="Jadwal Aktif Saat Ini" :value="$scheduleStats['active']" :detail="$scheduleStats['upcoming'].' jadwal mendatang dalam 7 hari. Termasuk jadwal yang bertumpuk.'" icon="calendar"/>@endif
    </div>
    @if ($deviceStats === null && $activePlaylists === null && $scheduleStats === null)<div class="dashboard-card text-sm text-slate-500">Akun Anda belum memiliki akses ringkasan operasional. Hubungi administrator untuk mendapatkan hak akses modul yang diperlukan.</div>@endif
    @can('scheduler.view') @can('playlists.view')
    <section class="dashboard-card">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold text-slate-900">Dijadwalkan Tayang</h2><span class="text-xs text-slate-400">Berdasarkan Scheduler &middot; bukan telemetri layar</span></div>
        @if ($allCompanies)<p class="mt-5 text-sm text-slate-500">Pilih satu workspace untuk melihat Playlist yang dijadwalkan. Setiap perusahaan memiliki jadwalnya sendiri.</p>
        @elseif ($scheduled)
            <div class="mt-5 flex items-start gap-4"><span class="rounded-xl bg-blue-50 p-3 text-blue-600"><x-studio-icon name="video" class="h-6 w-6"/></span><div class="min-w-0"><h3 class="break-words font-semibold text-slate-900">{{ $scheduled->playlist->name }}</h3><p class="mt-1 text-sm text-slate-600">{{ $scheduled->name }}</p>
            @if ($scheduledWindow)<p class="mt-2 text-xs text-slate-500">{{ $scheduledWindow['start']->setTimezone($scheduled->timezone)->format('d M Y H:i') }} &middot; {{ $scheduledWindow['end']->setTimezone($scheduled->timezone)->format('d M Y H:i') }} &middot; {{ $scheduled->timezone }}</p>@endif
            @if ($transition)<p class="mt-2 text-xs text-blue-700">Transisi berikutnya: {{ $transition->setTimezone($scheduled->timezone)->format('d M Y H:i') }} &middot; {{ $scheduled->timezone }}</p>@else<p class="mt-2 text-xs text-slate-400">Tidak ada transisi dalam 7 hari mendatang.</p>@endif</div></div>
        @else<div class="mt-5 rounded-xl bg-slate-50 p-5"><h3 class="font-medium text-slate-800">Belum ada jadwal aktif</h3><p class="mt-2 text-sm text-slate-500">Siapkan Playlist lalu atur waktu tayangnya di Jadwal Tayang.</p><a href="{{ route('admin.scheduler.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-medium text-blue-700">Buka Jadwal Tayang &rarr;</a>@if ($transition)<p class="mt-2 text-xs text-slate-500">Transisi berikutnya: {{ $transition->format('d M Y H:i') }} UTC</p>@endif</div>@endif
    </section>
    @endcan @endcan
    <section class="dashboard-card">
        <div class="mb-5 flex items-center justify-between"><h2 class="text-lg font-semibold text-slate-900">Akses Cepat</h2><button type="button" @click="$dispatch('cms-guide-open')" class="min-h-11 text-sm font-medium text-blue-700">Panduan memulai</button></div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['media.view'=>['admin.media-assets.index','Konten','Unggah dan kelola gambar/video','upload'], 'playlists.view'=>['admin.playlists.index','Playlist','Buat dan susun tayangan','plus'], 'scheduler.view'=>['admin.scheduler.index','Jadwal Tayang','Atur waktu pemutaran','calendar'], 'devices.view'=>['admin.devices.index','Perangkat','Daftarkan dan pantau layar','monitor']] as $permission=>[$route,$label,$tip,$icon])
                @can($permission)<a href="{{ route($route) }}" class="group flex min-h-28 flex-col items-start rounded-xl border border-slate-100 bg-slate-50 p-4 hover:border-blue-200 hover:bg-blue-50"><x-studio-icon :name="$icon" class="mb-3 h-5 w-5 text-blue-600"/><span class="text-sm font-semibold text-slate-800">Buka {{ $label }}</span><span class="mt-1 text-xs text-slate-500">{{ $tip }}</span></a>@endcan
            @endforeach
        </div>
        <p class="mt-4 text-xs leading-relaxed text-slate-400">Mulai dari Konten &rarr; susun Playlist &rarr; atur Jadwal &rarr; aktifkan Perangkat. Tindakan tersedia di halaman modul sesuai hak akses Anda.</p>
    </section>
    <div class="grid items-start gap-6 xl:grid-cols-5">
        @if ($deviceStats !== null)
        <section class="dashboard-card xl:col-span-3">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-semibold text-slate-900">Status Perangkat</h2><a href="{{ route('admin.devices.index') }}" class="min-h-11 py-3 text-sm font-medium text-blue-700">Lihat semua &rarr;</a></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recentDevices as $device)
                    @php $status = $device->connectionStatus(); $label = ['Online'=>'Online','Offline'=>'Offline','Pending'=>'Menunggu aktivasi','Disabled'=>'Nonaktif','Revoked'=>'Akses dicabut','Never connected'=>'Belum terhubung'][$status] ?? $status; @endphp
                    <li><a href="{{ route('admin.devices.show', $device->public_id) }}" class="flex items-start justify-between gap-3 rounded-lg py-4 hover:bg-slate-50"><div class="min-w-0"><p class="break-words text-sm font-medium text-slate-800">{{ $device->name }}</p><p class="mt-1 text-xs text-slate-400">{{ ucfirst($device->platform) }} &middot; Terakhir terhubung: {{ $device->last_seen_at?->locale('id')->diffForHumans() ?? 'Belum pernah' }}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-xs {{ $status === 'Online' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $label }}</span></a></li>
                @empty<li class="rounded-xl bg-slate-50 p-5 text-sm leading-relaxed text-slate-500">Belum ada perangkat. Buka halaman Perangkat untuk mendaftarkan layar dan mendapatkan kode aktivasi.</li>@endforelse
            </ul>
        </section>
        @endif
        @can('activity_logs.view')
        <section class="dashboard-card xl:col-span-2">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-semibold text-slate-900">Aktivitas Terbaru</h2><a href="{{ route('admin.activity-logs.index') }}" class="min-h-11 py-3 text-sm font-medium text-blue-700">Lihat semua &rarr;</a></div>
            <ul class="space-y-4">@forelse ($latestActivities as $activity)<li class="border-l-2 border-blue-100 pl-4"><p class="break-words text-sm text-slate-700">{{ $activity->description ?? $activity->action }}</p><p class="mt-2 text-xs text-slate-400">{{ $activity->user?->name ?? 'Sistem' }} &middot; {{ $activity->created_at?->locale('id')->diffForHumans() }}</p></li>@empty<li class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">Belum ada aktivitas. Perubahan yang tercatat akan tampil di sini.</li>@endforelse</ul>
        </section>
        @endcan
    </div>
    <p class="text-xs text-slate-400">Data saat halaman dibuka: {{ $asOf->format('d M Y H:i:s') }} UTC. Muat ulang halaman untuk memperbarui.</p>
</div>
@endsection
