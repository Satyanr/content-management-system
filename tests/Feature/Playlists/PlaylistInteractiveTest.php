<?php

use App\Livewire\Media\MediaTable;
use App\Livewire\Playlists\PlaylistStudio;
use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['playlists.view', 'playlists.edit', 'media.view', 'media.delete'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Editor A', 'code' => 'EDITA', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Editor B', 'code' => 'EDITB', 'is_active' => true]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['playlists.view', 'playlists.edit', 'media.view', 'media.delete']);
    $this->playlist = Playlist::create(['company_id' => $this->company->id, 'name' => 'Interactive', 'canvas_width' => 1920, 'canvas_height' => 1080]);
    $this->page = $this->playlist->pages()->create(['name' => 'Page 1']);
    $this->foreign = Playlist::create(['company_id' => $this->other->id, 'name' => 'Private']);
    $this->foreignPage = $this->foreign->pages()->create(['name' => 'Private Page']);
    $this->zone = $this->page->zones()->create(['name' => 'Main', 'x' => 100, 'y' => 100, 'width' => 400, 'height' => 200]);
    $this->foreignZone = $this->foreignPage->zones()->create(['name' => 'Private Window', 'width' => 100, 'height' => 100]);
    $this->media = interactiveAsset($this->company->id);
    $this->foreignMedia = interactiveAsset($this->other->id);
    $this->actingAs($this->user);
});

function interactiveAsset(int $companyId, string $type = 'image'): MediaAsset
{
    return MediaAsset::create(['company_id' => $companyId, 'title' => $type.' asset', 'type' => $type, 'width' => 800, 'height' => 400, 'file_name' => $type.'.dat', 'original_name' => $type.'.dat', 'path' => $type.'.dat', 'disk' => 'public']);
}

function interactiveComponent($test)
{
    return Livewire::test(PlaylistStudio::class, ['playlistId' => $test->playlist->id]);
}

test('dropping image or video atomically creates a selected window and item and retries are idempotent', function (string $type) {
    $media = interactiveAsset($this->company->id, $type);
    $token = (string) Str::uuid();
    $box = ['x' => 320, 'y' => 180, 'width' => 640, 'height' => 360];
    $component = interactiveComponent($this)->call('dropMedia', $this->page->id, $media->id, $box, $token)->assertHasNoErrors()->assertReturned(true);
    $zone = $this->page->zones()->where('name', $media->title)->sole();
    expect($zone->only(array_keys($box)))->toBe($box);
    expect($zone->items()->sole()->media_asset_id)->toBe($media->id);
    $component->assertDispatched('studio-select-window', id: $zone->id)
        ->call('dropMedia', $this->page->id, $media->id, $box, $token)->assertHasNoErrors();
    expect($this->page->zones()->count())->toBe(2);
    expect($zone->items()->count())->toBe(1);
    $component->assertSee($type === 'image' ? '<img' : '<video', false);
})->with(['image', 'video']);

test('invalid drop coordinates and tokens never create partial records', function () {
    $component = interactiveComponent($this);
    foreach ([['x' => -1, 'y' => 0, 'width' => 100, 'height' => 100], ['x' => 1900, 'y' => 0, 'width' => 100, 'height' => 100], ['x' => 0, 'y' => 0, 'width' => 0, 'height' => 100]] as $box) {
        $component->call('dropMedia', $this->page->id, $this->media->id, $box, (string) Str::uuid())->assertHasErrors();
    }
    $component->call('dropMedia', $this->page->id, $this->media->id, ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100], 'invalid')->assertHasErrors('token');
    expect($this->page->zones()->count())->toBe(1);
    $this->assertDatabaseCount('playlist_zone_items', 0);
});

test('properties persist after reload and fit affects rendering without changing source file', function () {
    Storage::fake('public');
    Storage::disk('public')->put($this->media->path, 'immutable-source');
    $this->zone->items()->create(['media_asset_id' => $this->media->id]);
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, [
        'name' => 'New Window', 'x' => 200, 'y' => 250, 'width' => 600, 'height' => 300,
        'keep_aspect_ratio' => true, 'fit_mode' => 'stretch', 'z_index' => 12, 'is_visible' => true, 'is_locked' => false,
    ])->assertHasNoErrors()->assertReturned(true);
    interactiveComponent($this)->assertSee('New Window')->assertSee('data-width="600"', false)->assertSee('object-fit:fill', false);
    expect($this->zone->fresh()->only(['x', 'y', 'width', 'height', 'fit_mode', 'z_index']))->toBe(['x' => 200, 'y' => 250, 'width' => 600, 'height' => 300, 'fit_mode' => 'stretch', 'z_index' => 12]);
    expect(Storage::disk('public')->get($this->media->path))->toBe('immutable-source');
    foreach (['contain', 'cover'] as $fit) {
        interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['fit_mode' => $fit])->assertHasNoErrors()->assertSee('object-fit:'.$fit, false);
    }
});

