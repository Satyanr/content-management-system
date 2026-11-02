<?php

use App\Livewire\Playlists\PlaylistStudio;
use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['dashboard.view', 'playlists.view', 'playlists.edit', 'media.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Studio Company', 'code' => 'STUDIO', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Other Company', 'code' => 'OTHER', 'is_active' => true]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['dashboard.view', 'playlists.view', 'playlists.edit', 'media.view']);
    $this->playlist = Playlist::create(['company_id' => $this->company->id, 'name' => 'Lobby Promotion', 'canvas_width' => 4864, 'canvas_height' => 512]);
    $this->page = $this->playlist->pages()->create(['name' => 'Page 1', 'sort_order' => 1, 'duration_seconds' => 15]);
    $this->foreign = Playlist::create(['company_id' => $this->other->id, 'name' => 'Foreign secret']);
    $this->foreignPage = $this->foreign->pages()->create(['name' => 'Foreign Page']);
    $this->actingAs($this->user);
});

function studioMedia(int $companyId, string $title, string $type = 'image'): MediaAsset
{
    return MediaAsset::create(['company_id' => $companyId, 'title' => $title, 'original_name' => 'asset.jpg', 'file_name' => 'asset.jpg', 'path' => 'test/asset.jpg', 'type' => $type]);
}

test('studio route and open studio link render real playlist data', function () {
    $this->get('/admin/playlists')->assertOk()->assertSee(route('admin.playlists.studio', $this->playlist->id));
    $this->get(route('admin.playlists.studio', $this->playlist->id))->assertOk()
        ->assertSee('Playlist Studio')->assertSee('Lobby Promotion')->assertSee('data-width="4864"', false)->assertSee('data-height="512"', false);
    $this->get(route('admin.playlists.studio', $this->foreign->id))->assertNotFound()->assertDontSee('Foreign secret');
    $this->user->revokePermissionTo('playlists.view');
    $this->get(route('admin.playlists.studio', $this->playlist->id))->assertForbidden();
});

test('studio saves playlist and active page properties atomically', function () {
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])
        ->set('name', 'Renamed Lobby')->set('canvasWidth', 1080)->set('canvasHeight', 1920)
        ->set('backgroundColor', '#123456')->set('pageName', 'Opening')->set('pageDuration', 30)
        ->call('save')->assertHasNoErrors()->assertDispatched('studio-saved', width: 1080, height: 1920);
    expect($this->playlist->fresh())->name->toBe('Renamed Lobby')->canvas_width->toBe(1080)->canvas_height->toBe(1920)->background_color->toBe('#123456');
    expect($this->page->fresh())->name->toBe('Opening')->duration_seconds->toBe(30);
    expect($this->foreign->fresh()->name)->toBe('Foreign secret');
});

test('invalid properties never modify playlist or page', function () {
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])
        ->set('name', '  ')->set('canvasWidth', 'bad')->set('canvasHeight', 16385)
        ->set('backgroundColor', 'red')->set('pageName', '')->set('pageDuration', 0)
        ->call('save')->assertHasErrors(['name', 'canvasWidth', 'canvasHeight', 'backgroundColor', 'pageName', 'pageDuration']);
    expect($this->playlist->fresh()->name)->toBe('Lobby Promotion');
    expect($this->page->fresh()->duration_seconds)->toBe(15);
});

test('pages can be added selected renamed and deleted except the final page', function () {
    $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])
        ->assertSet('activePageId', $this->page->id)->call('addPage')->assertHasNoErrors();
    $second = $this->playlist->pages()->where('id', '!=', $this->page->id)->sole();
    expect($second->duration_seconds)->toBe(15);
    $component->assertSet('activePageId', $second->id)->set('pageName', 'Second')->set('pageDuration', 45)->call('save')
        ->call('selectPage', $this->page->id)->assertSet('pageName', 'Page 1')
        ->call('selectPage', $second->id)->assertSet('pageName', 'Second')->assertSet('pageDuration', 45)
        ->call('deletePage', $second->id)->assertSet('activePageId', $this->page->id)
        ->call('deletePage', $this->page->id)->assertHasErrors('pages');
    expect($this->playlist->pages()->count())->toBe(1);
});

