<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search roles..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        <x-cms.button wire:click="openModal">
            Add Role
        </x-cms.button>
    </div>

    <x-cms.alert />

    <div class="relative overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <table class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th class="px-6 py-3">Role Name</th>
                    <th class="px-6 py-3">Guard</th>
                    <th class="px-6 py-3">Created</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($roles as $role)
                    <tr class="bg-white border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $role->name }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $role->guard_name }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $role->created_at->format('d M Y') }}
                        </td>

                        <td class="px-6 py-4 text-right space-x-2">
                            <button wire:click="edit({{ $role->id }})"
                                class="font-medium text-blue-600 hover:underline">
                                Edit
                            </button>

                            <button wire:click="delete({{ $role->id }})"
                                wire:confirm="Are you sure you want to delete this role?"
                                class="font-medium text-red-600 hover:underline">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-6 text-center text-gray-500">
                            No roles found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $roles->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Role' : 'Add Role'" maxWidth="max-w-2xl">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                ✕
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