test('aspect ratio server validation and free sizing are enforced', function () {
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['width' => 600, 'height' => 250])->assertHasErrors('geometry');
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['width' => 600, 'height' => 300])->assertHasNoErrors();
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['width' => 600, 'height' => 250, 'keep_aspect_ratio' => false])->assertHasNoErrors();
    expect($this->zone->fresh()->height)->toBe(250);
});

test('locked geometry cannot be changed even with an unlock in the same forged request', function () {
    $this->zone->update(['is_locked' => true]);
    foreach ([['x' => 200], ['width' => 600, 'height' => 300], ['x' => 200, 'is_locked' => false]] as $data) {
        interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, $data)->assertHasErrors('geometry');
    }
    expect($this->zone->fresh()->x)->toBe(100);
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['name' => 'Locked renamed'])->assertHasNoErrors();
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['is_locked' => false])->assertHasNoErrors();
    interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, ['x' => 200])->assertHasNoErrors();
});

test('bounds fit mode and positive integer geometry are validated', function () {
    foreach ([['x' => 1600], ['y' => 1000], ['x' => -1], ['width' => 0], ['height' => 1.5], ['fit_mode' => 'invalid']] as $data) {
        interactiveComponent($this)->call('updateWindow', $this->page->id, $this->zone->id, $data)->assertHasErrors();
    }
    expect($this->zone->fresh()->width)->toBe(400);
});

test('items can be added removed reordered and duplicate retries do not add items', function () {
    $component = interactiveComponent($this);
    $token = (string) Str::uuid();
    $component->call('addWindowMedia', $this->page->id, $this->zone->id, $this->media->id, $token)->assertHasNoErrors()
        ->call('addWindowMedia', $this->page->id, $this->zone->id, $this->media->id, $token)->assertHasNoErrors();
    $video = interactiveAsset($this->company->id, 'video');
    $component->call('addWindowMedia', $this->page->id, $this->zone->id, $video->id, (string) Str::uuid())->assertHasNoErrors();
    $ids = $this->zone->items()->pluck('id')->all();
    expect($ids)->toHaveCount(2);
    $component->call('reorderWindowMedia', $this->page->id, $this->zone->id, array_reverse($ids))->assertHasNoErrors();
    expect($this->zone->items()->pluck('id')->all())->toBe(array_reverse($ids));
    $component->call('reorderWindowMedia', $this->page->id, $this->zone->id, [$ids[0], $ids[0]])->assertHasErrors()
        ->call('removeWindowMedia', $this->page->id, $this->zone->id, $ids[0])->assertHasNoErrors();
    expect($this->zone->items()->count())->toBe(1);
    $this->assertDatabaseHas('media_assets', ['id' => $this->media->id]);
});

test('foreign media page zone and item IDs are rejected on every mutation path', function () {
    $box = ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100];
    $foreignItem = $this->foreignZone->items()->create(['media_asset_id' => $this->foreignMedia->id]);
    $actions = [
        ['dropMedia', $this->page->id, $this->foreignMedia->id, $box, (string) Str::uuid()],
        ['dropMedia', $this->foreignPage->id, $this->media->id, $box, (string) Str::uuid()],
        ['updateWindow', $this->page->id, $this->foreignZone->id, ['x' => 0]],
        ['deleteWindow', $this->page->id, $this->foreignZone->id],
        ['addWindowMedia', $this->page->id, $this->zone->id, $this->foreignMedia->id, (string) Str::uuid()],
        ['addWindowMedia', $this->page->id, $this->foreignZone->id, $this->media->id, (string) Str::uuid()],
        ['removeWindowMedia', $this->page->id, $this->zone->id, $foreignItem->id],
        ['changeWindowLayer', $this->page->id, $this->foreignZone->id, 'front'],
    ];
    foreach ($actions as $action) {
        $method = array_shift($action);
        $args = $action;
        $component = interactiveComponent($this);
        expect(fn () => $component->call($method, ...$args))->toThrow(ModelNotFoundException::class);
    }
    interactiveComponent($this)->call('reorderWindowMedia', $this->page->id, $this->zone->id, [$foreignItem->id])->assertHasErrors('items');
});

test('same company wrong page and stale page are rejected', function () {
    $page = $this->playlist->pages()->create(['name' => 'Other Page']);
    $zone = $page->zones()->create(['name' => 'Other Window', 'width' => 100, 'height' => 100]);
    $component = interactiveComponent($this);
    expect(fn () => $component->call('updateWindow', $this->page->id, $zone->id, ['x' => 10]))->toThrow(ModelNotFoundException::class);
    interactiveComponent($this)->call('dropMedia', $page->id, $this->media->id, ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100], (string) Str::uuid())->assertStatus(409);
});

