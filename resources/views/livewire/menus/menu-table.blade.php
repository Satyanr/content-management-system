<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input
            type="text"
            wire:model.live="search"
            placeholder="Search menus..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
        >

        <x-cms.button wire:click="openModal">
            Add Menu
        </x-cms.button>
    </div>

    <x-cms.alert />

    <div class="relative overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
        <table class="w-full text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <tr>
                    <th class="px-6 py-3">Title</th>
                    <th class="px-6 py-3">Parent</th>
                    <th class="px-6 py-3">Route</th>
                    <th class="px-6 py-3">Permission</th>
                    <th class="px-6 py-3">Sort</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($menus as $menu)
                    <tr class="bg-white border-b hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $menu->title }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $menu->parent?->title ?? '-' }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $menu->route ?? '-' }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $menu->permission ?? '-' }}
                        </td>

                        <td class="px-6 py-4">
                            {{ $menu->sort_order }}
                        </td>

                        <td class="px-6 py-4">
                            @if ($menu->is_active)
                                <span class="px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded">
                                    Active
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded">
                                    Inactive
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-right space-x-2">
                            <button wire:click="edit({{ $menu->id }})"
                                    class="font-medium text-blue-600 hover:underline">
                                Edit
                            </button>

                            <button wire:click="delete({{ $menu->id }})"
                                    wire:confirm="Are you sure you want to delete this menu?"
                                    class="font-medium text-red-600 hover:underline">
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-6 text-center text-gray-500">
                            No menus found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $menus->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Menu' : 'Add Menu'" maxWidth="max-w-2xl">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                ✕
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-cms.select label="Parent Menu" name="parent_id" wire:model="parent_id">
                    <option value="">Root Menu</option>
                    @foreach ($parentMenus as $parent)
                        @if ($parent->id !== $menuId)
                            <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                        @endif
                    @endforeach
                </x-cms.select>

                <x-cms.input label="Title" name="title" wire:model="title" />

                <x-cms.input label="Route Name" name="route" wire:model="route" placeholder="admin.dashboard" />

                <x-cms.input label="Icon" name="icon" wire:model="icon" placeholder="home" />

                <x-cms.select label="Permission" name="permission" wire:model="permission">
                    <option value="">No Permission</option>
                    @foreach ($permissions as $item)
                        <option value="{{ $item->name }}">{{ $item->name }}</option>
                    @endforeach
                </x-cms.select>

                <x-cms.input label="Sort Order" name="sort_order" type="number" wire:model="sort_order" />

                <div class="md:col-span-2">
                    <x-cms.checkbox label="Active" wire:model="is_active" />
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
