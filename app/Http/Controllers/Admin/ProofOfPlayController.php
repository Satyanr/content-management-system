<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Services\DevicePlaybackCounter;
use App\Services\PlaybackReport;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProofOfPlayController extends Controller
{
    public function index(Request $request, PlaybackReport $report)
    {
        $filters = $report->filters($request->query());
        $query = $report->query($filters);
        $company = app(WorkspaceService::class)->companyId();
        $summary = $report->summary($query);
        $events = (clone $query)->orderByDesc('id')->paginate(50)->withQueryString();
        $top = (clone $query)->where('status', 'completed')->selectRaw('media_asset_id,MAX(names) as names,COUNT(*) as plays')->groupBy('media_asset_id')->orderByDesc('plays')->limit(5)->get();
        $devices = Device::forCompany($company)->withCount('activeCredentials')->orderBy('name')->get();
        $groups = DeviceGroup::where('company_id', $company)->orderBy('name')->get();
        $playlists = Playlist::forCompany($company)->orderBy('name')->get();
        $media = MediaAsset::where('company_id', $company)->orderBy('title')->get(['id', 'title']);
        $counters = DB::table('device_item_counters')->where('company_id', $company)->when(! empty($filters['device']), fn ($q) => $q->where('device_id', $filters['device']))->orderByDesc('id')->limit(100)->get();

        return view('admin.proof-of-play.index', compact('filters', 'summary', 'events', 'top', 'devices', 'groups', 'playlists', 'media', 'company', 'counters'));
    }

    public function export(Request $request, PlaybackReport $report)
    {
        Gate::authorize('playback.export');
        $filters = $report->filters($request->query());
        $directory = storage_path('app/private/playback-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }$path = tempnam($directory, 'xlsx-');
        try {
            $report->write($path, $filters);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return response()->download($path, 'proof-of-play.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    public function reset(Request $request)
    {
        Gate::authorize('playback.reset');
        $valid = $request->validate(['device_id' => 'required|integer', 'item_id' => 'required|integer', 'confirmed' => 'accepted']);
        app(DevicePlaybackCounter::class)->reset($valid['device_id'], $valid['item_id']);

        return back()->with('success','Epoch counter diperbarui; histori lama tetap tersimpan. Menunggu sinkronisasi Device.');
    }
}
