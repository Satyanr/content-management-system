<div>
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-cms.input wire:model.live="search" placeholder="Search logs..." />

        <x-cms.select wire:model.live="module">
            <option value="">All Modules</option>
            @foreach ($modules as $item)
                <option value="{{ $item }}">{{ $item }}</option>
            @endforeach
        </x-cms.select>

        <x-cms.select wire:model.live="action">
            <option value="">All Actions</option>
            @foreach ($actions as $item)
                <option value="{{ $item }}">{{ $item }}</option>
            @endforeach
        </x-cms.select>
    </div>

    <x-cms.table>
        <x-slot name="head">
            <x-cms.table-header>Date</x-cms.table-header>
            <x-cms.table-header>User</x-cms.table-header>
            <x-cms.table-header>Company</x-cms.table-header>
            <x-cms.table-header>Module</x-cms.table-header>
            <x-cms.table-header>Action</x-cms.table-header>
            <x-cms.table-header>Description</x-cms.table-header>
            <x-cms.table-header>IP</x-cms.table-header>
        </x-slot>

        @forelse ($logs as $log)
            <x-cms.table-row>
                <x-cms.table-cell>
                    {{ $log->created_at->format('d M Y H:i') }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $log->user?->name ?? '-' }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $log->company?->name ?? '-' }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    <x-cms.badge color="gray">
                        {{ $log->module ?? '-' }}
                    </x-cms.badge>
                </x-cms.table-cell>

                <x-cms.table-cell>
                    @php
                        $color = match ($log->action) {
                            'created' => 'green',
                            'updated' => 'yellow',
                            'deleted' => 'red',
                            default => 'blue',
                        };
                    @endphp

                    <x-cms.badge :color="$color">
                        {{ $log->action }}
                    </x-cms.badge>
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $log->description ?? '-' }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $log->ip_address ?? '-' }}
                </x-cms.table-cell>
            </x-cms.table-row>
        @empty
            <x-cms.table-row>
                <x-cms.table-cell colspan="7">
                    <x-cms.empty-state title="No activity logs found" description="Activity logs will appear here." />
                </x-cms.table-cell>
            </x-cms.table-row>
        @endforelse
    </x-cms.table>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
