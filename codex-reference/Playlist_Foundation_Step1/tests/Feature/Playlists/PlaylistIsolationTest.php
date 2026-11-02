<?php

use App\Livewire\Playlists\PlaylistTable;
use App\Models\Company;
use App\Models\Playlist;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

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
    Livewire::test(PlaylistTable::class)
        ->assertDontSee('Private Playlist B')
        ->call('edit', $playlistB->id)
        ->assertNotFound();
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
