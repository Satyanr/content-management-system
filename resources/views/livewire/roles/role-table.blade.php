<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search roles..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        @can('roles.create')
            <x-cms.button wire:click="openModal">
                Add Role
            </x-cms.button>
        @endcan
    </div>

    <x-cms.alert />

    <x-cms.table>
        <x-slot name="head">
            <x-cms.table-header>Role Name</x-cms.table-header>
            <x-cms.table-header>Guard</x-cms.table-header>
            <x-cms.table-header>Created</x-cms.table-header>
            <x-cms.table-header align="right">Action</x-cms.table-header>
        </x-slot>

        @forelse ($roles as $role)
            <x-cms.table-row>
                <x-cms.table-cell class="font-medium text-gray-900">
                    {{ $role->name }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $role->guard_name }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $role->created_at->format('d M Y') }}
                </x-cms.table-cell>

                <x-cms.table-cell align="right" class="space-x-2">
                    @can('roles.edit')
                        <x-cms.action-link wire:click="edit({{ $role->id }})">
                            Edit
                        </x-cms.action-link>
                    @endcan

                    @can('roles.delete')
                        <x-cms.action-link color="red" wire:click="delete({{ $role->id }})"
                            wire:confirm="Are you sure you want to delete this role?">
                            Delete
                        </x-cms.action-link>
                    @endcan
                </x-cms.table-cell>
            </x-cms.table-row>
        @empty
            <x-cms.empty-state colspan="4" message="No roles found." />
        @endforelse
    </x-cms.table>

    <div class="mt-4">
        {{ $roles->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Role' : 'Add Role'" maxWidth="max-w-2xl">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                &times;
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4 space-y-4">
                <x-cms.input label="Role Name" name="name" wire:model="name" />

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-900">
                        Permissions
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-64 overflow-y-auto border rounded-lg p-3">
                        @foreach ($permissions as $permission)
                            <x-cms.checkbox label="{{ $permission->name }}" wire:model="selectedPermissions"
                                value="{{ $permission->name }}" />
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 p-4 border-t">
                <x-cms.button color="secondary" wire:click="closeModal">
                    Cancel
                </x-cms.button>

                <x-cms.button type="submit">
                    Save
                </x-cms.button>
            </div>
        </form>
    </x-cms.modal>
</div>
