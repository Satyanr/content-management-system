# Playlist Studio - Step 3: Interactive Canvas Editor

Local implementation on the existing Step 1/2 architecture. No new package, database migration, commit, push, or live media file changes.

## Files created

- app/Services/PlaylistWindowEditor.php: validated transactional Window/item operations, active-company access, parent locking, aspect ratio, bounds, lock enforcement and layer ordering.
- app/Livewire/Playlists/InteractsWithStudioWindows.php: Studio actions, active Page checks, canvas shrink confirmation and canvas handle persistence.
- resources/js/studio-geometry.js: output-coordinate conversion, movement, eight resize directions, ratio/grid/edge snapping and retry tokens.
- resources/js/studio-interactions.js: gesture lifecycle, media drop, Window Properties, item actions, busy/failure handling and Livewire state synchronization.
- resources/views/livewire/playlists/studio/window-properties.blade.php: numeric Window geometry, fit/aspect/lock/visibility and layer controls.
- tests/Feature/Playlists/PlaylistInteractiveTest.php: 15 new PHP tests.
- tests/js/studio-interactive.test.mjs: 13 new JS tests.
- docs/playlist-studio-step3.md: this handoff.

## Files modified

- app/Livewire/Playlists/PlaylistStudio.php: trait integration, scoped media eager loading, safe editor snapshots, canvas bounds checks on normal saves.
- resources/js/playlist-studio.js: initialize/destroy interactions, prevent zoom/fit changes during gestures, safe Page transitions.
- resources/views/livewire/playlists/playlist-studio.blade.php: drag surface, canvas handles, sync events, geometry feedback and persistent out-of-bounds warning.
- resources/views/livewire/playlists/studio/windows.blade.php: image/video rendering, selection, eight handles, native pixel geometry and stable keyed video elements.
- resources/views/livewire/playlists/studio/library.blade.php: draggable media and Add to selected Window.
- resources/views/livewire/playlists/studio/file-window.blade.php: Window selection, property links, item lists, reorder/remove/add and layer/delete controls.
- resources/views/livewire/playlists/studio/properties.blade.php: Window panel integration and confirmed canvas saves.
- resources/views/livewire/playlists/studio/toolbar.blade.php: Save All, snapping/grid and unsaved Window protection.
- resources/views/livewire/playlists/studio/pages.blade.php: block switching during interactions, protect drafts, retain last-Page protection.
- app/Livewire/Media/MediaTable.php: reject referenced-asset deletion with a friendly message before physical deletion; retain restrictive foreign-key protection for races.

Models, routes, permissions and existing schema were reused.

## Implemented behavior

- Drag image/video from Content Library; a ghost shows the drop location. Drop creates a Zone plus its first ZoneItem atomically, selects it and opens Properties.
- Native HTML drag/drop and Pointer Events; no interaction dependency was added.
- Drop coordinates and every pointer delta are converted by the editor scale. Zoom and Fit do not change output geometry.
- Drag and eight resize handles update only local UI during movement. Exactly one persistence action is sent on completion. Pointer cancellation/blur rolls back local geometry and removes listeners.
- Window geometry is server validated as integer, positive size, nonnegative position and inside the saved canvas. Locked geometry cannot be changed, including a forged request combining unlock and movement.
- Keep Aspect Ratio preserves the stored Window ratio, with rounding tolerance. Turn it off for free sizing.
- Contain/cover/stretch map to image/video object-fit: contain/cover/fill. Source files are never transformed.
- Numeric Properties, drag and resize synchronize with stored geometry. Save Window persists Window fields; the top Save/Ctrl-S first saves pending Window fields and then Playlist/Page fields. Separate saves use separate transactions.
- Snap to canvas edges and optional grid size are local editor options.
- Layer actions normalize a deterministic back-to-front order within the active Page. Direct numeric z-index is also supported.
- File Window lists media in sort_order and supports move earlier/later, remove item, add media, rename via Properties and confirmed Window deletion. Removing an item/Window keeps source media.
- Canvas right/bottom/corner handles preview output dimensions and save on release. Numeric canvas changes use existing Save workflow.
- Shrinking any Playlist canvas requires confirmation if Windows on any Page would be outside. Their coordinates and sizes are kept; the editor shows a warning. Enlarge the canvas or correct numeric geometry to recover those Windows.
- Local busy guards prevent repeated drops; persisted UUID request IDs in first-item settings make successful request retries idempotent. No cache/table dependency was needed.
- Every mutation checks playlists.edit, active company, Playlist and active Page. Zone and item IDs resolve through their parent relationships. Media selection additionally checks media.view and same-company image/video records. All Companies access remains forbidden.
- Video nodes are keyed by Zone/item identity and marked wire:ignore. Fit changes remain reactive through Alpine, avoiding unnecessary reconstruction on unrelated updates.
- Window drafts survive unrelated state snapshots; Page changes cancel gesture listeners and reset selection. HTTP failures use a component-scoped Livewire commit hook to release pending state even though Livewire action promises do not reject automatically.

