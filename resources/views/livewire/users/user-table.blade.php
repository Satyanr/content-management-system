<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search users..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        @can('users.create')
            <x-cms.button wire:click="openModal">
                Add User
            </x-cms.button>
        @endcan
    </div>

    <x-cms.alert />

    <x-cms.table>
        <x-slot name="head">
            <x-cms.table-header>Name</x-cms.table-header>
            <x-cms.table-header>Email</x-cms.table-header>
            <x-cms.table-header>Company</x-cms.table-header>
            <x-cms.table-header>Roles</x-cms.table-header>
            <x-cms.table-header>Created</x-cms.table-header>
            <x-cms.table-header align="right">Action</x-cms.table-header>
        </x-slot>

        @forelse ($users as $user)
            <x-cms.table-row>
                <x-cms.table-cell class="font-medium text-gray-900">
                    {{ $user->name }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $user->email }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $user->company?->name ?? '-' }}
                </x-cms.table-cell>

                <x-cms.table-cell>
                    @forelse ($user->roles as $role)
                        <x-cms.badge>
                            {{ $role->name }}
                        </x-cms.badge>
                    @empty
                        <span class="text-gray-400">No role</span>
                    @endforelse
                </x-cms.table-cell>

                <x-cms.table-cell>
                    {{ $user->created_at->format('d M Y') }}
                </x-cms.table-cell>

                <x-cms.table-cell align="right" class="space-x-2">
                    @can('users.edit')
                        <x-cms.action-link wire:click="edit({{ $user->id }})">
                            Edit
                        </x-cms.action-link>
                    @endcan

                    @can('users.delete')
                        <x-cms.action-link color="red" wire:click="delete({{ $user->id }})"
                            wire:confirm="Are you sure you want to delete this user?">
                            Delete
                        </x-cms.action-link>
                    @endcan
                </x-cms.table-cell>
            </x-cms.table-row>
        @empty
            <x-cms.empty-state colspan="5" message="No users found." />
        @endforelse
    </x-cms.table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit User' : 'Add User'" maxWidth="max-w-lg">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                &times;
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4 space-y-4">
                <x-cms.input label="Name" name="name" wire:model="name" />

                <x-cms.input label="Email" name="email" type="email" wire:model="email" />

                <x-cms.select label="Company" name="company_id" wire:model="company_id">
                    <option value="">Select Company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </x-cms.select>

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
