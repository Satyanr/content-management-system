<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <input
            type="text"
            wire:model.live="search"
            placeholder="Search menus..."
            class="w-full md:w-80 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
        >

        <button
            type="button"
            wire:click="openModal"
            class="text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-5 py-2.5"
        >
            Add Menu
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

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
            <div class="w-full max-w-2xl bg-white rounded-lg shadow">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ $isEdit ? 'Edit Menu' : 'Add Menu' }}
                    </h3>

                    <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                        ✕
                    </button>
                </div>

                <form wire:submit.prevent="save">
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Parent Menu</label>
                            <select wire:model="parent_id"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Root Menu</option>
                                @foreach ($parentMenus as $parent)
                                    @if ($parent->id !== $menuId)
                                        <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('parent_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Title</label>
                            <input type="text" wire:model="title"
                                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Route Name</label>
                            <input type="text" wire:model="route" placeholder="admin.dashboard"
                                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('route') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Icon</label>
                            <input type="text" wire:model="icon" placeholder="home"
                                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('icon') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Permission</label>
                            <select wire:model="permission"
                                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">No Permission</option>
                                @foreach ($permissions as $item)
                                    <option value="{{ $item->name }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                            @error('permission') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-900">Sort Order</label>
                            <input type="number" wire:model="sort_order"
                                   class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                            @error('sort_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox"
                                       wire:model="is_active"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Active
                            </label>
                        </div>
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