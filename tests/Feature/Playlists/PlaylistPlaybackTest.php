<?php

use App\Livewire\Playlists\PlaylistStudio;
use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\User;
use App\Services\PlaylistPlayback;
use App\Services\WorkspaceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    foreach (['playlists.view', 'playlists.edit', 'media.view', 'dashboard.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Playback A', 'code' => 'PLAYA', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Playback B', 'code' => 'PLAYB', 'is_active' => true]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['playlists.view', 'playlists.edit', 'media.view', 'dashboard.view']);
    $this->playlist = Playlist::create(['company_id' => $this->company->id, 'name' => 'Timeline', 'canvas_width' => 1920, 'canvas_height' => 1080, 'background_color' => '#112233']);
    $this->second = $this->playlist->pages()->create(['name' => 'Second', 'sort_order' => 2, 'duration_seconds' => 20]);
    $this->page = $this->playlist->pages()->create(['name' => 'First', 'sort_order' => 1, 'duration_seconds' => 15, 'background_color' => '']);
    $this->zone = $this->page->zones()->create(['name' => 'Main', 'width' => 800, 'height' => 450, 'fit_mode' => 'stretch']);
    $this->image = playbackAsset($this->company->id, 'image');
    $this->video = playbackAsset($this->company->id, 'video');
    $this->video->update(['duration' => 12]);
    $this->imageItem = $this->zone->items()->create(['media_asset_id' => $this->image->id, 'sort_order' => 2]);
    $this->videoItem = $this->zone->items()->create(['media_asset_id' => $this->video->id, 'sort_order' => 1]);
    $this->foreign = Playlist::create(['company_id' => $this->other->id, 'name' => 'Foreign Secret']);
    $this->foreignPage = $this->foreign->pages()->create(['name' => 'Private']);
    $this->foreignZone = $this->foreignPage->zones()->create(['name' => 'Private', 'width' => 200, 'height' => 100]);
    $this->foreignAsset = playbackAsset($this->other->id, 'video');
    $this->foreignItem = $this->foreignZone->items()->create(['media_asset_id' => $this->foreignAsset->id]);
    $this->actingAs($this->user);
});

function playbackAsset(int $company, string $type): MediaAsset
{
    return MediaAsset::create(['company_id' => $company, 'title' => $type.' source', 'type' => $type, 'file_name' => $type.'.dat', 'original_name' => $type.'.dat', 'path' => $type.'.dat', 'disk' => 'public', 'mime_type' => $type === 'image' ? 'image/png' : 'video/mp4']);
}
function playbackComponent($test)
{
    return Livewire::test(PlaylistStudio::class, ['playlistId' => $test->playlist->id]);
}

test('image and video duration changes persist and video can return to Auto', function () {
    $component = playbackComponent($this);
    $component->call('updateItemDuration', $this->page->id, $this->zone->id, $this->imageItem->id, 7)->assertHasNoErrors()->assertReturned(true)
        ->call('updateItemDuration', $this->page->id, $this->zone->id, $this->videoItem->id, 30)->assertHasNoErrors();
    expect($this->imageItem->fresh()->duration_seconds)->toBe(7);
    expect($this->videoItem->fresh()->duration_seconds)->toBe(30);
    playbackComponent($this)->assertSee('7s')->call('resetItemDurationToAuto', $this->page->id, $this->zone->id, $this->videoItem->id)->assertHasNoErrors();
    expect($this->videoItem->fresh()->duration_seconds)->toBeNull();
    $component->call('resetItemDurationToAuto', $this->page->id, $this->zone->id, $this->imageItem->id)->assertHasErrors('duration');
});

test('manual durations are integer seconds from one to 86400', function () {
    foreach ([0, -1, 86401, 1.5, 'invalid'] as $duration) {
        playbackComponent($this)->call('updateItemDuration', $this->page->id, $this->zone->id, $this->videoItem->id, $duration)->assertHasErrors('duration');
    }
    foreach ([1, 86400] as $duration) {
        playbackComponent($this)->call('updateItemDuration', $this->page->id, $this->zone->id, $this->videoItem->id, $duration)->assertHasNoErrors();
    }
});

