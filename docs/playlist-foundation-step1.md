# Playlist Foundation Step 1

Implemented from codex-reference/Playlist_Foundation_Step1 after reading its README and all source files.

## Changes

- Four additive migrations: playlists, playlist_pages, playlist_zones, playlist_zone_items.
- Four models with ordered relationships, company/creator/media relationships and casts; Company::playlists().
- PlaylistController, admin.playlists.index route, admin index and Livewire PlaylistTable/views.
- Workspace-scoped list/search/pagination and authorized create/edit/delete.
- Canvas presets (FHD, portrait, LED banner, ultra wide), custom width/height 64–16384, background color, description and active status.
- Creation atomically inserts Playlist and Page 1 (15 seconds).
- Locked form identity and company prevent client ID changes and saving after switching workspace.
- All Companies mode hides data and rejects creation. Non-super-admin workspace always follows user company, ignoring session overrides.
- Additive PlaylistPermissionSeeder and sidebar menu; DatabaseSeeder includes PlaylistPermissionSeeder.
- RolePermissionSeeder now grants additively so re-running it preserves custom role permissions.

## Validation on 2026-11-02

- PHP lint passed for all new/modified PHP files.
- Composer PSR-4 autoload class_exists checks passed for all seven new classes. Optimized dump-autoload did not finish and was interrupted; no dependencies were changed.
- route:list resolves GET /admin/playlists to admin.playlists.index.
- Blade view:cache passed; generated view cache was cleared afterward.
- Playlist tests: 11 passed, 52 assertions, using isolated SQLite :memory:.
- Full suite: 31/37 passed, 113 assertions. Six existing tests expect unprefixed dashboard/profile URLs or the dashboard route name. Existing HEAD routes already use admin.dashboard and /admin/profile. No unrelated route/authentication changes were made.
- MySQL connection at 127.0.0.1:3306 (cms_db) refused connections. Migration and both seeder commands were attempted but could not run against the configured database. No existing database rows were reset or removed.
- Visual browser verification against the active database remains pending; HTTP page rendering is covered by the Playlist test.

SQLite extensions exist locally but are disabled in php.ini. Run tests without changing system settings:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Playlists/PlaylistIsolationTest.php
```

After restoring the configured MySQL service, inspect pending migrations, then apply only these four files and seed:

```powershell
php artisan migrate:status
php artisan migrate --path=database/migrations/2026_11_02_000001_create_playlists_table.php --path=database/migrations/2026_11_02_000002_create_playlist_pages_table.php --path=database/migrations/2026_11_02_000003_create_playlist_zones_table.php --path=database/migrations/2026_11_02_000004_create_playlist_zone_items_table.php
php artisan db:seed --class=PlaylistPermissionSeeder
php artisan db:seed --class=MenuSeeder
php artisan permission:cache-reset
```

## Step 2 preparation

Page, zone and ordered media item storage is ready. Step 2 remains unimplemented: pages panel, interactive canvas, properties/files windows, media drag/drop, resize/stretch/aspect ratio, layers and playback preview.

All future page/zone/item endpoints must authorize through the parent Playlist and current workspace. Media assignment must additionally verify MediaAsset.company_id equals Playlist.company_id. Before media item editing is exposed, handle restricted media deletion gracefully in Content Library (the new foreign key prevents deleting referenced media). These are integration requirements for the next stage, not implemented Studio features.

No commit or push was performed.


## Follow-up during Step 2

MySQL became available. All four Step 1 migrations were applied successfully in batch 3, and PlaylistPermissionSeeder/MenuSeeder ran successfully. See playlist-studio-step2.md for current verification results. The earlier connection failure describes the original Step 1 session only.
