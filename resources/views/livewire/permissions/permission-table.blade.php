<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search permissions..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        <x-cms.button wire:click="openModal">
            Add Permission
        </x-cms.button>
    </div>

    <x-cms.alert />

    <div class="relative overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <table class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th class="px-6 py-3">Permission Name</th>
                    <th class="px-6 py-3">Guard</th>
                    <th class="px-6 py-3">Created</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($permissions as $permission)
                    <tr class="bg-white border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $permission->name }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $permission->guard_name }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $permission->created_at->format('d M Y') }}
                        </td>

                        <td class="px-6 py-4 text-right space-x-2">
                            <button wire:click="edit({{ $permission->id }})"
                                class="font-medium text-blue-600 hover:underline">
                                Edit
                            </button>

                            <button wire:click="delete({{ $permission->id }})"
                                wire:confirm="Are you sure you want to delete this permission?"
                                class="font-medium text-red-600 hover:underline">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-6 text-center text-gray-500">
                            No permissions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $permissions->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Permission' : 'Add Permission'" maxWidth="max-w-md">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                ✕
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4">
                <x-cms.input label="Permission Name" name="name" wire:model="name" placeholder="example: media.view" />
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
