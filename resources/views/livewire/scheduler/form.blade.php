<x-cms.modal :show="$showModal" :title="$scheduleId ? 'Edit Schedule' : 'Create Schedule'" maxWidth="max-w-2xl">
    <x-slot name="close"><button type="button" wire:click="closeModal" aria-label="Close schedule form" class="text-xl text-gray-500">&times;</button></x-slot>
    @if($showModal)
        <form wire:submit="save({{ $conflictReview && count($conflicts) ? 'true' : 'false' }})" class="max-h-[80vh] overflow-y-auto">
            <div class="space-y-4 p-5">
                @if($errors->any())<p class="rounded bg-red-50 p-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>@endif
                <x-cms.input label="Schedule name" name="form.name" wire:model="form.name" maxlength="150" required />
                <x-cms.select label="Playlist" name="form.playlist_id" wire:model="form.playlist_id" required><option value="">Select active Playlist</option>@foreach($playlists as $playlist)<option value="{{ $playlist->id }}" @disabled(!$playlist->is_active && (int)($form['playlist_id'] ?? 0) !== $playlist->id)>{{ $playlist->name }}{{ $playlist->is_active ? '' : ' (inactive - choose an active Playlist)' }}</option>@endforeach</x-cms.select>
                <div class="grid grid-cols-2 gap-4">
                    <x-cms.select label="Schedule type" name="form.schedule_type" wire:model.live="form.schedule_type"><option value="once">Once</option><option value="weekly">Weekly</option></x-cms.select>
                    <x-cms.input label="Priority (higher wins)" name="form.priority" type="number" min="-1000000" max="1000000" wire:model="form.priority" required />
                </div>
                <x-cms.input label="Timezone (IANA)" name="form.timezone" list="scheduler-timezones" wire:model="form.timezone" required />
                <datalist id="scheduler-timezones">@foreach(\App\Services\ScheduleClock::timezones() as $tz)<option value="{{ $tz }}">@endforeach</datalist>
                <p class="text-xs text-gray-500">Timezone is saved with this Schedule. Future company timezone changes do not alter it.</p>
                @if(($form['schedule_type'] ?? 'once') === 'once')
                    <div class="grid grid-cols-2 gap-4"><x-cms.input label="Local start" name="form.once_start" type="datetime-local" wire:model="form.once_start" required /><x-cms.input label="Local end" name="form.once_end" type="datetime-local" wire:model="form.once_end" required /></div>
                @else
                    <div class="grid grid-cols-2 gap-4"><x-cms.input label="Start date" name="form.start_date" type="date" wire:model="form.start_date" required /><x-cms.input label="End date (optional)" name="form.end_date" type="date" wire:model="form.end_date" /></div>
                    <div class="grid grid-cols-2 gap-4"><x-cms.input label="Start time" name="form.start_time" type="time" wire:model="form.start_time" required /><x-cms.input label="End time" name="form.end_time" type="time" wire:model="form.end_time" required /></div>
                    <fieldset><legend class="mb-2 text-sm font-medium text-gray-800">Start days</legend><div class="flex flex-wrap gap-3">@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $day=>$name)<label class="flex items-center gap-1 text-xs"><input type="checkbox" wire:model="form.weekdays" value="{{ $day }}" class="rounded border-gray-300">{{ $name }}</label>@endforeach</div>@error('form.weekdays')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror @error('form.weekdays.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</fieldset>
                    <p class="text-xs text-gray-500">An earlier end time means next day. The date range and selected weekday apply to the start day, including on the last date.</p>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300">Active</label>
                <p class="text-xs text-gray-500">DST gaps: Once is rejected; a Weekly occurrence is skipped. Repeated hours: first start and last end are used.</p>
                @include('livewire.scheduler.conflicts')
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t p-4">
                <x-cms.button color="secondary" wire:click="closeModal">Cancel</x-cms.button>
                <x-cms.button color="secondary" wire:click="checkConflicts" wire:loading.attr="disabled">Check conflicts</x-cms.button>
                <x-cms.button type="submit" wire:loading.attr="disabled">{{ $conflictReview && count($conflicts) ? 'Save despite conflicts' : 'Save Schedule' }}</x-cms.button>
            </div>
        </form>
    @endif
</x-cms.modal>
