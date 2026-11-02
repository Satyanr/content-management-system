<?php

namespace App\Livewire\Scheduler;

use App\Models\Playlist;
use App\Models\Schedule;
use App\Services\ScheduleClock;
use App\Services\ScheduleConflicts;
use App\Services\ScheduleManager;
use App\Services\ScheduleResolver;
use App\Services\WorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ScheduleTable extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $workspaceId = null;

    #[Locked]
    public ?int $formCompanyId = null;

    #[Locked]
    public ?int $scheduleId = null;

    #[Locked]
    public array $conflicts = [];

    #[Locked]
    public ?array $detail = null;

    #[Locked]
    public ?string $probeUtc = null;

    public array $form = [];

    public bool $showModal = false;

    public bool $conflictReview = false;

    public string $search = '';

    public string $playlistFilter = '';

    public string $activeFilter = '';

    public string $typeFilter = '';

    public string $view = 'list';

    public string $weekStart = '';

    public string $probeTime = '';

    public int $upcomingLimit = 30;

    public function mount(): void
    {
        Gate::authorize('scheduler.view');
        $this->workspaceId = app(WorkspaceService::class)->companyId();
        $timezone = $this->workspaceId ? app(ScheduleManager::class)->timezone($this->workspaceId) : 'Asia/Jakarta';
        $now = CarbonImmutable::now($timezone);
        $this->weekStart = $now->startOfWeek(1)->toDateString();
        $this->probeTime = $now->format('Y-m-d\TH:i');
    }

    private function company(bool $required = true): ?int
    {
        Gate::authorize('scheduler.view');
        $workspace = app(WorkspaceService::class);
        $id = $workspace->companyId();
        abort_unless($id === $this->workspaceId, 403, 'Workspace changed. Reload Scheduler.');
        if ($id !== null) {
            abort_unless($workspace->company()?->is_active, 403, 'Workspace is unavailable.');
        }
        if ($required) {
            abort_if($id === null, 403, 'Select a company workspace first.');
        }

        return $id;
    }

    public function openCreate(): void
    {
        Gate::authorize('scheduler.create');
        $company = $this->company();
        $timezone = app(ScheduleManager::class)->timezone($company);
        $now = CarbonImmutable::now($timezone);
        $this->scheduleId = null;
        $this->formCompanyId = $company;
        $this->conflicts = [];
        $this->conflictReview = false;
        $this->form = ['name' => '', 'playlist_id' => '', 'schedule_type' => 'once', 'timezone' => $timezone, 'priority' => 0, 'is_active' => true,
            'once_start' => $now->format('Y-m-d\TH:i'), 'once_end' => $now->addHour()->format('Y-m-d\TH:i'),
            'start_date' => $now->toDateString(), 'end_date' => '', 'start_time' => '09:00', 'end_time' => '10:00', 'weekdays' => [1, 2, 3, 4, 5]];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('scheduler.edit');
        $s = Schedule::forCompany($this->company())->findOrFail($id);
        $this->scheduleId = $s->id;
        $this->formCompanyId = $s->company_id;
        $this->conflicts = [];
        $this->conflictReview = false;
        $this->form = $s->only(['name', 'playlist_id', 'schedule_type', 'timezone', 'priority', 'is_active']);
        $this->form += ['once_start' => $s->starts_at_utc?->setTimezone($s->timezone)->format('Y-m-d\TH:i') ?? '',
            'once_end' => $s->ends_at_utc?->setTimezone($s->timezone)->format('Y-m-d\TH:i') ?? '',
            'start_date' => $s->start_date ?? '', 'end_date' => $s->end_date ?? '',
            'start_time' => substr($s->start_time ?? '09:00', 0, 5), 'end_time' => substr($s->end_time ?? '10:00', 0, 5), 'weekdays' => $s->weekdays ?? []];
        $this->resetValidation();
        $this->detail = null;
        $this->showModal = true;
    }

    private function candidate(): ?Schedule
    {
        $company = $this->company();
        abort_unless($this->showModal && $this->formCompanyId === $company, 403, 'Reopen the form in this workspace.');
        if ($this->scheduleId) {
            Schedule::forCompany($company)->findOrFail($this->scheduleId);
        }
        try {
            $data = app(ScheduleManager::class)->prepare($company, $this->form);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

return null;
        }
        $candidate = new Schedule($data);
        $candidate->id = $this->scheduleId;

        return $candidate;
    }

    private function findConflicts(Schedule $candidate): void
    {
        $this->conflicts = array_map(fn ($match) => [
            'name' => $match['schedule']->name, 'playlist' => $match['schedule']->playlist->name,
            'priority' => $match['schedule']->priority, 'candidatePriority' => $candidate->priority,
            'candidateWins' => $match['candidateWins'], 'timezone' => $candidate->timezone,
            'at' => $match['start']->setTimezone($candidate->timezone)->format('Y-m-d H:i P'),
        ], app(ScheduleConflicts::class)->find($candidate));
    }

    public function checkConflicts(): void
    {
        Gate::authorize($this->scheduleId ? 'scheduler.edit' : 'scheduler.create');
        $this->resetValidation();
        if ($candidate = $this->candidate()) {
            $this->findConflicts($candidate);
            $this->conflictReview = true;
        }
    }

    public function save(bool $confirmConflicts = false): bool
    {
        Gate::authorize($this->scheduleId ? 'scheduler.edit' : 'scheduler.create');
        $this->resetValidation();
        $candidate = $this->candidate();
        if (! $candidate) {
            return false;
        }
        $this->findConflicts($candidate);
        if ($this->conflicts && ! $confirmConflicts) {
            $this->conflictReview = true;

            return false;
        }
        $s = app(ScheduleManager::class)->save($this->company(), $this->scheduleId, $this->form, Auth::id());
        $this->findConflicts($s);
        $this->showModal = false;
        $this->formCompanyId = null;
        $this->resetPage();
        session()->flash('success', 'Schedule saved. Conflicts are resolved by priority, then lowest Schedule ID.');

        return true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->formCompanyId = null;
        $this->resetValidation();
    }

    public function updatedForm(): void
    {
        $this->conflictReview = false;
        $this->conflicts = [];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPlaylistFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActiveFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        Gate::authorize('scheduler.delete');
        Schedule::forCompany($this->company())->findOrFail($id)->deleteOrFail();
        $this->detail = null;
        $this->resetPage();
        session()->flash('success', 'Schedule deleted.');
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('scheduler.edit');
        $company = $this->company();
        DB::transaction(function () use ($company, $id) {
            $s = Schedule::forCompany($company)->lockForUpdate()->findOrFail($id);
            if (! $s->is_active && ! Playlist::forCompany($company)->where('is_active', true)->whereKey($s->playlist_id)->exists()) {
                $this->addError('schedule', 'Choose an active Playlist before enabling this Schedule.');

                return;
            }
            $s->update(['is_active' => ! $s->is_active]);
            $this->findConflicts($s->load('playlist'));
            session()->flash('success', 'Schedule status updated.');
        });
    }

    public function shiftWeek(int $direction): void
    {
        $this->company();
        validator(compact('direction'), ['direction' => ['in:-1,1']])->validate();
        $this->validate(['weekStart' => ['required', 'date_format:Y-m-d']]);
        $this->weekStart = CarbonImmutable::parse($this->weekStart, 'UTC')->addWeeks($direction)->startOfWeek(1)->toDateString();
    }

    public function currentWeek(): void
    {
        $this->weekStart = CarbonImmutable::now(app(ScheduleManager::class)->timezone($this->company()))->startOfWeek(1)->toDateString();
    }

    public function showDetails(int $id): void
    {
        $s = Schedule::forCompany($this->company())->with('playlist')->findOrFail($id);
        $this->detail = $s->only(['id', 'name', 'timezone', 'schedule_type', 'priority', 'is_active', 'start_date', 'end_date', 'start_time', 'end_time', 'weekdays']);
        $this->detail += ['playlist' => $s->playlist->name, 'start' => $s->starts_at_utc?->setTimezone($s->timezone)->format('Y-m-d H:i P'), 'end' => $s->ends_at_utc?->setTimezone($s->timezone)->format('Y-m-d H:i P')];
    }

    public function closeDetails(): void
    {
        $this->detail = null;
    }

    public function inspectTime(): void
    {
        $this->probeUtc = null;
        $company = $this->company();
        $this->validate(['probeTime' => ['required', 'date_format:Y-m-d\TH:i']]);
        [$date,$time] = explode('T', $this->probeTime);
        $utc = app(ScheduleClock::class)->wall($date, $time, app(ScheduleManager::class)->timezone($company));
        if (! $utc) {
            $this->addError('probeTime', 'This local time does not exist due to a timezone clock change.');

            return;
        }
        $this->probeUtc = $utc->format('Y-m-d H:i:s');
    }

    public function showMoreUpcoming(): void
    {
        $this->company();
        $this->upcomingLimit += 30;
    }

    public function render()
    {
        $company = $this->company(false);
        $manager = app(ScheduleManager::class);
        $resolver = app(ScheduleResolver::class);
        $clock = app(ScheduleClock::class);
        $timezone = $company ? $manager->timezone($company) : 'Asia/Jakarta';
        $playlists = Playlist::forCompany($company)->orderBy('name')->get(['id', 'name', 'is_active']);
        $query = Schedule::forCompany($company)->with('playlist')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when(ctype_digit($this->playlistFilter), fn ($q) => $q->where('playlist_id', $this->playlistFilter))
            ->when(in_array($this->activeFilter, ['1', '0'], true), fn ($q) => $q->where('is_active', (int) $this->activeFilter))
            ->when(in_array($this->typeFilter, ['once', 'weekly'], true), fn ($q) => $q->where('schedule_type', $this->typeFilter));
        $schedules = $query->orderByDesc('priority')->orderBy('id')->paginate(12);
        validator(['weekStart' => $this->weekStart], ['weekStart' => ['required', 'date_format:Y-m-d']])->validate();
        $week = CarbonImmutable::parse($this->weekStart, $timezone)->startOfWeek(1);
        $days = [];
        $occurrences = [];
        if ($company && $this->view === 'weekly') {
            foreach (Schedule::forCompany($company)->with('playlist')->get() as $s) {
                foreach ($clock->occurrences($s, $week->utc(), $week->addWeek()->utc()) as $o) {
                    $occurrences[] = $o;
                }
            }
        }
        $filteredIds = (clone $query)->pluck('id')->all();
        for ($d = 0; $d < 7; $d++) {
            $day = $week->addDays($d);
            $blocks = [];
            foreach ($occurrences as $o) {
                $start = $o['start']->max($day->utc());
                $end = $o['end']->min($day->addDay()->utc());
                if (! $start->lt($end) || ! in_array($o['schedule']->id, $filteredIds, true)) {
                    continue;
                }
                $overlap = collect($occurrences)->contains(fn ($other) => $o['schedule']->is_active && $o['schedule']->playlist->is_active && $other['schedule']->is_active && $other['schedule']->playlist->is_active && $other['schedule']->id !== $o['schedule']->id && $other['start']->lt($end) && $other['end']->gt($start));
                $blocks[] = $o + ['displayStart' => $start->setTimezone($timezone)->format('H:i'), 'displayEnd' => $end->setTimezone($timezone)->format('H:i'), 'overlap' => $overlap, 'continued' => $o['start']->lt($day->utc())];
            }
            usort($blocks, fn ($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp ?: $b['schedule']->priority <=> $a['schedule']->priority);
            $days[] = ['date' => $day, 'blocks' => $blocks];
        }
        $now = CarbonImmutable::now('UTC');
        $upcoming = $company ? $resolver->timeline($company, $now, $now->setTimezone($timezone)->addDays(7)->utc()) : [];
        $at = $this->probeUtc ? CarbonImmutable::parse($this->probeUtc, 'UTC') : null;
        $playing = $company && $at ? $resolver->resolve($company, $at) : null;

        return view('livewire.scheduler.schedule-table', compact('company','timezone','playlists','schedules','days','upcoming','at','playing'));
    }
}
