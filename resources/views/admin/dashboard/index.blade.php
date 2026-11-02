@extends('layouts.admin')

@section('content')
    <x-cms.page-header
        title="Beranda"
        subtitle="Ringkasan workspace dan aktivitas terbaru."
    />

    <x-cms.workflow-tip title="Siapkan tayangan pertama Anda" description="Mulai dari Pustaka Media → Playlist Studio → Jadwal → Perangkat. Panduan selalu tersedia dari tombol Bantuan." />
    <div class="mb-6 flex flex-wrap gap-2">@foreach (['media.view'=>['admin.media-assets.index','1. Pustaka Media'],'playlists.view'=>['admin.playlists.index','2. Playlist'],'scheduler.view'=>['admin.scheduler.index','3. Jadwal'],'devices.view'=>['admin.devices.index','4. Perangkat']] as $permission=>[$route,$label])@can($permission)<a href="{{ route($route) }}" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:border-blue-300">{{ $label }}</a>@endcan @endforeach</div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <x-cms.card>
            <div class="text-sm text-gray-500">Perusahaan</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">
                {{ $workspaceCompanyId ? 1 : $totalCompanies }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                {{ $workspaceCompanyId ? 'Workspace terpilih' : 'Semua perusahaan' }}
            </div>
        </x-cms.card>

        <x-cms.card>
            <div class="text-sm text-gray-500">Pengguna</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">
                {{ $totalUsers }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Pengguna terdaftar
            </div>
        </x-cms.card>

        <x-cms.card>
            <div class="text-sm text-gray-500">Online</div>
            <div class="mt-2 text-3xl font-bold text-green-600">
                {{ $onlineUsers }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Active sessions
            </div>
        </x-cms.card>

        <x-cms.card>
            <div class="text-sm text-gray-500">Nonaktif</div>
            <div class="mt-2 text-3xl font-bold text-yellow-600">
                {{ $inactiveSessions }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Idle sessions
            </div>
        </x-cms.card>

        <x-cms.card>
            <div class="text-sm text-gray-500">Activity Logs</div>
            <div class="mt-2 text-3xl font-bold text-gray-900">
                {{ $totalActivityLogs }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Total recorded actions
            </div>
        </x-cms.card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-cms.card>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Aktivitas Terbaru
                    </h2>
                    <p class="text-sm text-gray-500">
                        Recent CRUD and system actions.
                    </p>
                </div>

                @can('activity_logs.view')
                    <a
                        href="{{ route('admin.activity-logs.index') }}"
                        class="text-sm font-medium text-blue-600 hover:underline"
                    >
                        Lihat semua
                    </a>
                @endcan
            </div>

            <div class="space-y-3">
                @forelse ($latestActivities as $activity)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $activity->description ?? '-' }}
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $activity->user?->name ?? 'System' }}
                                    @if ($activity->company)
                                        · {{ $activity->company->name }}
                                    @endif
                                </div>
                            </div>

                            <x-cms.badge color="gray">
                                {{ $activity->action }}
                            </x-cms.badge>
                        </div>

                        <div class="mt-2 text-xs text-gray-400">
                            {{ $activity->created_at?->format('d M Y H:i') }}
                        </div>
                    </div>
                @empty
                    <x-cms.empty-state
                        title="No activity found"
                        description="Recent activities will appear here."
                    />
                @endforelse
            </div>
        </x-cms.card>

        <x-cms.card>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Riwayat Login Terbaru
                    </h2>
                    <p class="text-sm text-gray-500">
                        Sesi pengguna terbaru.
                    </p>
                </div>

                @can('login_histories.view')
                    <a
                        href="{{ route('admin.login-histories.index') }}"
                        class="text-sm font-medium text-blue-600 hover:underline"
                    >
                        Lihat semua
                    </a>
                @endcan
            </div>

            <div class="space-y-3">
                @forelse ($latestLogins as $login)
                    @php
                        $sessionLifetime = (int) config('session.lifetime', 120);

                        $isOnline = ! $login->logout_at
                            && $login->last_activity_at
                            && $login->last_activity_at->greaterThanOrEqualTo(now()->subMinutes($sessionLifetime));
                    @endphp

                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $login->user?->name ?? 'Deleted User' }}
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $login->user?->email ?? '-' }}
                                    @if ($login->company)
                                        · {{ $login->company->name }}
                                    @endif
                                </div>
                            </div>

                            @if ($login->logout_at)
                                <x-cms.badge color="gray">
                                    Logged Out
                                </x-cms.badge>
                            @elseif ($isOnline)
                                <x-cms.badge color="green">
                                    Online
                                </x-cms.badge>
                            @else
                                <x-cms.badge color="yellow">
                                    Nonaktif
                                </x-cms.badge>
                            @endif
                        </div>

                        <div class="mt-2 text-xs text-gray-400">
                            Login: {{ $login->login_at?->format('d M Y H:i') ?? '-' }}
                        </div>

                        <div class="text-xs text-gray-400">
                            Last activity: {{ $login->last_activity_at?->format('d M Y H:i') ?? '-' }}
                        </div>
                    </div>
                @empty
                    <x-cms.empty-state
                        title="No login history found"
                        description="User login history will appear here."
                    />
                @endforelse
            </div>
        </x-cms.card>
    </div>
@endsection
