<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input type="text" wire:model.live="search" placeholder="Search menus..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">

        @can('menus.create')
            <x-cms.button wire:click="openModal">
                Add Menu
            </x-cms.button>
        @endcan
    </div>

    <x-cms.alert />

    <x-cms.table>
        <x-slot name="head">
                <x-cms.table-header>Title</x-cms.table-header>
                <x-cms.table-header>Parent</x-cms.table-header>
                <x-cms.table-header>Route</x-cms.table-header>
                <x-cms.table-header>Permission</x-cms.table-header>
                <x-cms.table-header>Sort</x-cms.table-header>
                <x-cms.table-header>Status</x-cms.table-header>
                <x-cms.table-header align="right">Action</x-cms.table-header>
        </x-slot>

            @forelse ($menus as $menu)
                <x-cms.table-row>
                    <x-cms.table-cell class="font-medium text-gray-900">
                        {{ $menu->title }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $menu->parent?->title ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $menu->route ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $menu->permission ?? '-' }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        {{ $menu->sort_order }}
                    </x-cms.table-cell>

                    <x-cms.table-cell>
                        <x-cms.badge :color="$menu->is_active ? 'green' : 'red'">
                            {{ $menu->is_active ? 'Active' : 'Inactive' }}
                        </x-cms.badge>
                    </x-cms.table-cell>

                    <x-cms.table-cell align="right" class="space-x-2">
                        @can('menus.edit')
                            <x-cms.action-link wire:click="edit({{ $menu->id }})">
                                Edit
                            </x-cms.action-link>
                        @endcan

                        @can('menus.delete')
                            <x-cms.action-link color="red" wire:click="delete({{ $menu->id }})"
                                wire:confirm="Are you sure you want to delete this menu?">
                                Delete
                            </x-cms.action-link>
                        @endcan
                    </x-cms.table-cell>
                </x-cms.table-row>
            @empty
                <x-cms.empty-state colspan="7" message="No menus found." />
            @endforelse
    </x-cms.table>

    <div class="mt-4">
        {{ $menus->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Menu' : 'Add Menu'" maxWidth="max-w-2xl">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                &times;
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
