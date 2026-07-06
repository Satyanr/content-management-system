<?php

namespace App\Livewire\LoginHistories;

use App\Models\LoginHistory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\WorkspaceService;

class LoginHistoryTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $workspaceCompanyId = app(WorkspaceService::class)->companyId();
        $sessionLifetime = (int) config('session.lifetime', 120);
        $activeLimit = now()->subMinutes($sessionLifetime);

        $histories = LoginHistory::query()
            ->with(['user', 'company'])
            ->when($workspaceCompanyId !== null, function ($query) use ($workspaceCompanyId) {
                $query->where('company_id', '=', $workspaceCompanyId);
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('ip_address', 'like', '%' . $this->search . '%')
                        ->orWhere('user_agent', 'like', '%' . $this->search . '%')
                        ->orWhere('session_id', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function ($userQuery) {
                            $userQuery->where('name', 'like', '%' . $this->search . '%')->orWhere('email', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->status === 'online', function ($query) use ($activeLimit) {
                $query->where('logout_at', '=', null)->where('last_activity_at', '>=', $activeLimit);
            })
            ->when($this->status === 'inactive', function ($query) use ($activeLimit) {
                $query->where('logout_at', '=', null)->where(function ($q) use ($activeLimit) {
                    $q->where('last_activity_at', '<', $activeLimit)->orWhere('last_activity_at', '=', null);
                });
            })
            ->when($this->status === 'logged_out', function ($query) {
                $query->where('logout_at', '!=', null);
            })
            ->orderBy('login_at', 'desc')
            ->paginate(15);

        return view('livewire.login-histories.login-history-table', [
            'histories' => $histories,
        ]);
    }
}
