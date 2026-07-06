<div>
    <x-cms.alert />

    <x-cms.card title="General Settings" subtitle="Basic CMS configuration.">
        <form wire:submit.prevent="save" class="space-y-4">

            <x-cms.input
                label="Application Name"
                name="app_name"
                wire:model="app_name"
            />

            <x-cms.select
                label="Timezone"
                name="timezone"
                wire:model="timezone"
            >
                <option value="Asia/Jakarta">Asia/Jakarta</option>
                <option value="Asia/Makassar">Asia/Makassar</option>
                <option value="Asia/Jayapura">Asia/Jayapura</option>
                <option value="UTC">UTC</option>
            </x-cms.select>

            <x-cms.checkbox
                label="Maintenance Mode"
                name="maintenance_mode"
                wire:model="maintenance_mode"
            />

            <div class="flex justify-end">
                @can('settings.edit')
                    <x-cms.button type="submit">
                        Save Settings
                    </x-cms.button>
                @endcan
            </div>
        </form>
    </x-cms.card>
</div>