test('new image items default to ten seconds and videos to null Auto', function () {
    foreach ([$this->image, $this->video] as $asset) {
        playbackComponent($this)->call('dropMedia', $this->page->id, $asset->id, ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 100], (string) Str::uuid())->assertHasNoErrors();
        $zone = $this->page->zones()->where('name', $asset->title)->firstOrFail();
        expect($zone->items()->sole()->duration_seconds)->toBe($asset->type === 'image' ? 10 : null);
        playbackComponent($this)->call('addWindowMedia', $this->page->id, $this->zone->id, $asset->id, (string) Str::uuid())->assertHasNoErrors();
        expect($this->zone->items()->reorder()->latest('id')->first()->duration_seconds)->toBe($asset->type === 'image' ? 10 : null);
    }
});

test('manifest orders pages windows items and supplies known Auto durations and protected URLs', function () {
    $this->page->zones()->create(['name' => 'Back', 'width' => 100, 'height' => 100, 'z_index' => -2, 'is_visible' => false]);
    $manifest = app(PlaylistPlayback::class)->manifest($this->playlist->id);
    expect($manifest['totalDuration'])->toBe(35);
    expect(array_column($manifest['pages'], 'id'))->toBe([$this->page->id, $this->second->id]);
    expect($manifest['pages'][0]['background'])->toBe('#112233');
    expect($manifest['pages'][0]['zones'][0]['visible'])->toBeFalse();
    $items = $manifest['pages'][0]['zones'][1]['items'];
    expect(array_column($items, 'id'))->toBe([$this->videoItem->id, $this->imageItem->id]);
    expect($items[0]['duration'])->toBeNull();
    expect($items[0]['knownDuration'])->toBe(12.0);
    expect($items[1]['duration'])->toBe(10);
    expect($items[0]['src'])->toBe(route('admin.media-assets.stream', $this->video->id));
    expect($items[1]['src'])->toBe(route('admin.media-assets.stream', $this->image->id));
    $this->video->update(['duration' => null, 'metadata' => ['duration' => 6.5]]);
    expect(app(PlaylistPlayback::class)->manifest($this->playlist->id)['pages'][0]['zones'][1]['items'][0]['knownDuration'])->toBe(6.5);
    $this->video->update(['metadata' => ['duration' => 'unknown']]);
    expect(app(PlaylistPlayback::class)->manifest($this->playlist->id)['pages'][0]['zones'][1]['items'][0]['knownDuration'])->toBeNull();
});

test('manifest and preview reads never save drafts geometry durations or media files', function () {
    Storage::fake('public');
    Storage::disk('public')->put($this->video->path, 'original');
    $geometry = $this->zone->fresh()->getAttributes();
    $attributes = $this->playlist->fresh()->getAttributes();
    playbackComponent($this)->set('name', 'Unsaved draft')->set('canvasWidth', 1000)->call('getPlaybackManifest')->assertHasNoErrors()->assertSet('name', 'Unsaved draft');
    expect($this->playlist->fresh()->getAttributes())->toBe($attributes);
    expect($this->zone->fresh()->getAttributes())->toBe($geometry);
    expect($this->videoItem->fresh()->duration_seconds)->toBeNull();
    expect(Storage::disk('public')->get($this->video->path))->toBe('original');
});

test('foreign manifests and corrupt cross-company media references cannot leak data', function () {
    expect(fn () => app(PlaylistPlayback::class)->manifest($this->foreign->id))->toThrow(ModelNotFoundException::class);
    $item = $this->zone->items()->create(['media_asset_id' => $this->foreignAsset->id, 'sort_order' => 3]);
    $manifest = app(PlaylistPlayback::class)->manifest($this->playlist->id);
    $entry = collect($manifest['pages'][0]['zones'][0]['items'])->firstWhere('id', $item->id);
    expect($entry['src'])->toBeNull();
    expect($entry['knownDuration'])->toBeNull();
    expect($entry['available'])->toBeFalse();
    expect(json_encode($manifest))->not->toContain('Foreign Secret');
    $component = playbackComponent($this);
    expect(fn () => $component->call('updateItemDuration', $this->page->id, $this->zone->id, $item->id, 5))->toThrow(ModelNotFoundException::class);
});