test('foreign playlist and page IDs and locked state cannot be forged', function () {
    expect(fn () => Livewire::test(PlaylistStudio::class, ['playlistId' => $this->foreign->id]))->toThrow(ModelNotFoundException::class);
    foreach (['selectPage', 'deletePage'] as $method) {
        $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id]);
        expect(fn () => $component->call($method, $this->foreignPage->id))->toThrow(ModelNotFoundException::class);
    }
    foreach (['playlistId' => $this->foreign->id, 'activePageId' => $this->foreignPage->id] as $property => $id) {
        $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id]);
        expect(fn () => $component->set($property, $id))->toThrow(CannotUpdateLockedPropertyException::class);
    }
    expect($this->foreignPage->fresh()->name)->toBe('Foreign Page');
});

test('media results and pagination are strictly company scoped and permission checked', function () {
    studioMedia($this->company->id, 'Own image');
    studioMedia($this->company->id, 'Own video', 'video');
    studioMedia($this->other->id, 'Foreign image');
    $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])
        ->assertSee('Own image')->assertSee('Own video')->assertDontSee('Foreign image')
        ->set('mediaType', 'video')->assertSee('Own video')->assertDontSee('Own image')
        ->set('mediaSearch', 'Foreign')->assertDontSee('Foreign image')->assertDontSee('Own video');
    $this->user->revokePermissionTo('media.view');
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->assertDontSee('Own image')->assertDontSee('Own video');
});

test('view only user cannot mutate pages or canvas', function () {
    $this->user->revokePermissionTo('playlists.edit');
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->assertSee('Hanya lihat')->call('addPage')->assertForbidden();
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->call('deletePage', $this->page->id)->assertForbidden();
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->set('name', 'Forbidden')->call('save')->assertForbidden();
    expect($this->playlist->fresh()->name)->toBe('Lobby Promotion');
});

test('all companies mode and workspace switches cannot access or mutate studio', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $this->get(route('admin.playlists.studio', $this->playlist->id))->assertForbidden();
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->assertForbidden();
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id]);
    session()->put(WorkspaceService::SESSION_KEY, $this->other->id);
    expect(fn () => $component->call('addPage'))->toThrow(ModelNotFoundException::class);
    expect($this->playlist->pages()->count())->toBe(1);
});

test('windows use their original coordinates and belong to the active page', function () {
    $zone = $this->page->zones()->create(['name' => 'Own Window', 'x' => 120, 'y' => 50, 'width' => 800, 'height' => 400]);
    $this->foreignPage->zones()->create(['name' => 'Foreign Window', 'width' => 100, 'height' => 100]);
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])
        ->assertSee('Own Window')->assertDontSee('Foreign Window')->assertSee('data-x="120"', false)->assertSee('data-width="800"', false);
    expect($zone->fresh()->x)->toBe(120);
});

test('stale active page prevents partial canvas saves', function () {
    $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->set('name', 'Must rollback');
    $this->page->delete();
    expect(fn () => $component->call('save'))->toThrow(ModelNotFoundException::class);
    expect($this->playlist->fresh()->name)->toBe('Lobby Promotion');
});

test('pages from another playlist in the same company are rejected', function () {
    $sibling = Playlist::create(['company_id' => $this->company->id, 'name' => 'Sibling']);
    $siblingPage = $sibling->pages()->create(['name' => 'Sibling Page']);
    foreach (['selectPage', 'deletePage'] as $method) {
        $component = Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id]);
        expect(fn () => $component->call($method, $siblingPage->id))->toThrow(ModelNotFoundException::class);
    }
    expect($siblingPage->fresh()->name)->toBe('Sibling Page');
});

test('page deletion cascades windows and items while keeping media', function () {
    $second = $this->playlist->pages()->create(['name' => 'Second', 'sort_order' => 2]);
    $zone = $second->zones()->create(['name' => 'Window', 'width' => 100, 'height' => 100]);
    $media = studioMedia($this->company->id, 'Keep media');
    $item = $zone->items()->create(['media_asset_id' => $media->id]);
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->call('deletePage', $second->id)->assertHasNoErrors();
    $this->assertDatabaseMissing('playlist_pages', ['id' => $second->id]);
    $this->assertDatabaseMissing('playlist_zones', ['id' => $zone->id]);
    $this->assertDatabaseMissing('playlist_zone_items', ['id' => $item->id]);
    $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
});

test('disabled company is rejected even for super admin selected workspace', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $this->company->update(['is_active' => false]);
    Livewire::test(PlaylistStudio::class, ['playlistId' => $this->playlist->id])->assertForbidden();
});
