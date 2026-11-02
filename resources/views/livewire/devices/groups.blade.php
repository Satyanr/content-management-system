<x-cms.card title="Device Groups" subtitle="Groups organize Devices only. Company-wide Scheduler behavior is unchanged.">
    @can('devices.create')<x-cms.button wire:click="createGroup" class="mb-4">+ Create Group</x-cms.button>@endcan
    @if($errors->any() && !$showModal && !$showGroupModal)<p class="mb-3 text-sm text-red-600">{{ $errors->first() }}</p>@endif
    <div class="grid gap-3 lg:grid-cols-2">
        @forelse($groups as $group)
            <div wire:key="device-group-{{ $group->id }}" class="rounded-lg border border-gray-200 p-4">
                <h3 class="text-sm font-semibold">{{ $group->name }} <span class="text-xs font-normal text-gray-500">({{ $group->devices->count() }})</span></h3><p class="mt-1 text-xs text-gray-500">{{ $group->description }}</p>
                <div class="my-2 flex gap-3 text-xs">@can('devices.edit')<button wire:click="editGroup({{ $group->id }})" class="text-blue-700">Rename / Edit</button>@endcan @can('devices.delete')<button wire:click="deleteGroup({{ $group->id }})" wire:confirm="Delete this Group? Devices will not be deleted." class="text-red-600">Delete Group</button>@endcan</div>
                <ul class="space-y-1 text-xs text-gray-600">@foreach($group->devices as $member)<li class="flex items-center justify-between gap-2"><span>{{ $member->name }}</span>@can('devices.edit')<button wire:click="removeFromGroup({{ $group->id }},{{ $member->id }})" wire:confirm="Remove this Device from the Group?" class="text-red-600">Remove</button>@endcan</li>@endforeach</ul>
                @can('devices.edit')<div class="mt-3 flex items-start gap-2"><x-cms.select aria-label="Device to add to {{ $group->name }}" wire:model="groupMembers.{{ $group->id }}"><option value="">Choose Device</option>@foreach($memberChoices as $choice)<option value="{{ $choice->id }}">{{ $choice->name }}</option>@endforeach</x-cms.select><x-cms.button color="secondary" wire:click="addToGroup({{ $group->id }})" wire:loading.attr="disabled">Add</x-cms.button></div>@endcan
            </div>
        @empty<p class="text-sm text-gray-500">No Device Groups yet.</p>@endforelse
    </div>
</x-cms.card>
<x-cms.modal :show="$showGroupModal" :title="$groupId?'Edit Device Group':'Create Device Group'" maxWidth="max-w-lg">
    <x-slot name="close"><button wire:click="closeGroup" aria-label="Close Group form">&times;</button></x-slot>
    @if($showGroupModal)<form wire:submit="saveGroup" class="space-y-4 p-5"><x-cms.input label="Group name" name="groupForm.name" maxlength="150" wire:model="groupForm.name" required /><x-cms.input label="Description" name="groupForm.description" maxlength="5000" wire:model="groupForm.description" /><div class="flex justify-end gap-2"><x-cms.button color="secondary" wire:click="closeGroup">Cancel</x-cms.button><x-cms.button type="submit" wire:loading.attr="disabled">Save Group</x-cms.button></div></form>@endif
</x-cms.modal>
