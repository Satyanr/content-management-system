<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Playlist;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleResolver
{
    /** Per-device v2 plan; shares eligible()/ScheduleClock with legacy resolution. */
    public function devicePlan(Device $device, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $clock = app(ScheduleClock::class);
        $groups = $device->groups()->where('device_groups.company_id', $device->company_id)->pluck('device_groups.id')->all();
        $rules = $this->eligible($device->company_id)->filter(fn ($s) => match ($s->target_type) {
            'device' => in_array($device->id, array_map('intval', $s->target_ids ?? []), true),
            'group' => (bool) array_intersect($groups, $s->target_ids ?? []), default => true,
        });
        $cutoff = $device->playlist_command_at ? CarbonImmutable::instance($device->playlist_command_at) : null;
        $base = Playlist::forCompany($device->company_id)->where('is_active', true)->find($device->primary_playlist_id);
        $baseChoice = ['playlistId' => $base?->id, 'commandId' => $device->playlist_command_id, 'schedule' => null, 'source' => 'primary'];
        $triggers = [];
        $overrides = [];
        $points = [$from->timestamp => $from, $until->timestamp => $until];
        foreach ($rules as $rule) {
            if ($rule->playback_mode === 'persistent') {
                if ($rule->schedule_type === 'once') {
                    $at = $rule->starts_at_utc;
                    $intervals = $at ? [['start' => $at]] : [];
                } else {
                    $anchor = $from;
                    if ($rule->end_date && $rule->end_date < $from->setTimezone($rule->timezone)->toDateString()) {
                        $anchor = CarbonImmutable::parse($rule->end_date.' 23:59:59', $rule->timezone)->utc();
                    }
                    $intervals = $clock->occurrences($rule, $anchor->subDays(8), $until);
                }
                foreach ($intervals as $interval) {
                    $at = $interval['start'];
                    if ($at->gte($until) || ($cutoff && $at->lte($cutoff))) {
                        continue;
                    }
                    $triggers[] = ['at' => $at, 'rule' => $rule];
                    if ($at->gt($from)) {
                        $points[$at->timestamp] = $at;
                    }
                }
            } else {
                foreach ($clock->occurrences($rule, $from, $until) as $interval) {
                    if ($cutoff && $interval['start']->lte($cutoff)) {
                        continue;
                    }
                    $overrides[] = $interval + ['rule' => $rule];
                    foreach (['start', 'end'] as $edge) {
                        $at = $interval[$edge];
                        if ($at->gt($from) && $at->lt($until)) {
                            $points[$at->timestamp] = $at;
                        }
                    }
                }
            }
        }
        // Later triggers replace earlier choices. At an identical instant higher priority then lower ID wins.
        usort($triggers, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp ?: $a['rule']->priority <=> $b['rule']->priority ?: $b['rule']->id <=> $a['rule']->id);
        ksort($points);
        $points = array_values($points);
        $segments = [];
        $cursor = 0;
        $baseRule = null;
        $choice = fn ($rule, $at, $source) => ['playlistId' => $rule->playlist_id, 'commandId' => hash('sha256', 'schedule:'.$rule->id.':'.$at->timestamp), 'schedule' => ['id' => $rule->id, 'playlistId' => $rule->playlist_id, 'name' => $rule->name, 'priority' => $rule->priority, 'type' => $rule->schedule_type, 'timezone' => $rule->timezone, 'startsAtUtc' => $rule->starts_at_utc?->toIso8601String(), 'endsAtUtc' => $rule->ends_at_utc?->toIso8601String(), 'startDate' => $rule->start_date, 'endDate' => $rule->end_date, 'startTime' => $rule->start_time, 'endTime' => $rule->end_time, 'weekdays' => $rule->weekdays], 'source' => $source];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $at = $points[$i];
            while (isset($triggers[$cursor]) && $triggers[$cursor]['at']->lte($at)) {
                $trigger = $triggers[$cursor++];
                $baseRule = $trigger['rule'];
                $baseChoice = $choice($baseRule, $trigger['at'], 'persistent');
            }
            $active = collect($overrides)->filter(fn ($e) => $e['start']->lte($at) && $e['end']->gt($at))->sort(fn ($a, $b) => $b['rule']->priority <=> $a['rule']->priority ?: $a['rule']->id <=> $b['rule']->id)->first();
            $selected = $baseChoice;
            if ($active && (! $baseRule || $active['rule']->priority > $baseRule->priority || ($active['rule']->priority === $baseRule->priority && $active['rule']->id < $baseRule->id))) {
                $selected = $choice($active['rule'], $active['start'], $active['rule']->playback_mode);
            }
            $selected += ['state' => $selected['playlistId'] ? 'playing' : 'idle', 'startUtc' => $at->toIso8601String(), 'endUtc' => $points[$i + 1]->toIso8601String()];
            $last = array_key_last($segments);
            if ($last !== null && $segments[$last]['commandId'] === $selected['commandId'] && $segments[$last]['playlistId'] === $selected['playlistId']) {
                $segments[$last]['endUtc'] = $selected['endUtc'];
            } else {
                $segments[] = $selected;
            }
        }

        return ['segments' => $segments, 'fallbackPlaylistId' => $baseChoice['playlistId'], 'fallbackSelection' => $baseChoice, 'rules' => $rules->map(fn ($s) => $s->only(['id', 'playlist_id', 'priority', 'playback_mode', 'target_type', 'target_ids', 'updated_at']))->values()->all()];
    }

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
