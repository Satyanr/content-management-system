<?php

use App\Livewire\Playlists\PlaylistTable;
use App\Models\Company;
use App\Models\Playlist;
use App\Models\User;
use App\Services\WorkspaceService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['playlists.view', 'playlists.create', 'playlists.edit', 'playlists.delete'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
});

test('tenant user cannot edit playlist belonging to another company', function () {
    $companyA = Company::create(['name' => 'Company A', 'code' => 'A', 'is_active' => true]);
    $companyB = Company::create(['name' => 'Company B', 'code' => 'B', 'is_active' => true]);

    $user = User::factory()->create(['company_id' => $companyA->id]);
    $user->givePermissionTo(['playlists.view', 'playlists.edit']);
    $playlistB = Playlist::create([
        'company_id' => $companyB->id,
        'name' => 'Private Playlist B',
        'canvas_width' => 1920,
        'canvas_height' => 1080,
    ]);

    $this->actingAs($user);
    $component = Livewire::test(PlaylistTable::class)->assertDontSee('Private Playlist B');
    expect(fn () => $component->call('edit', $playlistB->id))
        ->toThrow(ModelNotFoundException::class);
});

test('new playlist belongs to current company and has a default page', function () {
    $company = Company::create(['name' => 'Company A', 'code' => 'A', 'is_active' => true]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->givePermissionTo(['playlists.view', 'playlists.create']);
    $this->actingAs($user);

    Livewire::test(PlaylistTable::class)
        ->call('openCreate')
        ->set('name', 'Lobby Screen')
        ->set('canvasWidth', 1920)
        ->set('canvasHeight', 384)
        ->call('save')
        ->assertHasNoErrors();

    $playlist = Playlist::query()->where('company_id', $company->id)->firstOrFail();
    expect($playlist->name)->toBe('Lobby Screen');
    expect($playlist->pages()->count())->toBe(1);
    expect($playlist->canvas_height)->toBe(384);
});

function playlistTenant(): array
{
    $company = Company::create(['name' => 'Tenant', 'code' => uniqid(), 'is_active' => true]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->givePermissionTo(['playlists.view', 'playlists.create', 'playlists.edit', 'playlists.delete']);

    return [$company, $user];
}

test('forged save and delete cannot change another tenant playlist', function () {
    [$company, $user] = playlistTenant();
    [$other] = playlistTenant();
    $playlist = Playlist::create(['company_id' => $other->id, 'name' => 'Other tenant']);
    $this->actingAs($user);
    $component = Livewire::test(PlaylistTable::class);
    expect(fn () => $component->call('delete', $playlist->id))
        ->toThrow(ModelNotFoundException::class);
    $form = Livewire::test(PlaylistTable::class)->call('openCreate');
    expect(fn () => $form->set('playlistId', $playlist->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect($playlist->fresh()->name)->toBe('Other tenant');
});

test('super admin must choose a workspace even for direct create calls', function () {
    [$company, $user] = playlistTenant();
    $user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Playlist::create(['company_id' => $company->id, 'name' => 'Hidden playlist']);
    $this->actingAs($user);
    Livewire::test(PlaylistTable::class)->assertDontSee('Hidden playlist')->call('openCreate')->assertForbidden();
    Livewire::test(PlaylistTable::class)->set('name', 'Forbidden')->call('save')->assertForbidden();
    $this->assertDatabaseMissing('playlists', ['name' => 'Forbidden']);
});

test('workspace switch invalidates an open form and forged tenant session is ignored', function () {
    [$company, $user] = playlistTenant();
    [$other] = playlistTenant();
    $this->actingAs($user)->withSession([WorkspaceService::SESSION_KEY => $other->id]);
    Livewire::test(PlaylistTable::class)->call('openCreate')->set('name', 'Owned')->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('playlists', ['name' => 'Owned', 'company_id' => $company->id]);
    $user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $component = Livewire::test(PlaylistTable::class)->call('openCreate')->set('name', 'Stale');
    session()->put(WorkspaceService::SESSION_KEY, $company->id);
    $component->call('save')->assertForbidden();
    $this->assertDatabaseMissing('playlists', ['name' => 'Stale']);
});

test('canvas validation presets update and cascading deletion work', function () {
    [$company, $user] = playlistTenant();
    $this->actingAs($user);
    $component = Livewire::test(PlaylistTable::class)->call('openCreate')
        ->set('name', '   ')->set('canvasWidth', 'bad')->set('canvasHeight', 0)
        ->set('backgroundColor', 'invalid')->call('save')
        ->assertHasErrors(['name', 'canvasWidth', 'canvasHeight', 'backgroundColor'])
        ->set('name', 'Canvas')->call('setCanvasPreset', 4864, 512)
        ->set('backgroundColor', '#112233')->call('save')->assertHasNoErrors();
    $playlist = Playlist::where('company_id', $company->id)->firstOrFail();
    expect($playlist->pages()->sole()->background_color)->toBe('#112233');
    $component->call('edit', $playlist->id)->set('canvasWidth', 1234)->set('canvasHeight', 567)
        ->set('name', 'Updated')->set('isActive', false)->call('save')->assertHasNoErrors();
    expect($playlist->fresh()->canvas_width)->toBe(1234);
    expect($playlist->fresh()->is_active)->toBeFalse();
    expect($playlist->pages()->count())->toBe(1);
    $page = $playlist->pages()->sole();
    $zone = $page->zones()->create(['name' => 'Main', 'width' => 1234, 'height' => 567]);
    $component->call('delete', $playlist->id);
    $this->assertDatabaseMissing('playlists', ['id' => $playlist->id]);
    $this->assertDatabaseMissing('playlist_pages', ['id' => $page->id]);
    $this->assertDatabaseMissing('playlist_zones', ['id' => $zone->id]);
});

test('view only users cannot mutate playlists', function () {
    [$company] = playlistTenant();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->givePermissionTo('playlists.view');
    $playlist = Playlist::create(['company_id' => $company->id, 'name' => 'Visible']);
    $this->actingAs($user);
    Livewire::test(PlaylistTable::class)->assertSee('Visible')->assertDontSee('+ New Playlist')->call('openCreate')->assertForbidden();
    Livewire::test(PlaylistTable::class)->call('edit', $playlist->id)->assertForbidden();
    Livewire::test(PlaylistTable::class)->call('delete', $playlist->id)->assertForbidden();
    Livewire::test(PlaylistTable::class)->call('save')->assertForbidden();
    Livewire::test(PlaylistTable::class)->call('setCanvasPreset', 1920, 1080)->assertForbidden();
});

test('playlist route renders sidebar and rejects missing permission', function () {
    [$company, $user] = playlistTenant();
    Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']);
    $user->givePermissionTo('dashboard.view');
    $this->seed(MenuSeeder::class);
    $this->actingAs($user)->get('/admin/playlists')->assertOk()->assertSee('Cari Playlist...')->assertSee('/admin/playlists');
    $user->revokePermissionTo('playlists.view');
    $this->get('/admin/playlists')->assertForbidden();
});

test('permission seeders preserve custom grants on repeated runs', function () {
    $custom = Permission::create(['name' => 'custom.permission', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo($custom);
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect($role->fresh()->hasPermissionTo('custom.permission'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('playlists.delete'))->toBeTrue();
});

test('selected super admin workspace scopes reads and creation', function () {
    [$company, $user] = playlistTenant();
    [$other] = playlistTenant();
    $user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Playlist::create(['company_id' => $other->id, 'name' => 'Private other workspace']);
    $this->actingAs($user)->withSession([WorkspaceService::SESSION_KEY => $company->id]);
    Livewire::test(PlaylistTable::class)->assertDontSee('Private other workspace')
        ->call('openCreate')->set('name', 'Selected workspace')->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('playlists', ['company_id' => $company->id, 'name' => 'Selected workspace']);
});

test('users without view permission cannot mount the component', function () {
    [$company] = playlistTenant();
    $user = User::factory()->create(['company_id' => $company->id]);
    $this->actingAs($user);
    Livewire::test(PlaylistTable::class)->assertForbidden();
});
