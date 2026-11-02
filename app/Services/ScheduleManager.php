<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleManager
{
    public function timezone(int $companyId): string
    {
        $timezone = app(SettingService::class)->get('timezone', 'Asia/Jakarta', $companyId);

        return in_array($timezone, ScheduleClock::timezones(), true) ? $timezone : 'Asia/Jakarta';
    }

    public function prepare(int $companyId, array $input): array
    {
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : ($input['name'] ?? '');
        $weekly = ($input['schedule_type'] ?? '') === 'weekly';
        $valid = validator($input, [
            'name' => ['required', 'string', 'max:150'], 'playlist_id' => ['required', 'integer'],
            'schedule_type' => ['required', 'string', 'in:once,weekly'], 'timezone' => ['required', 'string', Rule::in(ScheduleClock::timezones())],
            'priority' => ['required', 'integer', 'between:-1000000,1000000'], 'is_active' => ['required', 'boolean'],
            'once_start' => $weekly ? ['exclude'] : ['required', 'date_format:Y-m-d\TH:i'],
            'once_end' => $weekly ? ['exclude'] : ['required', 'date_format:Y-m-d\TH:i'],
            'start_date' => $weekly ? ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before:9999-12-30'] : ['exclude'],
            'end_date' => $weekly ? ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before:9999-12-30'] : ['exclude'],
            'start_time' => $weekly ? ['required', 'date_format:H:i'] : ['exclude'],
            'end_time' => $weekly ? ['required', 'date_format:H:i'] : ['exclude'],
            'weekdays' => $weekly ? ['required', 'array', 'list', 'min:1', 'max:7'] : ['exclude'],
            'weekdays.*' => $weekly ? ['integer', 'between:1,7', 'distinct'] : ['exclude'],
        ])->validate();
        $playlist = Playlist::query()->forCompany($companyId)->where('is_active', true)->find($valid['playlist_id']);
        if (! $playlist) {
            throw ValidationException::withMessages(['playlist_id' => 'Choose an active Playlist in the current company.']);
        }
        $data = array_intersect_key($valid, array_flip(['name', 'playlist_id', 'schedule_type', 'timezone', 'priority', 'is_active']));
        $data += ['company_id' => $companyId, 'starts_at_utc' => null, 'ends_at_utc' => null, 'start_date' => null, 'end_date' => null, 'start_time' => null, 'end_time' => null, 'weekdays' => null];
        if ($weekly) {
            if ($valid['start_time'] === $valid['end_time']) {
                throw ValidationException::withMessages(['end_time' => 'Start and end time must differ. An earlier end time means overnight.']);
            }
            $data['start_date'] = $valid['start_date'];
            $data['end_date'] = ($valid['end_date'] ?? null) ?: null;
            $data['start_time'] = $valid['start_time'].':00';
            $data['end_time'] = $valid['end_time'].':00';
            $days = array_map('intval', $valid['weekdays']);
            sort($days);
            $data['weekdays'] = $days;
        } else {
            $clock = app(ScheduleClock::class);
            foreach (['once_start', 'once_end'] as $field) {
                [$date, $time] = explode('T', $valid[$field]);
                $instant = $clock->wall($date, $time, $valid['timezone'], $field === 'once_end');
                if (! $instant || $date < '1000-01-01' || $date >= '9999-12-30') {
                    throw ValidationException::withMessages([$field => 'This local time does not exist in the selected timezone or is outside the supported calendar range.']);
                }
                $data[$field === 'once_start' ? 'starts_at_utc' : 'ends_at_utc'] = $instant->format('Y-m-d H:i:s');
            }
            if ($data['ends_at_utc'] <= $data['starts_at_utc']) {
                throw ValidationException::withMessages(['once_end' => 'End must be after start.']);
            }
        }

        return $data;
    }

    public function save(int $companyId, ?int $id, array $input, int $creator): Schedule
    {
        return DB::transaction(function () use ($companyId, $id, $input, $creator) {
            $data = $this->prepare($companyId, $input);
            // Lock the selected Playlist so active-state/deletion checks remain consistent through insert.
            Playlist::forCompany($companyId)->where('is_active', true)->lockForUpdate()->findOrFail($data['playlist_id']);
            if ($id) {
                $schedule = Schedule::forCompany($companyId)->lockForUpdate()->findOrFail($id);
                $schedule->update($data);
            } else {
                $schedule = Schedule::create($data + ['created_by' => $creator]);
            }

            return $schedule;
        });
    }
}
