<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleResolver
{
    public function eligible(int $companyId): Collection
    {
        return Schedule::query()->forCompany($companyId)->where('is_active', true)
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->whereHas('playlist', fn ($q) => $q->where('company_id', $companyId)->where('is_active', true))
            ->with('playlist')->orderByDesc('priority')->orderBy('id')->get();
    }

    public function choose(Collection $schedules, CarbonImmutable $at): ?Schedule
    {
        return $schedules->sort(fn ($a, $b) => $b->priority <=> $a->priority ?: ($a->id ?? PHP_INT_MAX) <=> ($b->id ?? PHP_INT_MAX))
            ->first(fn ($schedule) => app(ScheduleClock::class)->activeAt($schedule, $at->utc()));
    }

    public function resolve(int $companyId, CarbonImmutable $atUtc): ?Schedule
    {
        return $this->choose($this->eligible($companyId), $atUtc->utc());
    }

    /** Winner segments, including gaps, from the same choose/activeAt logic as resolve(). */
    public function timeline(int $companyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $to->gt($from)) {
            return [];
        }
        $schedules = $this->eligible($companyId);
        $clock = app(ScheduleClock::class);
        $points = [$from->timestamp => $from, $to->timestamp => $to];
        foreach ($schedules as $schedule) {
            foreach ($clock->occurrences($schedule, $from, $to) as $interval) {
                $start = $interval['start']->max($from);
                $end = $interval['end']->min($to);
                $points[$start->timestamp] = $start;
                $points[$end->timestamp] = $end;
            }
        }
        ksort($points);
        $points = array_values($points);
        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $winner = $this->choose($schedules, $points[$i]);
            $last = array_key_last($segments);
            if ($last !== null && ($segments[$last]['schedule']?->id) === ($winner?->id)) {
                $segments[$last]['end'] = $points[$i + 1];
            } else {
                $segments[] = ['start' => $points[$i], 'end' => $points[$i + 1], 'schedule' => $winner];
            }
        }

        return $segments;
    }
}
