<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\User;
use App\Services\DeviceLifecycle;
use App\Services\DeviceManifestService;
use App\Services\DevicePlaybackCounter;
use App\Services\DevicePlaylistAssignment;
use App\Services\PlaybackReport;
use App\Services\PlaylistPlayback;
use App\Services\ScheduleResolver;
use App\Services\WorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    foreach (['media.view', 'playlists.edit', 'dashboard.view', 'playlists.view', 'devices.view', 'devices.assign', 'playback.view', 'playback.export', 'playback.reset'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Always A', 'code' => 'ALWA', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Always B', 'code' => 'ALWB', 'is_active' => true]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['dashboard.view', 'playlists.view', 'devices.view', 'devices.assign']);
    $this->actingAs($this->user);
    $this->playlist = Playlist::create(['company_id' => $this->company->id, 'name' => 'Always On', 'is_active' => true]);
    $this->playlist->pages()->create(['name' => 'Page 1', 'duration_seconds' => 15]);
    $this->devices = collect(range(1, 4))->map(fn ($i) => Device::create(['company_id' => $this->company->id, 'name' => 'Screen '.$i, 'platform' => 'windows']));
});

test('checkpoint 1 assigns A B C but not D and v2 runs without a schedule', function () {
    $command = app(DevicePlaylistAssignment::class)->apply($this->playlist->id, $this->devices->take(3)->pluck('id')->all());
    foreach ($this->devices->take(3) as $device) {
        $device->refresh();
        expect($device->playlist_command_id)->toBe($command);
        $m = app(DeviceManifestService::class)->build($device, 2);
        expect($m['schemaVersion'])->toBe(2)->and($m['currentPlaylistId'])->toBe($this->playlist->id)->and($m['state'])->toBe('playing')->and($m['fallbackPlaylistId'])->toBe($this->playlist->id);
    }
    expect($this->devices->last()->fresh()->primary_playlist_id)->toBeNull();
    expect(DB::table('device_playlist_changes')->count())->toBe(3);
    $this->get(route('admin.playlists.assignment', $this->playlist->id))->assertOk()->assertSee('Tayangkan Playlist');
});
test('checkpoint 1 rejects foreign devices all companies and view only assignment', function () {
    $foreign = Device::create(['company_id' => $this->other->id, 'name' => 'Foreign', 'platform' => 'windows']);
    try {
        app(DevicePlaylistAssignment::class)->apply($this->playlist->id, [$foreign->id]);
        $this->fail();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
    expect($foreign->fresh()->primary_playlist_id)->toBeNull();
    $this->user->revokePermissionTo('devices.assign');
    $this->expectException(AuthorizationException::class);
    app(DevicePlaylistAssignment::class)->apply($this->playlist->id, [$this->devices->first()->id]);
});

test('checkpoint 2 persistent stays after end and temporary returns to previous with scoped targets', function () {
    $at = CarbonImmutable::parse('2026-11-04T00:00:00Z');
    CarbonImmutable::setTestNow($at);
    try {
        $device = $this->devices->first();
        app(DevicePlaylistAssignment::class)->apply($this->playlist->id, [$device->id]);
        $other = Playlist::create(['company_id' => $this->company->id, 'name' => 'Persistent B', 'is_active' => true]);
        $temporary = Playlist::create(['company_id' => $this->company->id, 'name' => 'Temporary C', 'is_active' => true]);
        foreach ([[$other, 'persistent', 1, 2, 0], [$temporary, 'temporary', 3, 4, 10]] as [$playlist,$mode,$start,$end,$priority]) {
            Schedule::create(['company_id' => $this->company->id, 'playlist_id' => $playlist->id, 'name' => $mode, 'schedule_type' => 'once', 'timezone' => 'UTC', 'starts_at_utc' => $at->addHours($start), 'ends_at_utc' => $at->addHours($end), 'playback_mode' => $mode, 'target_type' => 'device', 'target_ids' => [$device->id], 'priority' => $priority, 'is_active' => true]);
        }
        $plan = app(ScheduleResolver::class)->devicePlan($device->fresh(), $at, $at->addHours(8));
        expect(array_column($plan['segments'], 'playlistId'))->toBe([$this->playlist->id, $other->id, $temporary->id, $other->id]);
        expect($plan['fallbackPlaylistId'])->toBe($other->id);
        $unassigned = app(ScheduleResolver::class)->devicePlan($this->devices->last(), $at, $at->addHours(8));
        expect(array_column($unassigned['segments'], 'playlistId'))->toBe([null]);
        CarbonImmutable::setTestNow($at->addDays(15));
        $later = app(ScheduleResolver::class)->devicePlan($device->fresh(), $at->addDays(15), $at->addDays(16));
        expect($later['segments'][0]['playlistId'])->toBe($other->id);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
test('checkpoint 2 existing schedules remain legacy and group tie uses priority then lower ID', function () {
    $at = CarbonImmutable::now('UTC')->startOfHour();
    $group = DeviceGroup::create(['company_id' => $this->company->id, 'name' => 'Group']);
    $group->devices()->attach($this->devices->first()->id, ['company_id' => $this->company->id]);
    foreach ([['all', [], 1], ['group', [$group->id], 5]] as [$type,$ids,$priority]) {
        $rule = Schedule::create(['company_id' => $this->company->id, 'playlist_id' => $this->playlist->id, 'name' => 'Rule', 'schedule_type' => 'once', 'timezone' => 'UTC', 'starts_at_utc' => $at, 'ends_at_utc' => $at->addHours(2), 'target_type' => $type, 'target_ids' => $ids, 'priority' => $priority, 'is_active' => true]);
    }
    expect($rule->fresh()->playback_mode)->toBe('legacy');
    $plan = app(ScheduleResolver::class)->devicePlan($this->devices->first(), $at, $at->addHours(3));
    expect($plan['segments'][0]['schedule']['id'])->toBe($rule->id)->and($plan['segments'][1]['playlistId'])->toBeNull();
});

function alwaysOnEventFixture($test): array
{
    Storage::fake('local');
    $asset = MediaAsset::create(['company_id' => $test->company->id, 'title' => 'Image', 'type' => 'image', 'mime_type' => 'image/png', 'disk' => 'local', 'path' => 'testing/image.png', 'file_name' => 'image.png', 'original_name' => 'image.png', 'size' => 3, 'is_active' => true]);
    Storage::disk('local')->put($asset->path, 'png');
    $page = $test->playlist->pages()->first();
    $zone = $page->zones()->create(['name' => 'Main', 'width' => 800, 'height' => 600, 'is_visible' => true]);
    $item = $zone->items()->create(['media_asset_id' => $asset->id, 'duration_seconds' => 1, 'max_plays' => 1]);
    $device = $test->devices->first();
    app(DevicePlaylistAssignment::class)->apply($test->playlist->id, [$device->id]);
    $lifecycle = app(DeviceLifecycle::class);
    $code = $lifecycle->issue($test->company->id, $device->id);
    $token = $lifecycle->activate(['activation_code' => $code, 'platform' => 'windows', 'screen_width' => 800, 'screen_height' => 600, 'player_version' => '2.0.0'])['credential'];
    $manifest = app(DeviceManifestService::class)->build($device->fresh(), 2);
    $entry = $manifest['playlists'][0]['pages'][0]['zones'][0]['items'][0];
    $event = ['event_id' => (string) Str::uuid(), 'playback_session_id' => (string) Str::uuid(), 'revision' => $manifest['revision'], 'playlist_id' => $test->playlist->id, 'page_id' => $page->id, 'zone_id' => $zone->id, 'playlist_zone_item_id' => $item->id, 'media_asset_id' => $asset->id, 'schedule_id' => null, 'start_time_utc' => CarbonImmutable::now('UTC')->subSecond()->toISOString(), 'end_time_utc' => CarbonImmutable::now('UTC')->toISOString(), 'actual_played_ms' => 1000, 'expected_duration_ms' => 1000, 'status' => 'completed', 'reason' => 'duration_complete', 'counter_epoch' => $entry['counterEpoch']];

    return compact('event', 'token', 'item', 'device', 'manifest');
}
test('checkpoint 3 reset changes epoch only while quota duration transform edits do not', function () {
    $f = alwaysOnEventFixture($this);
    $old = $f['event']['counter_epoch'];
    $f['item']->update(['duration_seconds' => 2, 'max_plays' => 10, 'settings' => ['media_transform' => ['scale' => 2]]]);
    $m = app(DeviceManifestService::class)->build($f['device']->fresh(), 2);
    expect($m['playlists'][0]['pages'][0]['zones'][0]['items'][0]['counterEpoch'])->toBe($old);
    $this->user->givePermissionTo('playback.reset');
    app(DevicePlaybackCounter::class)->reset($f['device']->id, $f['item']->id);
    expect(app(DevicePlaybackCounter::class)->get($f['device'], $f['item']->id)['counter_epoch'])->not->toBe($old);
});
test('checkpoint 4 authenticated events are idempotent and old epochs never refill reset counters', function () {
    $f = alwaysOnEventFixture($this);
    $this->withToken($f['token']);
    $this->postJson('/api/device/playback-events', ['events' => [$f['event']]])->assertOk()->assertJsonPath('acknowledged.0', $f['event']['event_id']);
    $this->postJson('/api/device/playback-events', ['events' => [$f['event']]])->assertOk();
    expect(DB::table('playback_events')->count())->toBe(1);
    expect((int) DB::table('device_item_counters')->value('completed_count'))->toBe(1);
    $this->user->givePermissionTo('playback.reset');
    app(DevicePlaybackCounter::class)->reset($f['device']->id, $f['item']->id);
    $late = $f['event'];
    $late['event_id'] = (string) Str::uuid();
    $late['playback_session_id'] = (string) Str::uuid();
    $f['item']->delete();
    $this->postJson('/api/device/playback-events', ['events' => [$late]])->assertOk();
    expect(DB::table('playback_events')->count())->toBe(2)->and((int) DB::table('device_item_counters')->value('completed_count'))->toBe(0);
});
test('checkpoint 4 rejects forged identities items timestamps and duplicate session or changed payload', function () {
    $f = alwaysOnEventFixture($this);
    $this->withToken($f['token']);
    $this->postJson('/api/device/playback-events', ['device_id' => $this->devices->last()->id, 'events' => [$f['event']]])->assertUnprocessable();
    foreach (['playlist_zone_item_id' => 999999, 'actual_played_ms' => 500, 'end_time_utc' => '2099-01-01T00:00:00Z'] as $key => $value) {
        $invalid = $f['event'];
        $invalid[$key] = $value;
        $this->postJson('/api/device/playback-events', ['events' => [$invalid]])->assertUnprocessable();
    }
    $this->postJson('/api/device/playback-events', ['events' => [$f['event']]])->assertOk();
    $changed = $f['event'];
    $changed['reason'] = 'changed';
    $this->postJson('/api/device/playback-events', ['events' => [$changed]])->assertConflict();
    $changed = $f['event'];
    $changed['event_id'] = (string) Str::uuid();
    $this->postJson('/api/device/playback-events', ['events' => [$changed]])->assertConflict();
    $foreign = $this->devices->last();
    $l = app(DeviceLifecycle::class);
    $code = $l->issue($this->company->id, $foreign->id);
    $otherToken = $l->activate(['activation_code' => $code, 'platform' => 'windows', 'screen_width' => 800, 'screen_height' => 600])['credential'];
    $this->withToken($otherToken)->postJson('/api/device/playback-events', ['events' => [$f['event']]])->assertUnprocessable();
});
test('checkpoint 4 partial and failure retain milliseconds without consuming quota and reported differs from commanded', function () {
    $f = alwaysOnEventFixture($this);
    $this->withToken($f['token']);
    $event = $f['event'];
    $event['status'] = 'interrupted';
    $event['actual_played_ms'] = 400;
    $event['reason'] = 'page_transition';
    $this->postJson('/api/device/playback-events', ['events' => [$event]])->assertOk();
    expect((int) DB::table('device_item_counters')->value('completed_count'))->toBe(0);
    $this->postJson('/api/device/playback-state', ['revision' => $f['manifest']['revision'], 'playlist_id' => $this->playlist->id, 'command_id' => $f['device']->fresh()->playlist_command_id, 'pending_events' => 3])->assertOk();
    expect($f['device']->fresh()->reported_playlist_id)->toBe($this->playlist->id);
    expect(DB::table('device_playlist_changes')->where('source', 'player_report')->value('manifest_revision'))->toBe($f['manifest']['revision']);
    app(DevicePlaylistAssignment::class)->apply($this->playlist->id, [$f['device']->id]);
    expect($f['device']->fresh()->reported_command_id)->not->toBe($f['device']->fresh()->playlist_command_id);
});

test('checkpoint 5 report and XLSX share scope filters precise milliseconds and safe text cells', function () {
    $f = alwaysOnEventFixture($this);
    $this->withToken($f['token'])->postJson('/api/device/playback-events', ['events' => [$f['event']]])->assertOk();
    $row = (array) DB::table('playback_events')->first();
    unset($row['id']);
    $row['company_id'] = $this->other->id;
    $row['device_id'] = $this->devices->last()->id;
    $row['event_id'] = (string) Str::uuid();
    $row['playback_session_id'] = (string) Str::uuid();
    $row['names'] = json_encode(['device' => 'SECRET OTHER', 'playlist' => 'OTHER', 'page' => 'Other', 'zone' => 'Other', 'media' => 'Other']);
    DB::table('playback_events')->insert($row);
    $this->user->givePermissionTo(['playback.view', 'playback.export']);
    $filters = ['from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(), 'timezone' => 'UTC'];
    $this->get(route('admin.proof-of-play.index', $filters))->assertOk()->assertSee('Completed Plays')->assertDontSee('SECRET OTHER')->assertViewHas('summary', fn ($v) => (int) $v->completed === 1 && (int) $v->played_ms === 1000);
    DB::table('playback_events')->where('company_id', $this->company->id)->update(['names' => json_encode(['device' => '=HYPERLINK("https://example.test")', 'playlist' => 'Playlist', 'page' => 'Page', 'zone' => 'Window', 'media' => '+SUM(1,2)'])]);
    $report = app(PlaybackReport::class);
    $path = tempnam(sys_get_temp_dir(), 'proof-xlsx');
    try {
        $report->write($path, $report->filters($filters));
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $workbook = $zip->getFromName('xl/workbook.xml');
        expect($workbook)->toContain('Summary')->toContain('Detail');
        $summary = $zip->getFromName('xl/worksheets/sheet1.xml');
        $detail = $zip->getFromName('xl/worksheets/sheet2.xml');
        expect($detail)->toContain($f['event']['event_id'])->toContain('1000')->not->toContain('SECRET OTHER')->not->toContain('<f>');
        expect($summary)->toContain('SUM(1,2)')->not->toContain('<f>');
        $zip->close();
    } finally {
        @unlink($path);
    }
    $this->user->revokePermissionTo('playback.export');
    $this->get(route('admin.proof-of-play.export', $filters))->assertForbidden();
    $this->post(route('admin.proof-of-play.reset'), ['device_id' => $f['device']->id, 'item_id' => $f['item']->id, 'confirmed' => 1])->assertForbidden();
});
test('checkpoint 5 large XLSX stays bounded by chunking and contains every detail row', function () {
    $f = alwaysOnEventFixture($this);
    $this->withToken($f['token'])->postJson('/api/device/playback-events', ['events' => [$f['event']]])->assertOk();
    $template = (array) DB::table('playback_events')->first();
    unset($template['id']);
    for ($batch = 0; $batch < 20; $batch++) {
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $row = $template;
            $row['event_id'] = (string) Str::uuid();
            $row['playback_session_id'] = (string) Str::uuid();
            $rows[] = $row;
        }DB::table('playback_events')->insert($rows);
    }
    $this->user->givePermissionTo(['playback.view', 'playback.export']);
    $report = app(PlaybackReport::class);
    $path = tempnam(sys_get_temp_dir(), 'proof-large');
    try {
        memory_reset_peak_usage();
        $before = memory_get_usage(true);
        $report->write($path, $report->filters([]));
        $delta = memory_get_peak_usage(true) - $before;
        expect($delta)->toBeLessThan(32 * 1024 * 1024);
        $zip = new ZipArchive;
        $zip->open($path);
        expect(substr_count($zip->getFromName('xl/worksheets/sheet2.xml'), '<row '))->toBe(10002);
        $zip->close();
    } finally {
        @unlink($path);
    }
});

test('checkpoint 6 preview never consumes device quota and v1 requires upgrade for quota or assignment', function () {
    $f = alwaysOnEventFixture($this);
    $this->user->givePermissionTo(['playlists.edit', 'media.view']);
    $before = DB::table('device_item_counters')->get()->toJson();
    $preview = app(PlaylistPlayback::class)->manifest($this->playlist->id);
    expect($preview['pages'][0]['zones'][0]['items'][0])->not->toHaveKey('counterEpoch');
    expect(DB::table('device_item_counters')->get()->toJson())->toBe($before);
    $this->withToken($f['token'])->getJson('/api/device/manifest')->assertStatus(426)->assertJsonPath('error.code', 'upgrade_required');
    $this->withHeader('X-Player-Schema-Version', '2')->getJson('/api/device/manifest')->assertOk()->assertJsonPath('schemaVersion', 2);
});
test('checkpoint 6 All Companies cannot assign or reset and report scope follows selected workspace', function () {
    $f = alwaysOnEventFixture($this);
    $this->user->givePermissionTo(['playback.view', 'playback.reset']);
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    session()->forget(WorkspaceService::SESSION_KEY);
    try {
        app(DevicePlaylistAssignment::class)->apply($this->playlist->id, [$f['device']->id]);
        $this->fail();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
    $this->post(route('admin.proof-of-play.reset'), ['device_id' => $f['device']->id, 'item_id' => $f['item']->id, 'confirmed' => 1])->assertForbidden();
    $this->withSession([WorkspaceService::SESSION_KEY => $this->other->id])->get(route('admin.proof-of-play.index'))->assertOk()->assertDontSee($f['device']->name);
    $this->post(route('admin.proof-of-play.reset'), ['device_id' => $f['device']->id, 'item_id' => $f['item']->id, 'confirmed' => 1])->assertNotFound();
});

test('suspended playback may have a wall clock gap while active monotonic duration remains valid', function () {
    $f = alwaysOnEventFixture($this);
    $event = $f['event'];
    $event['start_time_utc'] = CarbonImmutable::now('UTC')->subMinutes(2)->toISOString();
    $this->withToken($f['token'])->postJson('/api/device/playback-events', ['events' => [$event]])->assertOk();
    expect((int) DB::table('playback_events')->value('actual_played_ms'))->toBe(1000);
});
