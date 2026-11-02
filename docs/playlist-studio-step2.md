# Playlist Studio - Step 2

Implemented locally from the supplied Step 2 attachment and screenshot. No commit or push.

## Files

Created:
- app/Services/PlaylistWorkspace.php: shared active-company access checks for route and every Studio request.
- app/Livewire/Playlists/PlaylistStudio.php: data, atomic property saves, page selection/add/delete and company-scoped media search/filter/pagination.
- resources/views/admin/playlists/studio.blade.php: standalone editor shell.
- resources/views/livewire/playlists/playlist-studio.blade.php: editor composition and layout preview.
- resources/views/livewire/playlists/studio/{toolbar,pages,windows,empty-canvas,file-window,library,properties}.blade.php: focused editor panels.
- resources/views/components/studio-icon.blade.php: consistent inline SVG icons.
- resources/js/playlist-studio.js: local Alpine state, zoom, fit, preview, panel controls and unsaved-property prompts.
- tests/Feature/Playlists/PlaylistStudioTest.php: 13 Studio tests.
- tests/js/playlist-studio.test.mjs: four geometry/UI-state tests with Node built-ins.
- docs/playlist-studio-step2.md: this handoff.

Modified:
- routes/web.php: GET /admin/playlists/{playlist}/studio, admin.playlists.studio, under existing authenticated admin middleware and playlists.view permission.
- app/Http/Controllers/Admin/PlaylistController.php: authorize scoped Studio access before rendering.
- resources/views/livewire/playlists/playlist-table.blade.php: Open Studio links.
- resources/js/app.js: load Studio Alpine module through existing Vite entrypoint.
- resources/css/app.css: scoped editor controls, workspace background, panel constraints and x-cloak.

Existing uncommitted Step 1 files were retained. No new dependencies or Step 2 schema migrations were needed.

## Behavior

- Desktop editor with editable Playlist name, back link, Save, layout preview, zoom and panel settings.
- Pages panel defaults to 220px; right panel defaults to 300px. Panel widths can be adjusted using Panels sliders and panels can be hidden.
- Page selection uses Livewire without a full reload. Page creation is immediate. Rename/duration and Playlist canvas properties persist with Save or Save Properties (Ctrl/Cmd+S also saves for editors).
- Page delete asks for confirmation. Server-side parent locks serialize add/delete and enforce at least one remaining page.
- Output canvas preserves stored pixel dimensions and ratio. Manual zoom is 25-200%; Fit may fall below 25% to show large/ultrawide output within the viewport. Zoom never modifies output or zone coordinates.
- Canvas resolution/color are applied after saving; validation failures retain unsaved values and show errors.
- Canvas and layout preview render stored visible windows or an empty state. Preview shows saved geometry and does not play media.
- File Window lists stored coordinates, dimensions, layers, fit and visibility/lock flags. No window mutations are exposed.
- Content Library shows only active-company image/video records, with search, type filter and pagination. media.view is required in addition to playlists.view.
- All mutations require playlists.edit. Playlist/Page identity is locked in Livewire. Every read/mutation resolves Playlist through the active company; Page IDs must belong to that Playlist. All Companies and unavailable/inactive companies are rejected.
- Saves update Playlist and active Page in a transaction; a stale/deleted Page cannot cause a partial Playlist save.

## Migration and checks (2026-11-02)

MySQL was available during Step 2. All four previously pending Step 1 migrations ran successfully in batch 3. PlaylistPermissionSeeder and MenuSeeder ran successfully. migrate:status shows all migrations applied. No existing rows were reset/deleted.

Executed:
- PHP lint on new/changed backend files: passed.
- Autoload checks: passed.
- Blade view:cache: passed; generated view cache cleared afterward.
- route:list: both Playlist list and Studio routes registered.
- npm.cmd run build: passed. npm.ps1 is blocked by local PowerShell policy, so npm.cmd was used without changing policy.
- Playlist PHP tests: 24 passed, 138 assertions, including the 11 Step 1 tests.
- Node geometry/UI-state tests: 4 passed.
- Full PHP suite: 44/50 passed, 199 assertions. The same six pre-existing Auth/Profile test failures remain: tests reference dashboard, /dashboard and /profile while the project uses admin.dashboard, /admin/dashboard and /admin/profile. Unrelated authentication routes were preserved.
- git diff --check: passed.

Reproduce isolated PHP/JS tests:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Playlists
node --test tests/js/playlist-studio.test.mjs
npm.cmd run build
```

SQLite extensions are enabled only for the test process; php.ini and live database configuration were not changed.

## Local visual acceptance

No browser automation or screenshot tool was available in this session. HTTP/Livewire render and geometry tests passed, but actual browser layout and JavaScript Console errors have not been verified. No browser screenshot is claimed.

1. Start the local application as usual (or php artisan serve), sign in, and select a company workspace.
2. Open /admin/playlists, create/select a Playlist, then choose Open Studio.
3. At 1366x768, verify Pages / canvas / three right-panel tabs. Use Panels to adjust/hide each side.
4. In Properties save 1920x1080, 1080x1920, 1920x384 and 4864x512 in turn; check ratio, background and saved dimensions after reopening.
5. Use zoom in/out and Fit; scroll enlarged canvases. Toggle panels to check Fit recomputation.
6. Add a page, switch pages, rename/change duration and Save; reload to verify persistence. Delete a page and verify the last page is protected.
7. Preview Layout, close with Escape, check focus restoration. Inspect the browser Console for JavaScript errors and the network panel for failed Livewire requests.
8. Confirm company-specific library results and view-only controls with the corresponding accounts. Foreign Playlist URLs and Page IDs are covered by automated tests.

## Step 3 preparation

The canvas exposes data-zone-id and original data-x/data-y/data-width/data-height in output pixels. Alpine zoom is independent of persisted geometry. Future pointer operations must convert editor coordinates by the current scale, while server mutations must resolve each Zone through the current Page/Playlist and verify media company ownership.

Drag/drop, resize, aspect-ratio manipulation, snapping, playback, scheduling and device publishing were intentionally not implemented. Before attaching media items, Content Library deletion should display a friendly error for referenced assets; the existing restrictive foreign key already prevents deleting them.
