# CMS Digital Signage — Playlist Foundation (Step 1)

Target repo: Satyanr/content-management-system, Laravel 13 + Livewire 3.
**No GitHub changes were made.** This folder contains NEW source files. Copy into your repository root.

## New files

- `database/migrations/2026_11_02_000001_create_playlists_table.php`
- `database/migrations/2026_11_02_000002_create_playlist_pages_table.php`
- `database/migrations/2026_11_02_000003_create_playlist_zones_table.php`
- `database/migrations/2026_11_02_000004_create_playlist_zone_items_table.php`
- `app/Models/Playlist.php`, `PlaylistPage.php`, `PlaylistZone.php`, `PlaylistZoneItem.php`
- `app/Http/Controllers/Admin/PlaylistController.php`
- `app/Livewire/Playlists/PlaylistTable.php`
- `resources/views/admin/playlists/index.blade.php`
- `resources/views/livewire/playlists/playlist-table.blade.php`
- `database/seeders/PlaylistPermissionSeeder.php`
- `tests/Feature/Playlists/PlaylistIsolationTest.php`

## Edit three existing files (do NOT overwrite your current versions)

### 1. routes/web.php

Add import with other admin controllers:

```php
use App\Http\Controllers\Admin\PlaylistController;
```

Inside existing `/admin` route group add:

```php
Route::get('/playlists', [PlaylistController::class, 'index'])
    ->middleware('permission:playlists.view')
    ->name('playlists.index');
```

### 2. database/seeders/MenuSeeder.php

Find the existing Menu::query()->updateOrCreate() with title `Playlist`, replace its values with:

```php
Menu::query()->updateOrCreate(
    ['title' => 'Playlist', 'parent_id' => null],
    [
        'route' => 'admin.playlists.index',
        'icon' => 'playlist',
        'permission' => 'playlists.view',
        'sort_order' => 4,
        'is_active' => true,
    ],
);
```

### 3. database/seeders/DatabaseSeeder.php

After `RolePermissionSeeder::class`, add:

```php
PlaylistPermissionSeeder::class,
```

Example:

```php
$this->call([
    RolePermissionSeeder::class,
    PlaylistPermissionSeeder::class,
    MenuSeeder::class,
]);
```

`PlaylistPermissionSeeder` is additive: it does NOT call syncPermissions() or reset existing role assignments.
Avoid running your existing RolePermissionSeeder by itself afterward because its syncPermissions() overwrites role grants. For future new installations, always run DatabaseSeeder, or integrate playlists.* in RolePermissionSeeder lists.

## Run locally

```bash
php artisan migrate
php artisan db:seed --class=PlaylistPermissionSeeder
php artisan db:seed --class=MenuSeeder
php artisan permission:cache-reset
php artisan optimize:clear
php artisan route:list --name=playlists
php artisan test tests/Feature/Playlists/PlaylistIsolationTest.php
```

Go to: `/admin/playlists`.

## Acceptance checklist

1. Admin sees Playlist menu, operator with view-only sees but cannot create.
2. Super admin in All Companies mode sees a select-workspace message and no playlist content.
3. Select a workspace, create a Playlist, use presets or custom canvas (e.g. 4864×512).
4. New playlist auto-generates Page 1, 15 seconds.
5. Edit Playlist: name, description, canvas, background, active.
6. Company A cannot view, edit, or delete Company B's Playlist via forged Livewire ID.
7. Delete Playlist cascades pages and zones (via foreign keys).

## Important notes

- This is **Step 1 only**. Studio editor, drag/drop, resizing, and timeline are intentionally not yet implemented.
- `playlist_zone_items.media_asset_id` is restrictive on deletion. BEFORE adding media items in Step 3, update `MediaTable` deletion to show a user-friendly error when an asset is referenced by PlaylistZoneItem, otherwise the database will prevent deletion with an exception.
- Step 3 must check that `MediaAsset.company_id == Playlist.company_id` on ALL add/update paths.
- Migrating to private storage and serving authorized signed media access remains a separate security prerequisite for production.
- The test file is provided as a starting point; run it against your local project before considering the feature verified.
