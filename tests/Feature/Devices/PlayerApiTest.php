<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\User;
use App\Services\DeviceLifecycle;
use App\Services\DeviceManifestService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02T03:15:00Z'));
    Storage::fake('local');
    $this->company = Company::create(['name' => 'Player A', 'code' => 'PAPI-A', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Player B', 'code' => 'PAPI-B', 'is_active' => true]);
    $this->device = Device::create(['company_id' => $this->company->id, 'name' => 'Screen', 'platform' => 'windows']);
    $lifecycle = app(DeviceLifecycle::class);
    $code = $lifecycle->issue($this->company->id, $this->device->id);
    $this->token = $lifecycle->activate(['activation_code' => $code, 'platform' => 'windows', 'player_version' => 'test', 'screen_width' => 1920, 'screen_height' => 1080])['credential'];
    $this->device->refresh();
    $this->playlist = Playlist::create(['company_id' => $this->company->id, 'name' => 'Lobby', 'canvas_width' => 1920, 'canvas_height' => 1080, 'background_color' => '#112233', 'is_active' => true]);
    $this->page = $this->playlist->pages()->create(['name' => 'Page One', 'duration_seconds' => 15, 'sort_order' => 2, 'background_color' => '']);
    $this->zone = $this->page->zones()->create(['name' => 'Video Window', 'x' => 10, 'y' => 20, 'width' => 600, 'height' => 300, 'z_index' => 3, 'fit_mode' => 'stretch', 'is_visible' => true]);
    $this->asset = playerAsset($this->company->id, 'video', '0123456789');
    $this->item = $this->zone->items()->create(['media_asset_id' => $this->asset->id, 'sort_order' => 3]);
    $this->schedule = playerSchedule($this->company->id, $this->playlist->id);
    $this->withToken($this->token);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function playerAsset(int $company, string $type = 'image', string $bytes = 'image'): MediaAsset
{
    $path = 'companies/'.$company.'/'.bin2hex(random_bytes(8)).'.dat';
    Storage::disk('local')->put($path, $bytes);

    return MediaAsset::create(['company_id' => $company, 'title' => 'Asset', 'file_name' => $type.'.dat', 'original_name' => $type.'.dat', 'path' => $path,
        'disk' => 'local', 'type' => $type, 'mime_type' => $type === 'video' ? 'video/mp4' : 'image/png', 'size' => strlen($bytes), 'is_active' => true]);
}

function playerSchedule(int $company, int $playlist, array $overrides = []): Schedule
{
    return Schedule::create(array_replace(['company_id' => $company, 'playlist_id' => $playlist, 'name' => 'Lobby Schedule', 'schedule_type' => 'once',
        'timezone' => 'Asia/Jakarta', 'starts_at_utc' => '2026-11-02T03:00:00Z', 'ends_at_utc' => '2026-11-02T05:00:00Z', 'priority' => 0, 'is_active' => true], $overrides));
}

test('authenticated manifest provides deduplicated content and no internal secrets', function () {
    $this->zone->items()->create(['media_asset_id' => $this->asset->id]);
    $response = $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('schemaVersion', 1)->assertJsonPath('state', 'scheduled')
        ->assertJsonPath('device.publicId', $this->device->public_id)->assertJsonPath('currentSchedule.id', $this->schedule->id)
        ->assertJsonPath('output.width', 1920)->assertJsonCount(1, 'media')->assertJsonPath('media.0.size', 10)
        ->assertJsonPath('media.0.checksum.value', hash('sha256', '0123456789'));
    expect($response->json('media.0.downloadUrl'))->toBe('/api/device/media/'.$this->asset->id);
    foreach ([$this->token, $this->asset->path, 'token_hash', 'token_identifier', 'storage/app', '/admin/'] as $secret) {
        expect($response->getContent())->not->toContain($secret);
    }
    expect($response->headers->get('X-Request-ID'))->not->toBeEmpty();
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
});

test('admin session cannot authenticate manifest without a Device token', function () {
    $this->actingAs(User::factory()->create(['company_id' => $this->other->id]));
    $this->withHeader('Authorization', '')->getJson('/api/device/manifest')->assertUnauthorized()->assertJsonPath('error.code', 'invalid_token');
    $this->withToken($this->token)->getJson('/api/device/manifest')->assertOk()->assertJsonPath('device.publicId', $this->device->public_id);
});

test('invalid and revoked credentials fail before conditional responses', function () {
    $etag = $this->getJson('/api/device/manifest')->headers->get('ETag');
    $this->withToken('invalid')->withHeader('If-None-Match', $etag)->getJson('/api/device/manifest')->assertUnauthorized()->assertJsonPath('error.code', 'invalid_token');
    app(DeviceLifecycle::class)->revoke($this->company->id, $this->device->id);
    $this->withToken($this->token)->getJson('/api/device/manifest')->assertUnauthorized();
    $this->getJson('/api/device/media/'.$this->asset->id)->assertUnauthorized();
});

test('disabled Device and inactive Company return safe machine readable errors', function () {
    // Preserve the valid secret to exercise state-specific errors; UI disable also revokes.
    $this->device->forceFill(['status' => 'disabled'])->save();
    $this->getJson('/api/device/manifest')->assertUnauthorized()->assertJsonPath('error.code', 'device_disabled');
    $this->getJson('/api/device/media/'.$this->asset->id)->assertUnauthorized();
    $this->device->forceFill(['status' => 'active'])->save();
    $this->company->update(['is_active' => false]);
    $this->getJson('/api/device/manifest')->assertUnauthorized()->assertJsonPath('error.code', 'company_inactive');
    $this->getJson('/api/device/media/'.$this->asset->id)->assertUnauthorized();
});

test('client identity query and bodies are rejected and schema is explicit', function () {
    $this->getJson('/api/device/manifest?company_id='.$this->other->id)->assertUnprocessable();
    $this->getJson('/api/device/media/'.$this->asset->id.'?token=secret')->assertUnprocessable();
    $this->call('GET', '/api/device/manifest', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token], '{"device_id":1}')->assertUnprocessable();
    $this->withHeader('X-Player-Schema-Version', '2')->getJson('/api/device/manifest')->assertStatus(406)->assertJsonPath('error.code', 'unsupported_schema_version');
});

test('schedule priority ties gaps and upcoming playlists use the existing resolver', function () {
    $future = Playlist::create(['company_id' => $this->company->id, 'name' => 'Future', 'is_active' => true]);
    $later = playerSchedule($this->company->id, $future->id, ['starts_at_utc' => '2026-11-02T06:00:00Z', 'ends_at_utc' => '2026-11-02T08:00:00Z', 'priority' => 10]);
    playerSchedule($this->company->id, $future->id); // Equal priority: lowest ID wins.
    $response = $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('currentSchedule.id', $this->schedule->id);
    expect(array_column($response->json('playlists'), 'id'))->toContain($future->id, $this->playlist->id);
    expect(collect($response->json('schedulePlan'))->contains(fn ($s) => ($s['schedule']['id'] ?? null) === $later->id))->toBeTrue();
    CarbonImmutable::setTestNow('2026-11-02T05:30:00Z');
    $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('state', 'no_schedule')->assertJsonPath('currentSchedule', null)
        ->assertJsonPath('nextTransitionUtc', '2026-11-02T06:00:00+00:00');
});

test('weekly overnight plan uses local start day and UTC transitions', function () {
    $this->schedule->update(['schedule_type' => 'weekly', 'starts_at_utc' => null, 'ends_at_utc' => null, 'start_date' => '2026-11-02',
        'end_date' => '2026-11-02', 'weekdays' => [1], 'start_time' => '22:00:00', 'end_time' => '02:00:00']);
    CarbonImmutable::setTestNow('2026-11-02T16:00:00Z');
    $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('state', 'scheduled')->assertJsonPath('nextTransitionUtc', '2026-11-02T19:00:00+00:00');
});

test('inactive schedules playlists and cross company schedule references never play', function () {
    $this->schedule->update(['is_active' => false]);
    $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('state', 'no_schedule')->assertJsonCount(0, 'playlists')->assertJsonCount(0, 'media');
    $this->schedule->update(['is_active' => true]);
    $this->playlist->update(['is_active' => false]);
    $this->getJson('/api/device/manifest')->assertJsonPath('state', 'no_schedule');
    $this->playlist->update(['is_active' => true, 'company_id' => $this->other->id]);
    $this->getJson('/api/device/manifest')->assertJsonPath('state', 'no_schedule');
});

test('layouts preserve sort geometry visibility fits backgrounds and auto manual durations', function () {
    $first = $this->playlist->pages()->create(['name' => 'First', 'sort_order' => 1, 'duration_seconds' => 9, 'background_color' => '#000000']);
    $back = $this->page->zones()->create(['name' => 'Back', 'z_index' => -1, 'width' => 100, 'height' => 100, 'is_visible' => false, 'fit_mode' => 'contain']);
    $image = playerAsset($this->company->id);
    $imageItem = $this->zone->items()->create(['media_asset_id' => $image->id, 'sort_order' => 1, 'duration_seconds' => 7]);
    $layout = $this->getJson('/api/device/manifest')->assertOk()->json('playlists.0');
    expect(array_column($layout['pages'], 'id'))->toBe([$first->id, $this->page->id]);
    expect($layout['pages'][0]['background'])->toBe('#000000');
    expect($layout['pages'][1]['background'])->toBe('#112233');
    expect($layout['pages'][1]['zones'][0]['id'])->toBe($back->id);
    expect($layout['pages'][1]['zones'][0]['visible'])->toBeFalse();
    $zone = $layout['pages'][1]['zones'][1];
    expect([$zone['x'], $zone['y'], $zone['width'], $zone['height'], $zone['fit_mode']])->toBe([10, 20, 600, 300, 'stretch']);
    expect(array_column($zone['items'], 'id'))->toBe([$imageItem->id, $this->item->id]);
    expect($zone['items'][0]['duration'])->toBe(7);
    expect($zone['items'][1]['duration'])->toBeNull();
    expect($zone['items'][1]['durationMode'])->toBe('auto');
});

test('revision changes for persisted content independent of timestamps', function (string $change) {
    $before = app(DeviceManifestService::class)->build($this->device)['revision'];
    match ($change) {
        'geometry' => DB::table('playlist_zones')->where('id', $this->zone->id)->update(['x' => 55]),
        'duration' => $this->item->update(['duration_seconds' => 20]),
        'page_order' => $this->page->update(['sort_order' => 0]),
        'page_add' => $this->playlist->pages()->create(['name' => 'Extra', 'duration_seconds' => 4]),
        'schedule_priority' => $this->schedule->update(['priority' => 20]),
        'schedule_range' => $this->schedule->update(['ends_at_utc' => '2026-11-02T05:30:00Z']),
        'file' => Storage::disk('local')->put($this->asset->path, 'abcdefghij'),
        'output' => $this->device->update(['screen_width' => 1000]),
        'playlist' => $this->playlist->update(['background_color' => '#ffffff']),
        'item_order' => $this->item->update(['sort_order' => 1]),
    };
    expect(app(DeviceManifestService::class)->build($this->device->fresh())['revision'])->not->toBe($before);
})->with(['geometry', 'duration', 'page_order', 'page_add', 'schedule_priority', 'schedule_range', 'file', 'output', 'playlist', 'item_order']);

test('unchanged manifest has useful weak ETag despite a live clock and time transitions invalidate it', function () {
    $first = $this->getJson('/api/device/manifest')->assertOk();
    CarbonImmutable::setTestNow('2026-11-02T03:16:00Z');
    $this->withHeader('If-None-Match', $first->headers->get('ETag'))->get('/api/device/manifest')->assertStatus(304)->assertContent('')
        ->assertHeader('X-Server-Time-Utc', '2026-11-02T03:16:00+00:00');
    CarbonImmutable::setTestNow('2026-11-02T05:00:00Z');
    $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('state', 'no_schedule');
});

test('same hour schedule transition invalidates ETag without database changes', function () {
    $this->schedule->update(['ends_at_utc' => '2026-11-02T03:30:00Z']);
    $etag = $this->getJson('/api/device/manifest')->headers->get('ETag');
    CarbonImmutable::setTestNow('2026-11-02T03:30:00Z');
    $this->withHeader('If-None-Match', $etag)->getJson('/api/device/manifest')->assertOk()->assertJsonPath('state', 'no_schedule');
});

test('cross company forged and unrelated media cannot be downloaded', function () {
    $foreign = playerAsset($this->other->id);
    $unrelated = playerAsset($this->company->id);
    foreach ([$foreign->id, $unrelated->id, '999999', 'not-an-id'] as $id) {
        $this->getJson('/api/device/media/'.$id)->assertNotFound()->assertJsonPath('error.code', 'media_not_found');
    }
    $this->zone->items()->create(['media_asset_id' => $foreign->id]);
    $this->getJson('/api/device/manifest')->assertOk()->assertJsonCount(1, 'media');
    $this->getJson('/api/device/media/'.$foreign->id)->assertNotFound();
    $this->get('/storage/'.$this->asset->path)->assertForbidden();
});

test('image download streams full bytes and conditional response preserves authorization', function () {
    $image = playerAsset($this->company->id, 'image', 'image-bytes');
    $this->zone->items()->create(['media_asset_id' => $image->id]);
    $response = $this->get('/api/device/media/'.$image->id)->assertOk()->assertHeader('Content-Length', '11')->assertHeader('Content-Type', 'image/png');
    expect($response->streamedContent())->toBe('image-bytes');
    $this->withHeader('If-None-Match', $response->headers->get('ETag'))->get('/api/device/media/'.$image->id)->assertStatus(304)->assertContent('');
    app(DeviceLifecycle::class)->revoke($this->company->id, $this->device->id);
    $this->get('/api/device/media/'.$image->id)->assertUnauthorized();
});

test('Range GET suffix open ended HEAD If-Range and invalid ranges are supported', function () {
    $url = '/api/device/media/'.$this->asset->id;
    $response = $this->withHeader('Range', 'bytes=2-5')->get($url)->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')->assertHeader('Content-Length', '4');
    expect($response->streamedContent())->toBe('2345');
    expect($this->withHeader('Range', 'bytes=-3')->get($url)->assertStatus(206)->streamedContent())->toBe('789');
    expect($this->withHeader('Range', 'bytes=8-')->get($url)->assertStatus(206)->streamedContent())->toBe('89');
    $this->head($url)->assertOk()->assertContent('')->assertHeader('Content-Length', '10');
    foreach (['bytes=10-', 'bytes=5-2', 'bytes=-0', 'bytes=0-1,4-5', 'wrong', 'bytes=-'] as $range) {
        $this->withHeader('Range', $range)->getJson($url)->assertStatus(416)->assertHeader('Content-Range', 'bytes */10');
    }
    expect($this->withHeader('If-Range', '"old"')->get($url)->assertOk()->streamedContent())->toBe('0123456789');
});

test('missing inactive and unsafe path files never expose filesystem details', function () {
    Storage::disk('local')->delete($this->asset->path);
    $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('media.0.available', false);
    $response = $this->getJson('/api/device/media/'.$this->asset->id)->assertNotFound()->assertJsonPath('error.code', 'media_unavailable');
    expect($response->getContent())->not->toContain($this->asset->path, 'trace', 'exception');
    $this->asset->update(['path' => '../../.env']);
    $this->getJson('/api/device/media/'.$this->asset->id)->assertNotFound();
    $this->asset->update(['is_active' => false]);
    $this->getJson('/api/device/manifest')->assertJsonCount(0, 'media');
});

test('scheduler changes during synchronization require restart and removed media is immediately denied', function () {
    $revision = $this->getJson('/api/device/manifest')->json('revision');
    $this->page->update(['duration_seconds' => 22]);
    $this->withHeader('X-Content-Revision', $revision)->getJson('/api/device/media/'.$this->asset->id)->assertStatus(409)->assertJsonPath('error.code', 'sync_revision_changed');
    $this->schedule->update(['is_active' => false]);
    $this->getJson('/api/device/media/'.$this->asset->id)->assertNotFound();
});

test('manifest and range requests do not consume heartbeat buckets', function () {
    for ($i = 0; $i < 11; $i++) {
        $this->getJson('/api/device/manifest')->assertOk();
        $this->withHeader('Range', 'bytes=0-0')->get('/api/device/media/'.$this->asset->id)->assertStatus(206)->streamedContent();
    }
    $this->postJson('/api/device/heartbeat', [])->assertOk();
});

test('large layout uses bounded eager loading query count instead of per item queries', function () {
    for ($p = 0; $p < 12; $p++) {
        $page = $this->playlist->pages()->create(['name' => 'Extra '.$p, 'sort_order' => $p + 3, 'duration_seconds' => 15]);
        for ($z = 0; $z < 4; $z++) {
            $zone = $page->zones()->create(['name' => 'Window', 'width' => 100, 'height' => 100]);
            for ($i = 0; $i < 6; $i++) {
                $zone->items()->create(['media_asset_id' => $this->asset->id, 'sort_order' => $i]);
            }
        }
    }
    DB::enableQueryLog();
    $manifest = app(DeviceManifestService::class)->build($this->device);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($manifest['playlists'][0]['pages'])->toHaveCount(13);
    expect($manifest['media'])->toHaveCount(1);
    expect($count)->toBeLessThanOrEqual(12);
});

test('internal failures return sanitized errors with tracing even in debug mode', function () {
    config(['app.debug' => true]);
    $this->mock(DeviceManifestService::class)->shouldReceive('build')->andThrow(new RuntimeException('SECRET filesystem /private/database'));
    $response = $this->getJson('/api/device/manifest')->assertStatus(500)->assertJsonPath('error.code', 'request_failed');
    expect($response->getContent())->not->toContain('SECRET', 'trace', 'exception', '/private');
    expect($response->json('requestId'))->toBe($response->headers->get('X-Request-ID'));
});

test('future winning layouts include their media before becoming current', function () {
    $future = Playlist::create(['company_id' => $this->company->id, 'name' => 'Future Media', 'is_active' => true]);
    $page = $future->pages()->create(['name' => 'Future Page', 'duration_seconds' => 10]);
    $zone = $page->zones()->create(['name' => 'Future Window', 'width' => 100, 'height' => 100]);
    $asset = playerAsset($this->company->id);
    $zone->items()->create(['media_asset_id' => $asset->id]);
    playerSchedule($this->company->id, $future->id, ['starts_at_utc' => '2026-11-04T10:00:00Z', 'ends_at_utc' => '2026-11-04T12:00:00Z']);
    $response = $this->getJson('/api/device/manifest')->assertOk()->assertJsonCount(2, 'media');
    expect(array_column($response->json('media'), 'id'))->toContain($asset->id);
    expect($this->get('/api/device/media/'.$asset->id)->assertOk()->streamedContent())->toBe('image');
});

test('shadowed schedule edits change revision without changing current winner', function () {
    $loser = playerSchedule($this->company->id, $this->playlist->id, ['priority' => -10]);
    $first = $this->getJson('/api/device/manifest')->assertOk();
    $loser->update(['priority' => -5]);
    $second = $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('currentSchedule.id', $this->schedule->id);
    expect($second->json('revision'))->not->toBe($first->json('revision'));
});

test('optional per item transforms change revision and do not expose arbitrary JSON settings', function () {
    $before = $this->getJson('/api/device/manifest')->assertOk()->json('revision');
    $this->item->update(['settings' => ['editor_request_id' => 'private-editor-key', 'media_transform' => ['scale' => 2, 'offset_x' => 15, 'offset_y' => -5, 'fit_mode' => 'cover', 'keep_aspect_ratio' => true]]]);
    $response = $this->getJson('/api/device/manifest')->assertOk()->assertJsonPath('playlists.0.pages.0.zones.0.items.0.mediaTransform.scale', 2);
    expect($response->json('revision'))->not->toBe($before);
    expect($response->getContent())->not->toContain('private-editor-key');
});

test('manifest horizon is bounded and has contiguous explicit gaps', function () {
    config(['player.horizon_hours' => 999]);
    $response = $this->getJson('/api/device/manifest')->assertOk();
    expect(CarbonImmutable::parse($response->json('validUntilUtc'))->diffInHours(CarbonImmutable::parse($response->json('planStartUtc')), true))->toBe(168.0);
    $plan = $response->json('schedulePlan');
    expect($plan[0]['startUtc'])->toBe($response->json('planStartUtc'));
    expect(end($plan)['endUtc'])->toBe($response->json('validUntilUtc'));
    for ($i = 1; $i < count($plan); $i++) {
        expect($plan[$i]['startUtc'])->toBe($plan[$i - 1]['endUtc']);
    }
    expect(end($plan)['state'])->toBe('no_schedule');
});
