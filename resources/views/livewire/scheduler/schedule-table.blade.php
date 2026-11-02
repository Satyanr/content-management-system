<div>
    <x-cms.alert />
    @error('schedule')<p class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
    <div wire:loading class="mb-3 text-sm text-blue-600" role="status">Updating Scheduler...</div>
    @if (!$company)
        <x-cms.card><p class="text-amber-700">Select a company workspace before viewing or managing schedules.</p></x-cms.card>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <x-cms.input aria-label="Search schedules" wire:model.live.debounce.350ms="search" placeholder="Search schedules..." class="max-w-xs" />
            <x-cms.select aria-label="Filter Playlist" wire:model.live="playlistFilter"><option value="">All Playlists</option>@foreach($playlists as $playlist)<option value="{{ $playlist->id }}">{{ $playlist->name }}</option>@endforeach</x-cms.select>
            <x-cms.select aria-label="Filter status" wire:model.live="activeFilter"><option value="">Any status</option><option value="1">Active</option><option value="0">Inactive</option></x-cms.select>
            <x-cms.select aria-label="Filter type" wire:model.live="typeFilter"><option value="">Any type</option><option value="once">Once</option><option value="weekly">Weekly</option></x-cms.select>
            <div class="flex gap-2"><x-cms.button wire:click="$set('view', 'list')" :color="$view === 'list' ? 'primary' : 'secondary'">List</x-cms.button><x-cms.button wire:click="$set('view', 'weekly')" :color="$view === 'weekly' ? 'primary' : 'secondary'">Weekly</x-cms.button></div>
            @can('scheduler.create')<x-cms.button wire:click="openCreate" wire:loading.attr="disabled">+ Create Schedule</x-cms.button>@endcan
        </div>
        @if (!$showModal) @include('livewire.scheduler.conflicts') @endif
        @if ($view === 'weekly')
            @include('livewire.scheduler.weekly')
        @else
            @include('livewire.scheduler.list')
            <div class="my-4">{{ $schedules->links() }}</div>
        @endif
        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <x-cms.card title="What's Playing at Time?" :subtitle="'Local time in '.$timezone">
                <form wire:submit="inspectTime" class="flex flex-wrap items-start gap-3">
                    <x-cms.input aria-label="Inspect local date and time" type="datetime-local" wire:model="probeTime" name="probeTime" required />
                    <x-cms.button type="submit" wire:loading.attr="disabled">Resolve</x-cms.button>
                </form>
                @error('probeTime')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @if ($at)
                    <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
                        <p class="font-semibold">{{ $playing?->playlist->name ?? 'No scheduled Playlist' }}</p>
                        <p>{{ $at->setTimezone($timezone)->format('Y-m-d H:i P') }}</p>
                        @if($playing)<p>{{ $playing->name }} / Priority {{ $playing->priority }} / Schedule #{{ $playing->id }}</p><p class="mt-1 text-xs">Highest priority wins; equal priority uses the lowest Schedule ID.</p>@endif
                    </div>
                @endif
                <p class="mt-3 text-xs text-gray-500">During a repeated local hour, this lookup uses its first occurrence. Nonexistent local times are rejected.</p>
            </x-cms.card>
            <x-cms.card title="Upcoming 7 Days" :subtitle="'Company-wide resolver winners, including unscheduled gaps / '.$timezone">
                <div class="max-h-96 space-y-2 overflow-y-auto">
                    @foreach(array_slice($upcoming,0,$upcomingLimit) as $segment)
                        <div class="rounded border border-gray-200 p-3 text-xs">
                            <p class="font-semibold text-gray-800">{{ $segment['schedule']?->playlist->name ?? 'No scheduled Playlist' }}</p>
                            <p class="mt-1 text-gray-500">{{ $segment['start']->setTimezone($timezone)->format('D d M H:i P') }} - {{ $segment['end']->setTimezone($timezone)->format('D d M H:i P') }}</p>
                            @if($segment['schedule'])<p class="mt-1 text-gray-500">{{ $segment['schedule']->name }} / Priority {{ $segment['schedule']->priority }}</p>@endif
                        </div>
                    @endforeach
                </div>
                @if(count($upcoming)>$upcomingLimit)<x-cms.button color="secondary" wire:click="showMoreUpcoming" class="mt-3">Show more ({{ min($upcomingLimit,count($upcoming)) }}/{{ count($upcoming) }})</x-cms.button>@endif
            </x-cms.card>
        </div>
    @endif
    @include('livewire.scheduler.form')
    @if($detail)
        <x-cms.modal :show="true" title="Schedule Details" maxWidth="max-w-xl">
            <x-slot name="close"><button type="button" wire:click="closeDetails" aria-label="Close details">&times;</button></x-slot>
            <div class="space-y-2 p-5 text-sm text-gray-700">
                <h3 class="font-semibold">{{ $detail['name'] }}</h3><p>Playlist: {{ $detail['playlist'] }}</p>
                <p>{{ $detail['timezone'] }} / Priority {{ $detail['priority'] }} / {{ $detail['is_active'] ? 'Active' : 'Inactive' }}</p>
                @if($detail['schedule_type']==='once')<p>{{ $detail['start'] }} - {{ $detail['end'] }}</p>
                @else <p>{{ $detail['start_date'] }} - {{ $detail['end_date'] ?: 'No end date' }}</p><p>{{ $detail['start_time'] }} - {{ $detail['end_time'] }} / ISO days {{ implode(', ', $detail['weekdays']) }}</p>@endif
                @can('scheduler.edit')<x-cms.button wire:click="edit({{ $detail['id'] }})">Edit Schedule</x-cms.button>@endcan
            </div>
        </x-cms.modal>
    @endif
</div>
