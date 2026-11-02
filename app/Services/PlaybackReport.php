<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class PlaybackReport
{
    public function filters(array $input): array
    {
        Gate::authorize('playback.view');
        $company = app(WorkspaceService::class)->companyId();
        $timezone = $company ? app(ScheduleManager::class)->timezone($company) : 'Asia/Jakarta';
        $valid = validator($input + ['from' => now($timezone)->toDateString(), 'to' => now($timezone)->toDateString(), 'timezone' => $timezone], [
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'timezone' => ['required', Rule::in(ScheduleClock::timezones())],
            'device' => 'nullable|integer|min:1', 'group' => 'nullable|integer|min:1', 'playlist' => 'nullable|integer|min:1', 'media' => 'nullable|integer|min:1', 'status' => 'nullable|in:completed,interrupted,failed',
        ])->validate();

        return $valid;
    }

    public function query(array $filters)
    {
        Gate::authorize('playback.view');
        $company = app(WorkspaceService::class)->companyId();
        $query = DB::table('playback_events')->when($company !== null, fn ($q) => $q->where('company_id', $company), fn ($q) => $q->whereRaw('1 = 0'));
        $start = CarbonImmutable::parse($filters['from'], $filters['timezone'])->startOfDay()->utc();
        $end = CarbonImmutable::parse($filters['to'], $filters['timezone'])->addDay()->startOfDay()->utc();
        $query->where('start_time_utc', '>=', $start->format('Y-m-d H:i:s'))->where('start_time_utc', '<', $end->format('Y-m-d H:i:s'));
        foreach (['device' => 'device_id', 'playlist' => 'playlist_id', 'media' => 'media_asset_id', 'status' => 'status'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['group'])) {
            $query->whereIn('device_id', DB::table('device_group_device')->join('device_groups', 'device_groups.id', '=', 'device_group_device.device_group_id')->where('device_groups.company_id', $company)->where('device_group_device.company_id', $company)->where('device_group_id', $filters['group'])->select('device_id'));
        }

        return $query;
    }

    public function summary($query)
    {
        return (clone $query)->selectRaw("COUNT(*) as events, COALESCE(SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END),0) as completed, COALESCE(SUM(actual_played_ms),0) as played_ms, COUNT(DISTINCT device_id) as devices")->first();
    }

    private function row(array $values): Row
    {
        return new Row(array_map(fn ($value) => is_int($value) || is_float($value) ? Cell::fromValue($value) : new StringCell((string) ($value ?? ''), null), $values));
    }

    public function write(string $path, array $filters): void
    {
        Gate::authorize('playback.export');
        Gate::authorize('playback.view');
        $workspace = app(WorkspaceService::class);
        abort_if($workspace->companyId() === null, 403);
        $query = $this->query($filters);
        $highWater = (clone $query)->max('id');
        $query->where('id', '<=', $highWater ?? 0);
        $writer = new Writer;
        $writer->openToFile($path);
        try {
            $writer->getCurrentSheet()->setName('Summary');
            $writer->addRow($this->row(['Periode', $filters['from'].' - '.$filters['to'], 'Timezone', $filters['timezone']]));
            $writer->addRow($this->row(['Company', 'Device', 'Playlist', 'Media', 'Completed plays', 'Partial plays', 'Failed plays', 'Played milliseconds', 'Seconds', 'Minutes', 'Hours']));
            $company = $workspace->company()?->name ?? '';
            $summary = (clone $query)->selectRaw("device_id,playlist_id,media_asset_id,MAX(names) as names,SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,SUM(CASE WHEN status='interrupted' THEN 1 ELSE 0 END) as partial,SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,SUM(actual_played_ms) as played_ms")->groupBy('device_id', 'playlist_id', 'media_asset_id')->orderBy('device_id')->orderBy('playlist_id')->orderBy('media_asset_id');
            $summary->chunk(500, function ($rows) use ($writer, $company) {
                foreach ($rows as $entry) {
                    $names = json_decode($entry->names, true);
                    $ms = (int) $entry->played_ms;
                    $writer->addRow($this->row([$company, $names['device'], $names['playlist'], $names['media'], (int) $entry->completed, (int) $entry->partial, (int) $entry->failed, $ms, $ms / 1000, $ms / 60000, $ms / 3600000]));
                }
            });
            $writer->addNewSheetAndMakeItCurrent()->setName('Detail');
            $writer->addRow($this->row(['Event ID', 'Device', 'Playlist', 'Page', 'Window', 'Media', 'Start UTC', 'End UTC', 'Start local', 'End local', 'Timezone', 'Played milliseconds', 'Expected milliseconds', 'Status', 'Reason', 'Received UTC', 'Counter epoch']));
            (clone $query)->orderBy('id')->chunkById(500, function ($rows) use ($writer, $filters) {
                foreach ($rows as $entry) {
                    $names = json_decode($entry->names, true);
                    $local = fn ($date) => CarbonImmutable::parse($date, 'UTC')->setTimezone($filters['timezone'])->format('Y-m-d H:i:s.v');
                    $writer->addRow($this->row([$entry->event_id, $names['device'], $names['playlist'], $names['page'], $names['zone'], $names['media'], $entry->start_time_utc, $entry->end_time_utc, $local($entry->start_time_utc), $local($entry->end_time_utc), $filters['timezone'], (int) $entry->actual_played_ms, $entry->expected_duration_ms === null ? '' : (int) $entry->expected_duration_ms, $entry->status, $entry->reason, $entry->received_at, $entry->counter_epoch]));
                }
            });
        } finally {
            $writer->close();
        }
    }
}
