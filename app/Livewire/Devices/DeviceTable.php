<?php

namespace App\Livewire\Devices;

use App\Models\Device;
use App\Models\DeviceGroup;
use App\Services\ActivityLogService;
use App\Services\DeviceLifecycle;
use App\Services\DeviceManager;
use App\Services\DeviceWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class DeviceTable extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $workspaceId = null;

    #[Locked]
    public ?int $formCompanyId = null;

    #[Locked]
    public ?int $deviceId = null;

    #[Locked]
    public ?int $groupId = null;

    #[Locked]
    public ?int $groupCompanyId = null;

    public array $form = [];

    public array $groupForm = [];

    public array $groupMembers = [];

    public bool $showModal = false;

    public bool $showGroupModal = false;

    public string $view = 'grid';

    public string $search = '';

    public string $platformFilter = '';

    public string $statusFilter = '';

    public string $groupFilter = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->workspaceId = app(DeviceWorkspace::class)->company(false);
    }

    private function company(bool $required = true): ?int
    {
        $id = app(DeviceWorkspace::class)->company($required);
        abort_unless($id === $this->workspaceId, 403, 'Workspace changed. Reload Devices.');

        return $id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlatformFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedGroupFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('devices.create');
        $this->formCompanyId = $this->company();
        $this->deviceId = null;
        $this->form = ['name' => '', 'description' => '', 'location' => '', 'platform' => 'windows', 'screen_width' => null, 'screen_height' => null, 'orientation' => 'landscape', 'groups' => []];
        $this->showModal = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        Gate::authorize('devices.edit');
        $company = $this->company();
        $device = Device::forCompany($company)->with('groups')->findOrFail($id);
        $this->form = $device->only(['name', 'description', 'location', 'platform', 'screen_width', 'screen_height', 'orientation']);
        $this->form['groups'] = $device->groups->pluck('id')->all();
        $this->deviceId = $device->id;
        $this->formCompanyId = $company;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function save(): bool
    {
        Gate::authorize($this->deviceId ? 'devices.edit' : 'devices.create');
        $company = $this->company();
        abort_unless($this->showModal && $this->formCompanyId === $company, 403, 'Reopen the Device form in this workspace.');
        try {
            app(DeviceManager::class)->save($company, $this->deviceId, $this->form, Auth::id());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('form.'.$key, $messages[0]);
            }

            return false;
        }
        $this->closeModal();
        session()->flash('success', 'Device saved. New Devices stay Pending until activation.');

        return true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->formCompanyId = null;
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        Gate::authorize('devices.delete');
        $company = $this->company();
        DB::transaction(function () use ($company, $id) {
            $device = Device::forCompany($company)->lockForUpdate()->findOrFail($id);
            app(DeviceLifecycle::class)->audit($device, 'deleted');
            $device->deleteOrFail();
        });
        $this->resetPage();
        $this->dispatch('device-secret-clear');
        session()->flash('success', 'Device deleted and its credentials invalidated.');
    }

    public function generateCode(int $id): void
    {
        Gate::authorize('devices.activate');
        $this->issue($id, false);
    }

    public function rePair(int $id): void
    {
        Gate::authorize('devices.activate');
        Gate::authorize('devices.revoke');
        $this->issue($id, true);
    }

    private function issue(int $id, bool $repair): void
    {
        $company = $this->company();
        $code = app(DeviceLifecycle::class)->issue($company, $id, $repair);
        $device = Device::forCompany($company)->findOrFail($id);
        $expiry = $device->pairingCodes()->latest('id')->firstOrFail()->expires_at->setTimezone(app(DeviceWorkspace::class)->timezone($company))->format('Y-m-d H:i T');
        // Only this authorized response carries the code. Never place it in a public Livewire property/session/log.
        $this->dispatch('device-pairing-code', code: $code, name: $device->name, expires: $expiry);
    }

    public function cancelCode(int $id): void
    {
        Gate::authorize('devices.activate');
        app(DeviceLifecycle::class)->cancel($this->company(), $id);
        $this->dispatch('device-secret-clear');
        session()->flash('success', 'Pending pairing codes cancelled.');
    }

    public function revoke(int $id): void
    {
        Gate::authorize('devices.revoke');
        app(DeviceLifecycle::class)->revoke($this->company(), $id);
        $this->dispatch('device-secret-clear');
        session()->flash('success', 'All credentials revoked. Re-pair to issue a new credential.');
    }

    public function disable(int $id): void
    {
        Gate::authorize('devices.edit');
        Gate::authorize('devices.revoke');
        app(DeviceLifecycle::class)->disable($this->company(), $id, true);
        $this->dispatch('device-secret-clear');
        session()->flash('success', 'Device disabled; credentials and pairing codes invalidated.');
    }

    public function enable(int $id): void
    {
        Gate::authorize('devices.edit');
        app(DeviceLifecycle::class)->disable($this->company(), $id, false);
        session()->flash('success', 'Device enabled as Pending. Generate a new activation code.');
    }

    public function createGroup(): void
    {
        Gate::authorize('devices.create');
        $this->groupCompanyId = $this->company();
        $this->groupId = null;
        $this->groupForm = ['name' => '', 'description' => ''];
        $this->showGroupModal = true;
        $this->resetValidation();
    }

    public function editGroup(int $id): void
    {
        Gate::authorize('devices.edit');
        $company = $this->company();
        $group = DeviceGroup::forCompany($company)->findOrFail($id);
        $this->groupId = $id;
        $this->groupCompanyId = $company;
        $this->groupForm = $group->only(['name', 'description']);
        $this->showGroupModal = true;
        $this->resetValidation();
    }

    public function closeGroup(): void
    {
        $this->showGroupModal = false;
        $this->groupCompanyId = null;
        $this->resetValidation();
    }

    public function saveGroup(): void
    {
        Gate::authorize($this->groupId ? 'devices.edit' : 'devices.create');
        $company = $this->company();
        abort_unless($this->showGroupModal && $this->groupCompanyId === $company, 403, 'Reopen Group form in this workspace.');
        if ($this->groupId) {
            DeviceGroup::forCompany($company)->findOrFail($this->groupId);
        }
        if (is_string($this->groupForm['name'] ?? null)) {
            $this->groupForm['name'] = trim($this->groupForm['name']);
        }
        $this->validate(['groupForm.name' => ['required', 'string', 'max:150', Rule::unique('device_groups', 'name')->where('company_id', $company)->ignore($this->groupId)], 'groupForm.description' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($company) {
            $group = $this->groupId ? DeviceGroup::forCompany($company)->lockForUpdate()->findOrFail($this->groupId) : new DeviceGroup(['company_id' => $company]);
            $group->fill(array_intersect_key($this->groupForm, array_flip(['name', 'description'])))->save();
            app(ActivityLogService::class)->log(action: $this->groupId ? 'group.updated' : 'group.created', module: 'devices', subject: $group, companyId: $company);
        });
        $this->closeGroup();
        session()->flash('success', 'Device Group saved.');
    }

    public function deleteGroup(int $id): void
    {
        Gate::authorize('devices.delete');
        $company = $this->company();
        DB::transaction(function () use ($company, $id) {
            $group = DeviceGroup::forCompany($company)->lockForUpdate()->findOrFail($id);
            app(ActivityLogService::class)->log(action: 'group.deleted', module: 'devices', subject: $group, companyId: $company);
            $group->deleteOrFail();
        });
        session()->flash('success', 'Group deleted. Devices are retained.');
    }

    public function addToGroup(int $id): void
    {
        Gate::authorize('devices.edit');
        $company = $this->company();
        $value = $this->groupMembers[$id] ?? null;
        validator(['device' => $value], ['device' => ['required', 'integer']])->validate();
        app(DeviceManager::class)->membership($company, (int) $value, $id, true);
        session()->flash('success', 'Device added to Group.');
    }

    public function removeFromGroup(int $groupId, int $deviceId): void
    {
        Gate::authorize('devices.edit');
        app(DeviceManager::class)->membership($this->company(), $deviceId, $groupId, false);
        session()->flash('success', 'Device removed from Group.');
    }

    public function render()
    {
        $company = $this->company(false);
        $timezone = $company ? app(DeviceWorkspace::class)->timezone($company) : 'Asia/Jakarta';
        $groups = DeviceGroup::forCompany($company)->with(['devices' => fn ($q) => $q->forCompany($company)->orderBy('name')])->orderBy('name')->get();
        $query = Device::forCompany($company)->with('groups')->withCount('activeCredentials')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when(in_array($this->platformFilter, ['windows', 'android', 'linux', 'other'], true), fn ($q) => $q->where('platform', $this->platformFilter))
            ->when(in_array($this->statusFilter, ['pending', 'active', 'disabled'], true), fn ($q) => $q->where('status', $this->statusFilter))
            ->when(ctype_digit($this->groupFilter), fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('device_groups.id', $this->groupFilter)->forCompany($company)));
        $now = CarbonImmutable::now('UTC');
        $fresh = $now->subSeconds(max(1, (int) config('devices.heartbeat_freshness_seconds', 180)))->format('Y-m-d H:i:s');
        if (in_array($this->statusFilter, ['online', 'offline', 'never', 'revoked'], true)) {
            $query->where('status', 'active');
            if ($this->statusFilter === 'revoked') {
                $query->whereDoesntHave('activeCredentials');
            } else {
                $query->whereHas('activeCredentials');
                if ($this->statusFilter === 'never') {
                    $query->whereNull('last_seen_at');
                } elseif ($this->statusFilter === 'online') {
                    $query->whereBetween('last_seen_at', [$fresh, $now->format('Y-m-d H:i:s')]);
                } else {
                    $query->whereNotNull('last_seen_at')->where(fn ($q) => $q->where('last_seen_at', '<', $fresh)->orWhere('last_seen_at', '>', $now->format('Y-m-d H:i:s')));
                }
            }
        }
        $sort = in_array($this->sortBy, ['name', 'platform', 'status', 'last_seen_at', 'created_at'], true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $devices = $query->orderBy($sort, $direction)->orderBy('id')->paginate(12);
        $memberChoices = Device::forCompany($company)->orderBy('name')->get(['id', 'name']);

        return view('livewire.devices.device-table', compact('company', 'timezone', 'devices', 'groups', 'memberChoices'));
    }
}