test('missing edit and media permissions reject modifications and selection', function () {
    $this->user->revokePermissionTo('playlists.edit');
    foreach ([['dropMedia', $this->page->id, $this->media->id, ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100], (string) Str::uuid()], ['updateWindow', $this->page->id, $this->zone->id, ['x' => 0]], ['deleteWindow', $this->page->id, $this->zone->id], ['resizeCanvas', 1000, 1000], ['addWindowMedia', $this->page->id, $this->zone->id, $this->media->id, (string) Str::uuid()], ['removeWindowMedia', $this->page->id, $this->zone->id, 1], ['changeWindowLayer', $this->page->id, $this->zone->id, 'front'], ['reorderWindowMedia', $this->page->id, $this->zone->id, [1]]] as $action) {
        $method = array_shift($action);
        $args = $action;
        interactiveComponent($this)->call($method, ...$args)->assertForbidden();
    }
    $this->user->givePermissionTo('playlists.edit')->revokePermissionTo('media.view');
    interactiveComponent($this)->call('dropMedia', $this->page->id, $this->media->id, ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100], (string) Str::uuid())->assertForbidden();
    interactiveComponent($this)->call('addWindowMedia', $this->page->id, $this->zone->id, $this->media->id, (string) Str::uuid())->assertForbidden();
});

test('canvas shrink requires confirmation across all pages and preserves windows', function () {
    $page = $this->playlist->pages()->create(['name' => 'Another page']);
    $zone = $page->zones()->create(['name' => 'Edge', 'x' => 1600, 'y' => 700, 'width' => 300, 'height' => 300]);
    $original = $zone->only(['x', 'y', 'width', 'height']);
    interactiveComponent($this)->call('resizeCanvas', 1000, 800)->assertHasErrors('canvasBounds');
    expect($this->playlist->fresh()->canvas_width)->toBe(1920);
    interactiveComponent($this)->call('resizeCanvas', 1000, 800, true)->assertHasNoErrors()->assertSet('canvasWidth', 1000)->assertDispatched('studio-canvas-resized', width: 1000, height: 800);
    expect($zone->fresh()->only(['x', 'y', 'width', 'height']))->toBe($original);
    interactiveComponent($this)->set('canvasWidth', 900)->call('save')->assertHasErrors('canvasBounds')->call('save', true)->assertHasNoErrors();
    interactiveComponent($this)->call('resizeCanvas', 63, 800, true)->assertHasErrors('width');
});

test('layer controls establish a deterministic order and deletion keeps media', function () {
    $second = $this->page->zones()->create(['name' => 'Second', 'width' => 100, 'height' => 100]);
    $third = $this->page->zones()->create(['name' => 'Third', 'width' => 100, 'height' => 100]);
    $component = interactiveComponent($this);
    $order = fn () => $this->page->zones()->reorder()->orderBy('z_index')->pluck('id')->all();
    $component->call('changeWindowLayer', $this->page->id, $this->zone->id, 'front')->assertHasNoErrors();
    expect($order())->toBe([$second->id, $third->id, $this->zone->id]);
    $component->call('changeWindowLayer', $this->page->id, $this->zone->id, 'backward')->assertHasNoErrors();
    expect($order())->toBe([$second->id, $this->zone->id, $third->id]);
    $component->call('changeWindowLayer', $this->page->id, $this->zone->id, 'back')->assertHasNoErrors()
        ->call('changeWindowLayer', $this->page->id, $this->zone->id, 'forward')->assertHasNoErrors();
    expect($order())->toBe([$second->id, $this->zone->id, $third->id]);
    $item = $this->zone->items()->create(['media_asset_id' => $this->media->id]);
    $component->call('deleteWindow', $this->page->id, $this->zone->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('playlist_zone_items', ['id' => $item->id]);
    $this->assertDatabaseHas('media_assets', ['id' => $this->media->id]);
});

test('content library prevents deleting referenced assets before touching the file', function () {
    Storage::fake('public');
    Storage::disk('public')->put($this->media->path, 'protected-source');
    $this->zone->items()->create(['media_asset_id' => $this->media->id]);
    Livewire::test(MediaTable::class)->call('delete', $this->media->id)->assertSee('Media is used in a Playlist');
    Livewire::test(MediaTable::class)->set('selectedMediaIds', [(string) $this->media->id])->call('deleteSelected')->assertSee('Some selected media are used in a Playlist');
    expect(Storage::disk('public')->get($this->media->path))->toBe('protected-source');
    $this->assertDatabaseHas('media_assets', ['id' => $this->media->id]);
});

test('existing studio cannot mutate after super admin clears the workspace', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $component = interactiveComponent($this);
    session()->forget(WorkspaceService::SESSION_KEY);
    $component->call('updateWindow', $this->page->id, $this->zone->id, ['x' => 300])->assertForbidden();
    expect($this->zone->fresh()->x)->toBe(100);
});
