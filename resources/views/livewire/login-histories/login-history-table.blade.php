<div class="space-y-4">
    <x-cms.card>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-cms.input label="Search" name="search" wire:model.live.debounce.500ms="search"
                    placeholder="Search user, IP, session, browser..." />
            </div>

            <x-cms.select label="Status" name="status" wire:model.live="status">
                <option value="">All Status</option>
                <option value="online">Online</option>
                <option value="inactive">Inactive</option>
                <option value="logged_out">Logged Out</option>
            </x-cms.select>
        </div>
    </x-cms.card>

    <x-cms.card>
        <x-cms.table>
            <x-slot name="head">
                <x-cms.table-header>Login Time</x-cms.table-header>
                <x-cms.table-header>Last Activity</x-cms.table-header>
                <x-cms.table-header>Logout Time</x-cms.table-header>
                <x-cms.table-header>User</x-cms.table-header>
                <x-cms.table-header>Company</x-cms.table-header>
                <x-cms.table-header>IP Address</x-cms.table-header>
                <x-cms.table-header>Status</x-cms.table-header>
            </x-slot>

            @forelse ($histories as $history)
                <x-cms.table-row>
                    <x-cms.table-cell>
                        {{ $history->login_at?->format('d M Y H:i:s') ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $history->last_activity_at?->format('d M Y H:i:s') ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $history->logout_at?->format('d M Y H:i:s') ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        <div class="font-medium text-gray-900 dark:text-white">
                            {{ $history->user?->name ?? 'Deleted User' }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $history->user?->email ?? '-' }}
                        </div>
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $history->company?->name ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $history->ip_address ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        @php
                            $sessionLifetime = (int) config('session.lifetime', 120);

                            $isOnline =
                                !$history->logout_at &&
                                $history->last_activity_at &&
                                $history->last_activity_at->greaterThanOrEqualTo(now()->subMinutes($sessionLifetime));
                        @endphp

                        @if ($history->logout_at)
                            <x-cms.badge color="gray">
                                Logged Out
                            </x-cms.badge>
                        @elseif ($isOnline)
                            <x-cms.badge color="green">
                                Online
                            </x-cms.badge>
                        @else
                            <x-cms.badge color="yellow">
                                Inactive
                            </x-cms.badge>
                        @endif
                    </x-cms.table-cell>
                </x-cms.table-row>

                <x-cms.table-row>
                    <x-cms.table-cell colspan="7">
                        <div class="text-xs text-gray-500 break-all">
                            {{ $history->user_agent ?? '-' }}
                        </div>
                    </x-cms.table-cell>
                </x-cms.table-row>
            @empty
                <x-cms.table-row>
                    <x-cms.table-cell colspan="7">
                        <x-cms.empty-state title="No login history found"
                            description="Login activity will appear here." />
                    </x-cms.table-cell>
                </x-cms.table-row>
            @endforelse
        </x-cms.table>

        <div class="mt-4">
            {{ $histories->links() }}
        </div>
    </x-cms.card>
</div>
