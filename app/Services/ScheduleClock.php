<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

class ScheduleClock
{
    public static function timezones(): array
    {
        static $zones;

        return $zones ??= DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
    }

    /** Resolve wall time without PHP's implicit DST normalization. Folds: earliest start, latest end. */
    public function wall(string $date, string $time, string $timezone, bool $end = false): ?CarbonImmutable
    {
        if (! in_array($timezone, self::timezones(), true)) {
            return null;
        }
        $local = $date.' '.(strlen($time) === 5 ? $time.':00' : $time);
        try {
            $nominal = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $local, 'UTC');
            if (! $nominal || $nominal->format('Y-m-d H:i:s') !== $local) {
                return null;
            }
            $zone = new DateTimeZone($timezone);
            $transitions = $zone->getTransitions($nominal->timestamp - 259200, $nominal->timestamp + 259200);
            $candidates = [];
            foreach (array_unique(array_column($transitions ?: [], 'offset')) as $offset) {
                $instant = $nominal->subSeconds($offset);
                if ($instant->setTimezone($zone)->format('Y-m-d H:i:s') === $local) {
                    $candidates[$instant->timestamp] = $instant;
                }
            }
            ksort($candidates);

            return $candidates ? ($end ? end($candidates) : reset($candidates)) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function interval(Schedule $schedule, string $date): ?array
    {
        if ($schedule->schedule_type !== 'weekly' || ! $schedule->start_date || ! $schedule->start_time || ! $schedule->end_time) {
            return null;
        }
        if ($date < $schedule->start_date || ($schedule->end_date && $date > $schedule->end_date)) {
            return null;
        }
        if ($schedule->start_time === $schedule->end_time) {
            return null;
        }
        $day = CarbonImmutable::parse($date, 'UTC');
        if (! in_array($day->isoWeekday(), array_map('intval', $schedule->weekdays ?? []), true)) {
            return null;
        }
        $endDate = $schedule->end_time < $schedule->start_time ? $day->addDay()->toDateString() : $date;
        $start = $this->wall($date, $schedule->start_time, $schedule->timezone);
        $end = $this->wall($endDate, $schedule->end_time, $schedule->timezone, true);

        return $start && $end && $end->gt($start) ? ['schedule' => $schedule, 'start' => $start, 'end' => $end] : null;
    }

    public function occurrences(Schedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $to->gt($from)) {
            return [];
        }
        try {
            if ($schedule->schedule_type === 'once') {
                $start = $schedule->starts_at_utc;
                $end = $schedule->ends_at_utc;

                return $start && $end && $end->gt($start) && $start->lt($to) && $end->gt($from) ? [['schedule' => $schedule, 'start' => $start, 'end' => $end]] : [];
            }
            if ($schedule->schedule_type !== 'weekly' || ! in_array($schedule->timezone, self::timezones(), true)) {
                return [];
            }
            $date = CarbonImmutable::parse($from->setTimezone($schedule->timezone)->toDateString(), 'UTC')->subDays(2);
            $last = CarbonImmutable::parse($to->setTimezone($schedule->timezone)->toDateString(), 'UTC');
            if ($schedule->start_date) {
                $date = $date->max(CarbonImmutable::parse($schedule->start_date, 'UTC'));
            }
            if ($schedule->end_date) {
                $last = $last->min(CarbonImmutable::parse($schedule->end_date, 'UTC'));
            }
            $result = [];
            for (; $date->lte($last); $date = $date->addDay()) {
                $interval = $this->interval($schedule, $date->toDateString());
                if ($interval && $interval['start']->lt($to) && $interval['end']->gt($from)) {
                    $result[] = $interval;
                }
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    public function activeAt(Schedule $schedule, CarbonImmutable $at): bool
    {
        foreach ($this->occurrences($schedule, $at, $at->addSecond()) as $interval) {
            if ($interval['start']->lte($at) && $interval['end']->gt($at)) {
                return true;
            }
        }

        return false;
    }
}