test('forged page zone and item identifiers are rejected for duration updates', function () {
    foreach ([[$this->foreignPage->id, $this->foreignZone->id, $this->foreignItem->id], [$this->page->id, $this->foreignZone->id, $this->imageItem->id], [$this->page->id, $this->zone->id, $this->foreignItem->id]] as $ids) {
        $component = playbackComponent($this);
        expect(fn () => $component->call('updateItemDuration', ...array_merge($ids, [5])))->toThrow(ModelNotFoundException::class);
    }
    playbackComponent($this)->call('updateItemDuration', $this->second->id, $this->zone->id, $this->imageItem->id, 5)->assertStatus(409);
});

test('view-only can read manifest but cannot change durations and media permission is respected', function () {
    $this->user->revokePermissionTo('playlists.edit');
    playbackComponent($this)->call('getPlaybackManifest')->assertHasNoErrors();
    playbackComponent($this)->call('updateItemDuration', $this->page->id, $this->zone->id, $this->imageItem->id, 5)->assertForbidden();
    playbackComponent($this)->call('resetItemDurationToAuto', $this->page->id, $this->zone->id, $this->videoItem->id)->assertForbidden();
    $this->user->revokePermissionTo('media.view');
    $manifest = app(PlaylistPlayback::class)->manifest($this->playlist->id);
    expect($manifest['pages'][0]['zones'][0]['items'][0]['src'])->toBeNull();
});

test('all companies or missing view permission cannot obtain manifests', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    expect(fn () => app(PlaylistPlayback::class)->manifest($this->playlist->id))->toThrow(HttpException::class);
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $component = playbackComponent($this);
    session()->forget(WorkspaceService::SESSION_KEY);
    $component->call('getPlaybackManifest')->assertForbidden();
    $this->user->removeRole('super-admin')->revokePermissionTo('playlists.view');
    playbackComponent($this)->assertForbidden();
});

test('manifest uses bounded eager queries instead of N plus one', function () {
    app(PlaylistPlayback::class)->manifest($this->playlist->id);
    DB::enableQueryLog();
    DB::flushQueryLog();
    app(PlaylistPlayback::class)->manifest($this->playlist->id);
    $small = count(DB::getQueryLog());
    for ($i = 0; $i < 10; $i++) {
        $zone = $this->second->zones()->create(['name' => 'More', 'width' => 100, 'height' => 100]);
        $zone->items()->create(['media_asset_id' => $this->image->id]);
    }
    DB::flushQueryLog();
    app(PlaylistPlayback::class)->manifest($this->playlist->id);
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($large)->toBeLessThanOrEqual($small + 1);
});

test('existing stream supports bounded open-ended suffix ranges and authorized images', function () {
    Storage::fake('public');
    Storage::disk('public')->put($this->video->path, '0123456789');
    Storage::disk('public')->put($this->image->path, 'image');
    $url = route('admin.media-assets.stream', $this->video->id);
    $response = $this->withHeader('Range', 'bytes=2-5')->get($url)->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')->assertHeader('Content-Length', '4');
    expect($response->streamedContent())->toBe('2345');
    $response = $this->withHeader('Range', 'bytes=-3')->get($url)->assertStatus(206)->assertHeader('Content-Range', 'bytes 7-9/10');
    expect($response->streamedContent())->toBe('789');
    $response = $this->withHeader('Range', 'bytes=6-')->get($url)->assertStatus(206);
    expect($response->streamedContent())->toBe('6789');
    foreach (['bytes=99-', 'bytes=-0', 'bytes=5-2', 'bytes=', 'bytes=0-1,5-6'] as $range) {
        $this->withHeader('Range', $range)->get($url)->assertStatus(416);
    }
    $this->flushHeaders();
    $this->get(route('admin.media-assets.stream',$this->image->id))->assertOk()->assertHeader('Content-Type','image/png');
    $this->get(route('admin.media-assets.stream',$this->foreignAsset->id))->assertNotFound();
    $this->user->revokePermissionTo('media.view');
    $this->get($url)->assertForbidden();
});
