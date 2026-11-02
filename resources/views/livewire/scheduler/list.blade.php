<x-cms.table>
    <x-slot name="head"><tr>@foreach(['Schedule / Playlist','Type / Days / Date range','Start / End','Timezone','Priority','Status','Actions'] as $label)<x-cms.table-header>{{ $label }}</x-cms.table-header>@endforeach</tr></x-slot>
    @forelse($schedules as $s)
        <x-cms.table-row wire:key="schedule-{{ $s->id }}">
            <x-cms.table-cell><button type="button" wire:click="showDetails({{ $s->id }})" class="font-semibold text-blue-700 hover:underline">{{ $s->name }}</button><p class="mt-1 text-xs text-gray-500">{{ $s->playlist->name }}{{ $s->playlist->is_active ? '' : ' (Playlist inactive)' }}</p></x-cms.table-cell>
            <x-cms.table-cell><span class="capitalize">{{ $s->schedule_type }}</span><p class="text-xs text-gray-500">@if($s->schedule_type==='weekly'){{ collect($s->weekdays)->map(fn($d)=>[1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'][$d])->implode(', ') }}<br>{{ $s->start_date }} - {{ $s->end_date ?: 'No end' }}@else{{ $s->starts_at_utc->setTimezone($s->timezone)->format('Y-m-d') }} - {{ $s->ends_at_utc->setTimezone($s->timezone)->format('Y-m-d') }}@endif</p></x-cms.table-cell>
            <x-cms.table-cell>@if($s->schedule_type==='once'){{ $s->starts_at_utc->setTimezone($s->timezone)->format('H:i P') }} - {{ $s->ends_at_utc->setTimezone($s->timezone)->format('H:i P') }}@else{{ substr($s->start_time,0,5) }} - {{ substr($s->end_time,0,5) }}<p class="text-xs text-gray-500">{{ $s->end_time < $s->start_time ? 'Ends next day' : 'Same day' }}</p>@endif</x-cms.table-cell>
            <x-cms.table-cell>{{ $s->timezone }}</x-cms.table-cell><x-cms.table-cell>{{ $s->priority }}</x-cms.table-cell>
            <x-cms.table-cell><x-cms.badge :color="$s->is_active ? 'green' : 'red'">{{ $s->is_active ? 'Active' : 'Inactive' }}</x-cms.badge></x-cms.table-cell>
            <x-cms.table-cell><div class="flex flex-wrap gap-2 text-xs">
                @can('scheduler.edit')<button type="button" wire:click="edit({{ $s->id }})" class="text-blue-700">Edit</button><button type="button" wire:click="toggleActive({{ $s->id }})" wire:loading.attr="disabled" class="text-blue-700">{{ $s->is_active ? 'Disable' : 'Enable' }}</button>@endcan
                @can('scheduler.delete')<button type="button" wire:click="delete({{ $s->id }})" wire:confirm="Delete this Schedule? The Playlist will be kept." wire:loading.attr="disabled" class="text-red-600">Delete</button>@endcan
            </div></x-cms.table-cell>
        </x-cms.table-row>
    @empty <x-cms.empty-state colspan="7" message="No schedules match these filters." />@endforelse
</x-cms.table>
