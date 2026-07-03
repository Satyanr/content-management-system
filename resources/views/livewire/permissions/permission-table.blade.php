<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search permissions..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        @can('permissions.create')
            <x-cms.button wire:click="openModal">
                Add Permission
            </x-cms.button>
        @endcan
    </div>

    <x-cms.alert />

    <x-cms.table>
        <x-cms.table-header>
            <x-cms.table-header-row>
                <x-cms.table-cell header>Permission Name</x-cms.table-cell>
                <x-cms.table-cell header>Guard</x-cms.table-cell>
                <x-cms.table-cell header>Created</x-cms.table-cell>
                <x-cms.table-cell header align="right">Action</x-cms.table-cell>
            </x-cms.table-header-row>
        </x-cms.table-header>

        <tbody>
            @forelse ($permissions as $permission)
                <x-cms.table-row>
                    <x-cms.table-cell class="font-medium text-gray-900">
                        {{ $permission->name }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $permission->guard_name }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $permission->created_at->format('d M Y') }}
                    </x-cms.table-cell>

                    <x-cms.table-cell align="right" class="space-x-2">
                        @can('permissions.edit')
                            <x-cms.action-link wire:click="edit({{ $permission->id }})">
                                Edit
                            </x-cms.action-link>
                        @endcan

                        @can('permissions.delete')
                            <x-cms.action-link color="red" wire:click="delete({{ $permission->id }})"
                                wire:confirm="Are you sure you want to delete this permission?">
                                Delete
                            </x-cms.action-link>
                        @endcan
                    </x-cms.table-cell>
                </x-cms.table-row>
            @empty
                <x-cms.empty-state colspan="4" message="No permissions found." />
            @endforelse
        </tbody>
    </x-cms.table>

    <div class="mt-4">
        {{ $permissions->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Permission' : 'Add Permission'" maxWidth="max-w-md">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                &times;
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4">
                <x-cms.input label="Permission Name" name="name" wire:model="name"
                    placeholder="example: media.view" />
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
