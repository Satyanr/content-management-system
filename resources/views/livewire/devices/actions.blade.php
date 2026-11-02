<div class="flex flex-wrap gap-2 text-xs">
    <a class="text-blue-700 hover:underline" href="{{ route('admin.devices.show',$device->public_id) }}">Details</a>
    @can('devices.edit')<button type="button" wire:click="edit({{ $device->id }})" class="text-blue-700">Edit</button>@endcan
    @can('devices.activate')
        @if($device->status==='pending')<button type="button" wire:click="generateCode({{ $device->id }})" wire:confirm="Generate a new one-time code? Previous pending codes will be cancelled." wire:loading.attr="disabled" class="text-blue-700">Generate Activation Code</button>@endif
        <button type="button" wire:click="cancelCode({{ $device->id }})" wire:confirm="Cancel all pending activation codes for this Device?" wire:loading.attr="disabled" class="text-gray-600">Cancel pairing code</button>
    @endcan
    @can('devices.revoke')
        <button type="button" wire:click="revoke({{ $device->id }})" wire:confirm="Revoke every credential and pending code for this Device? Its connection will be denied immediately." wire:loading.attr="disabled" class="text-red-600">Revoke credentials</button>
        @can('devices.activate')@if($device->status==='active')<button type="button" wire:click="rePair({{ $device->id }})" wire:confirm="Re-pair this Device? All old credentials will be revoked and a new code will be shown once." wire:loading.attr="disabled" class="text-blue-700">Re-pair</button>@endif@endcan
    @endcan
    @can('devices.edit')
        @if($device->status==='disabled')<button type="button" wire:click="enable({{ $device->id }})" wire:confirm="Enable as Pending? A fresh activation is required." class="text-blue-700">Enable</button>
        @else @can('devices.revoke')<button type="button" wire:click="disable({{ $device->id }})" wire:confirm="Disable this Device and revoke all access?" wire:loading.attr="disabled" class="text-red-600">Disable</button>@endcan @endif
    @endcan
    @can('devices.delete')<button type="button" wire:click="delete({{ $device->id }})" wire:confirm="Delete this Device, its pairing history and all credentials? Groups will be retained." wire:loading.attr="disabled" class="text-red-600">Delete</button>@endcan
</div>