## Validation

- No schema changes required. MySQL migrate:status shows all existing migrations applied.
- PHP lint: all five new/changed PHP files passed.
- Playlist PHP suite: 39 passed, 265 assertions (includes all Step 1/2 tests).
- JS Studio suite: 17 passed (four existing plus thirteen new), including zoom 12/25/50/100/200%, all eight resize handles, ratio/bounds, snapping, single end-of-gesture request, cancellation, simulated Livewire snapshots, duplicate drops and HTTP failure cleanup.
- npm.cmd run build: passed.
- php artisan view:cache: passed; generated view cache cleared afterward.
- git diff --check: passed.
- Full-suite baseline before changes: 44/50 passed, six failures in old Authentication/Profile route tests. Final full suite: 59/65 passed, 326 assertions, with exactly those same six failures. These tests still refer to dashboard, /dashboard and /profile rather than the existing admin-prefixed routes; no new full-suite failure was observed.

Reproduce:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Playlists
node --test tests/js/*.test.mjs
npm.cmd run build
php artisan view:cache
php artisan view:clear
git diff --check
```

PHP tests use isolated SQLite memory storage, not the configured live MySQL database. File immutability/deletion tests use Storage::fake.

## Browser verification and limitations

No browser automation tool was available in this session. JS state/geometry tests and PHP/Livewire rendering passed, but an actual browser Console and DOM morph/video identity still need visual acceptance. Simulated re-render tests are not a browser end-to-end guarantee.

1. Sign in, select a company, open /admin/playlists and Open Studio.
2. Drag an image/video onto the canvas. Check the ghost, selected Window, item list and persisted placement after reload.
3. At 25%, 50%, 100%, 200% and Fit below 25%, move/resize the same Window. Watch original X/Y/size in the footer and verify Network sends one mutation per completed gesture, not one per move.
4. Try all eight handles. With Keep Aspect Ratio ON, ratio should stay fixed; turn it OFF and save before free resizing. Save pending Properties before dragging; a dirty Window draft is protected until saved/discarded.
5. Compare Contain, Cover and Stretch on image and video Windows. The source file remains unchanged. Basic video controls are available in layout preview; sequencing is deferred.
6. Lock and save; geometry inputs/handles must be disabled and dragging rejected. Unlock/save separately before moving.
7. Use Add media on a selected Window; reorder/remove items in File Window. The canvas shows the first media only. Change layers and delete a Window with confirmation.
8. Resize the canvas from its right/bottom/corner handles or numeric Properties. Confirm or cancel the shrink warning and verify Window geometry is retained. Check the warning for out-of-bounds Windows.
9. Change Pages and search/filter media repeatedly. Test cancellation by switching focus during a gesture. Inspect Console and Network after Livewire updates, and simulate an offline request to check failure recovery.

Canvas bounds are strict for new or changed Window geometry. Windows intentionally outside a confirmed smaller canvas remain editable through numeric correction or canvas enlargement. Playback sequencing and timeline behavior are outside Step 3.

## Step 4 recommendation

Build playback from ordered ZoneItems, with media/image duration, video-end behavior and a Page timeline. Add coordinated multi-Window playback, explicit preview play/pause/seek and error/empty-media handling. Preserve geometry and authorization from this editor; keep playback scheduling separate from the editing request lifecycle. Scheduler, devices, publishing and Player API were not implemented here.
