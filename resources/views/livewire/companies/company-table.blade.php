<div>
    <div class="mb-4 flex items-center justify-between gap-4">
        <x-cms.input wire:model.live="search" placeholder="Search companies..." class="w-full md:w-80" />

        @can('companies.create')
            <x-cms.button wire:click="openModal">
                Add Company
            </x-cms.button>
        @endcan
    </div>

    <x-cms.alert />

    <x-cms.table>
        <x-cms.table-header>
            <tr>
                <x-cms.table-cell header>Name</x-cms.table-cell>
                <x-cms.table-cell header>Code</x-cms.table-cell>
                <x-cms.table-cell header>Email</x-cms.table-cell>
                <x-cms.table-cell header>Phone</x-cms.table-cell>
                <x-cms.table-cell header>Status</x-cms.table-cell>
                <x-cms.table-cell header align="right">Action</x-cms.table-cell>
            </tr>
        </x-cms.table-header>

        <tbody>
            @forelse ($companies as $company)
                <x-cms.table-row>
                    <x-cms.table-cell>{{ $company->name }}</x-cms.table-cell>
                    <x-cms.table-cell>{{ $company->code }}</x-cms.table-cell>
                    <x-cms.table-cell>{{ $company->email ?? '-' }}</x-cms.table-cell>
                    <x-cms.table-cell>{{ $company->phone ?? '-' }}</x-cms.table-cell>
                    <x-cms.table-cell>
                        @if ($company->is_active)
                            <x-cms.badge color="green">Active</x-cms.badge>
                        @else
                            <x-cms.badge color="red">Inactive</x-cms.badge>
                        @endif
                    </x-cms.table-cell>
                    <x-cms.table-cell align="right" class="space-x-2">
                        @can('companies.edit')
                            <x-cms.action-link wire:click="edit({{ $company->id }})">
                                Edit
                            </x-cms.action-link>
                        @endcan

                        @can('companies.delete')
                            <x-cms.action-link color="red" wire:click="delete({{ $company->id }})"
                                wire:confirm="Are you sure you want to delete this company?">
                                Delete
                            </x-cms.action-link>
                        @endcan
                    </x-cms.table-cell>
                </x-cms.table-row>
            @empty
                <x-cms.empty-state colspan="6" message="No companies found." />
            @endforelse
        </tbody>
    </x-cms.table>

    <div class="mt-4">
        {{ $companies->links() }}
    </div>

    <x-cms.modal :show="$showModal" :title="$isEdit ? 'Edit Company' : 'Add Company'" maxWidth="max-w-lg">
        <x-slot name="close">
            <button type="button" wire:click="closeModal" class="text-gray-400 hover:text-gray-900">
                ✕
            </button>
        </x-slot>

        <form wire:submit.prevent="save">
            <div class="p-4 space-y-4">
                <x-cms.input label="Company Name" name="name" wire:model="name" />

                <x-cms.input label="Company Code" name="code" wire:model="code" placeholder="Example: COMPANY_A" />

                <x-cms.input label="Email" name="email" type="email" wire:model="email" />

                <x-cms.input label="Phone" name="phone" wire:model="phone" />

                <x-cms.checkbox label="Active" name="is_active" wire:model="is_active" />
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
