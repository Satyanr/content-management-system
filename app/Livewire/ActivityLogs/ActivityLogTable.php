<?php

namespace App\Livewire\ActivityLogs;

use App\Models\ActivityLog;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Services\WorkspaceService;

class ActivityLogTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $module = '';
    public string $action = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingModule(): void
    {
        $this->resetPage();
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $workspaceCompanyId = app(WorkspaceService::class)->companyId();

        $logs = ActivityLog::query()
            ->with(['user', 'company'])
            ->when($workspaceCompanyId !== null, function ($query) use ($workspaceCompanyId) {
                $query->where('company_id', '=', $workspaceCompanyId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', '%' . $this->search . '%')
                        ->orWhere('module', 'like', '%' . $this->search . '%')
                        ->orWhere('action', 'like', '%' . $this->search . '%')
                        ->orWhere('ip_address', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->module, function ($query) {
                $query->where('module', '=', $this->module);
            })
            ->when($this->action, function ($query) {
                $query->where('action', '=', $this->action);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $modules = ActivityLog::query()
            ->select('module')
            ->where('module', '!=', null)
            ->when(!Auth::user()?->hasRole('super-admin'), function ($query) {
                $query->where('company_id', '=', Auth::user()?->company_id);
            })
            ->distinct()
            ->orderBy('module', 'asc')
            ->pluck('module');

        $actions = ActivityLog::query()
            ->select('action')
            ->where('action', '!=', null)
            ->when(!Auth::user()?->hasRole('super-admin'), function ($query) {
                $query->where('company_id', '=', Auth::user()?->company_id);
            })
            ->distinct()
            ->orderBy('action', 'asc')
            ->pluck('action');

        return view('livewire.activity-logs.activity-log-table', [
            'logs' => $logs,
            'modules' => $modules,
            'actions' => $actions,
        ]);
    }
}
