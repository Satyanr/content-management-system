<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search users..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        <x-cms.button wire:click="openModal">
            Add User
        </x-cms.button>
    </div>

    <x-cms.alert />

    <div class="relative overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <table class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th class="px-6 py-3">Name</th>
                    <th class="px-6 py-3">Email</th>
                    <th class="px-6 py-3">Roles</th>
                    <th class="px-6 py-3">Created</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="bg-white border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $user->name }}
                        </td>
                        <td class="px-6 py-4">
                            {{ $user->email }}
                        </td>
                        <td class="px-6 py-4">
                            @forelse ($user->roles as $role)
                                <span
                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded">
                                    {{ $role->name }}
                                </span>
                            @empty
                                <span class="text-gray-400">No role</span>
                            @endforelse
                        </td>
                        <td class="px-6 py-4">
                            {{ $user->created_at->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button wire:click="edit({{ $user->id }})"
                                class="font-medium text-blue-600 hover:underline">
                                Edit
                            </button>

                            <button wire:click="delete({{ $user->id }})"
                                wire:confirm="Are you sure you want to delete this user?"
                                class="font-medium text-red-600 hover:underline">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-6 text-center text-gray-500">
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit User' : 'Add User'" maxWidth="max-w-lg">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                ✕
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4 space-y-4">
                <x-cms.input label="Name" name="name" wire:model="name" />

                <x-cms.input label="Email" name="email" type="email" wire:model="email" />

                <x-cms.input label="{{ $isEdit ? 'Password (leave blank if unchanged)' : 'Password' }}" name="password"
                    type="password" wire:model="password" />

                <x-cms.select label="Role" name="role" wire:model="role">
                    <option value="">Select Role</option>
                    @foreach ($roles as $item)
                        <option value="{{ $item->name }}">{{ $item->name }}</option>
                    @endforeach
                </x-cms.select>
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
