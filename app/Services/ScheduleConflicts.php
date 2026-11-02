<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

class ScheduleConflicts
{
    /** First competing occurrence per Schedule, across the stored validity period, not just the preview week. */
    public function find(Schedule $candidate): array
    {
        if (! $candidate->is_active) {
            return [];
        }
        $others = app(ScheduleResolver::class)->eligible($candidate->company_id)->reject(fn ($s) => $s->id === $candidate->id);
        $result = [];
        foreach ($others as $other) {
            $overlap = $this->overlap($candidate, $other);
            if ($overlap) {
                $result[] = ['schedule' => $other, 'start' => $overlap['start'], 'end' => $overlap['end'],
                    'candidateWins' => $candidate->priority > $other->priority || ($candidate->priority === $other->priority && ($candidate->id ?? PHP_INT_MAX) < $other->id)];
            }
        }

        return $result;
    }

    private function bounds(Schedule $s): ?array
    {
        if ($s->schedule_type === 'once') {
            return $s->starts_at_utc && $s->ends_at_utc ? [$s->starts_at_utc, $s->ends_at_utc] : null;
        }
        if (! $s->start_date) {
            return null;
        }

        return [CarbonImmutable::parse($s->start_date, 'UTC')->subDays(2), $s->end_date ? CarbonImmutable::parse($s->end_date, 'UTC')->addDays(3) : CarbonImmutable::parse('9999-12-29', 'UTC')];
    }

    private function sample(Schedule $a, Schedule $b, CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        $clock = app(ScheduleClock::class);
        foreach ($clock->occurrences($a, $from, $to) as $x) {
            foreach ($clock->occurrences($b, $from, $to) as $y) {
                $start = $x['start']->max($y['start'])->max($from);
                $end = $x['end']->min($y['end'])->min($to);
                if ($start->lt($end)) {
                    return compact('start', 'end');
                }
            }
        }

        return null;
    }

    /** Weekly patterns repeat between timezone transitions. Sample full weeks plus transition neighborhoods. */
    public function overlap(Schedule $a, Schedule $b): ?array
    {
        if ($a->company_id !== $b->company_id) {
            return null;
        }
        foreach ([$a, $b] as $s) {
            if ($s->schedule_type === 'weekly' && ! in_array($s->timezone, ScheduleClock::timezones(), true)) {
                return null;
            }
        }
        try {
            $ba = $this->bounds($a);
            $bb = $this->bounds($b);
        } catch (\Throwable) {
            return null;
        }
        if (! $ba || ! $bb) {
            return null;
        }
        $from = $ba[0]->max($bb[0]);
        $to = $ba[1]->min($bb[1]);
        if (! $from->lt($to)) {
            return null;
        }
        if ($a->schedule_type === 'once' && $b->schedule_type === 'once') {
            return ['start' => $from, 'end' => $to];
        }
        if ($match = $this->sample($a, $b, $from, $from->addDays(14)->min($to))) {
            return $match;
        }
        $zones = [];
        foreach ([$a, $b] as $s) {
            if ($s->schedule_type === 'weekly') {
                $zones[$s->timezone] = new DateTimeZone($s->timezone);
            }
        }
        $cuts = [$from->timestamp, $to->timestamp];
        foreach ($zones as $zone) {
            foreach ($zone->getTransitions($from->timestamp, $to->timestamp) ?: [] as $t) {
                $cuts[] = $t['ts'];
            }
        }
        $cuts = array_values(array_unique($cuts));
        sort($cuts);
        $seen = [];
        foreach ($cuts as $i => $cut) {
            $at = CarbonImmutable::createFromTimestampUTC($cut);
            // Three days on either side cover overnight and clock-gap/fold boundaries.
            $lo = $at->subDays(3)->max($from);
            $hi = $at->addDays(3)->min($to);
            $signature = 'boundary';
            foreach ($zones as $zone) {
                foreach ([-259200, 0, 259200] as $offset) {
                    $signature .= (new DateTimeImmutable('@'.($cut + $offset)))->setTimezone($zone)->format('|N H:i:s Z');
                }
                foreach ($zone->getTransitions($cut - 259200, $cut + 259200) ?: [] as $t) {
                    $signature .= '|'.($t['ts'] - $cut).':'.$t['offset'];
                }
            }
            $whole = $lo->eq($at->subDays(3)) && $hi->eq($at->addDays(3)) && $lo->gte($from->addDays(5)) && $hi->lte($to->subDays(6));
            if (! $whole || ! isset($seen[$signature])) {
                if ($match = $this->sample($a, $b, $lo, $hi)) {
                    return $match;
                }
                if ($whole) {
                    $seen[$signature] = true;
                }
            }
            if (! isset($cuts[$i + 1])) {
                continue;
            }
            $lo = $at->addDays(3);
            $hi = CarbonImmutable::createFromTimestampUTC($cuts[$i + 1])->subDays(3);
            if (! $lo->lt($hi)) {
                continue;
            }
            $long = $hi->gte($lo->addDays(8));
            $signature = $long ? 'week' : 'short:'.($hi->timestamp - $lo->timestamp);
            foreach ($zones as $zone) {
                $signature .= $lo->setTimezone($zone)->format($long ? '|Z' : '|N H:i:s Z');
            }
            $whole = $lo->gte($from->addDays(5)) && $hi->lte($to->subDays(6));
            if ($whole && isset($seen[$signature])) {
                continue;
            }
            if ($match = $this->sample($a, $b, $lo, $long ? $lo->addDays(8) : $hi)) {
                return $match;
            }
            if ($whole) {
                $seen[$signature] = true;
            }
        }

        return null;
    }
}
