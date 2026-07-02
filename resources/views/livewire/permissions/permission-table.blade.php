<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search permissions..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        <button type="button" wire:click="openModal"
            class="text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-5 py-2.5">
            Add Permission
        </button>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 p-4 text-sm text-green-800 rounded-lg bg-green-50">
            {{ session('success') }}
        </div>
    @endif

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

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
            <div class="w-full max-w-md bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ $isEdit ? 'Edit Permission' : 'Add Permission' }}
                    </h3>

                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                        ✕
                    </button>
                </div>

                <form wire:submit.prevent="save">
                    <div class="p-4">
                        <label class="block mb-2 text-sm font-medium text-gray-900">
                            Permission Name
                        </label>

                        <input type="text" wire:model="name" placeholder="example: media.view"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2 p-4 border-t">
                        <button type="button" wire:click="closeModal"
                            class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                            Cancel
                        </button>

                        <button type="submit"
                            class="px-5 py-2.5 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
