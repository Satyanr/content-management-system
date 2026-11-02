# Playlist Studio - Step 4: Timeline, Duration and Playback Preview

Implemented on the local Step 3 source. No migration, dependency addition, commit, push, physical media modification, Scheduler, Device API or publishing work.

## Created files

- app/Services/PlaylistPlayback.php: read-only, company-scoped, eager-loaded playback manifest.
- resources/js/studio-playback.js: shared-clock playback engine, sequence resolution and virtualized timeline segment calculations.
- resources/js/studio-playback-renderer.js: separate preview DOM renderer, metadata probes, async video alignment, autoplay/error handling and resource cleanup.
- resources/js/studio-timeline.js: Alpine adapter, controls, keyboard shortcuts, scrubbing, saved manifest refresh and duration actions.
- resources/views/livewire/playlists/studio/playback-controls.blade.php: Play/Pause/Stop/Restart/Page/Loop/audio controls and status.
- resources/views/livewire/playlists/studio/timeline.blade.php: ruler, Page segments, Window tracks, repeated media blocks, playhead, track collapse and timeline zoom/height.
- resources/views/livewire/playlists/studio/playback-preview.blade.php: viewport preview, optional native fullscreen, timeline and independent media renderer.
- tests/Feature/Playlists/PlaylistPlaybackTest.php: 11 new PHP tests.
- tests/js/studio-playback.test.mjs: 21 new JavaScript tests.
- docs/playlist-studio-step4.md: this handoff.

## Modified files

- app/Services/PlaylistWindowEditor.php: new image items persist 10 seconds, videos persist null Auto, scoped item duration update.
- app/Livewire/Playlists/InteractsWithStudioWindows.php: updateItemDuration, resetItemDurationToAuto, getPlaybackManifest and saved-playback invalidation events.
- app/Livewire/Playlists/PlaylistStudio.php: duration snapshot and invalidation after Playlist/Page mutations.
- app/Http/Controllers/Admin/MediaStreamController.php: existing authenticated stream also serves images; corrected suffix/open-ended Range handling and private/no-store cache policy.
- resources/js/playlist-studio.js: playback lifecycle integration and editor Page timeline alignment.
- resources/js/studio-interactions.js: refresh invalidated saved playback after completed mutations.
- resources/views/livewire/playlists/playlist-studio.blade.php: inline playback overlay, timeline, preview composition and keyboard/event integration.
- resources/views/livewire/playlists/studio/file-window.blade.php: manual duration input/Save and video Auto reset, known/pending duration display.
- resources/views/livewire/playlists/studio/toolbar.blade.php: Playback Preview entry point.
- tests/js/playlist-studio.test.mjs: existing preview test updated for asynchronous saved manifest loading.

Models, routes and the existing duration_seconds column were reused.

## Duration and manifest

Images default to 10 seconds, with editable integer duration 1-86400. New image items store 10; legacy null image items resolve to an effective 10 without rewriting old data. Video null means Auto; manual durations are validated within 1-86400. Auto reset is rejected for images.

The manifest contains ordered Pages, fixed Page durations, geometry/fit/visibility/layers and ordered items with protected source URLs, manual/Auto duration and known intrinsic video duration. Positive finite database duration (or metadata.duration) is used when available. Browser loadedmetadata resolves missing/inaccurate intrinsic duration without writing metadata or media files.

Preview requires playlists.view and a selected active workspace. Sources additionally honor media.view; without it, the manifest supplies unavailable placeholders instead of media URLs/metadata. Every duration update resolves Playlist -> active Page -> Zone -> Item and same-company media, and requires playlists.edit. No arbitrary company_id is accepted. Cross-company media references are sanitized even if malformed data is inserted directly.

Queries are eager-loaded and tested to stay bounded when more Windows/items are added. Preview manifest reads do not persist pending Livewire properties.

## Engine and renderer

One performance.now clock and one requestAnimationFrame loop drive every active Window. Each Window runs sort_order items sequentially, repeats its sequence inside the fixed Page interval, and runs concurrently with other Windows. Pages advance in order; Playlist looping defaults ON and can be disabled.

Play/Pause/Stop/Restart/Previous/Next and global Seek derive Page time and local Window/item time. Pause/Resume preserves position. End stops at the total duration when looping is OFF. Playback speed is fixed at 1x.

Video currentTime follows the shared clock. Manual durations shorter than the source cut off at the item boundary; longer durations wrap intrinsic source time. Rapid seeks keep the latest target, and stale source listeners/promises cannot reset a new source or Page. Page changes pause/unload old video sources and remove their listeners.

The preview renderer owns its DOM inside wire:ignore containers. It never reuses editor video elements, changes editor selection/geometry or saves configuration. An inline overlay renders playback over the canvas while active; Return to editor/Stop reveals the existing editing canvas. The full preview fills the viewport and offers native fullscreen when supported. Closing it pauses playback, releases media/probes/listeners and restores focus without saving drafts.

Page background takes precedence, falling back to Playlist background. Window position, dimensions, fit, z-index and visibility come from the saved manifest. Lock remains an editing restriction and does not suppress playback.

Metadata and media-start loading have an 8-second timeout. Unknown Auto video duration is shown as Loading/Unknown without assigning a made-up length. A Window with unresolved Auto metadata waits for resolution; other Windows and the Page clock continue. Failed unknown Auto items are skipped within that Window; a Window with no usable sequence displays fallback. Known-duration failed items retain their defined interval with a fallback. Codec, image, metadata, audio-policy and network errors do not stop the other Windows.

Autoplay starts only from Play. NotAllowedError exposes Play muted / Enable Audio recovery and does not retry every frame. Preview video elements and metadata probes are cleaned when switching Pages, closing Preview, refreshing the manifest or unmounting.

## Timeline

The bottom panel has a global ruler and Page segments, active-Page Window tracks, proportional item blocks, current-item highlighting and a global playhead. Blocks repeat within a Page and are truncated at its end. Unknown Auto metadata is shown separately rather than as a known-length block.

Click or drag anywhere on the time surface/playhead to seek, including across Pages. Timeline zoom is independent of canvas zoom. Horizontal scrolling and virtualized blocks keep long durations manageable. Timeline height, visibility, all tracks and individual tracks can be adjusted/collapsed.

Duration, reorder and other saved mutations invalidate and refresh the saved manifest. Explicit Refresh saved timeline also reloads it. Preview always uses saved configuration; dirty Playlist/Page/Window drafts remain in the editor. Playback does not make Livewire requests per frame/second.

Keyboard shortcuts outside form controls: Space Play/Pause, Home start, End total, Left/Right one-second seek, Escape closes Preview. Focus is contained in the preview while open.

## Validation on 2026-11-02

Executed successfully:
- PHP lint on six new/modified PHP files.
- Playlist suite: 50 passed, 360 assertions, including all Step 1-3 tests.
- JavaScript suite: 38 passed (17 previous plus 21 playback tests).
- npm.cmd run build.
- php artisan view:cache, followed by view:clear.
- git diff --check.
- migrate:status: all existing migrations applied; Step 4 needs no migration.

Full-suite baseline before Step 4: 59/65 passed with six old Authentication/Profile route failures. Final full suite: 70/76 passed, 421 assertions, with the same six failures. The tests still expect dashboard, /dashboard and /profile instead of existing admin-prefixed routes. No new full-suite failure was observed.

Range HTTP tests verified exact 206 bodies/headers for bytes=2-5, bytes=-3 and bytes=6-, plus 416 for invalid/unsatisfiable ranges. Authenticated image streaming and foreign-company/media-permission denial were also tested.

PHP tests use isolated SQLite in-memory storage. File tests use Storage::fake. No original file or live database data was modified by playback tests.

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Playlists
node --test tests/js/*.test.mjs
npm.cmd run build
php artisan view:cache
php artisan view:clear
git diff --check
```

## Real browser acceptance

No browser automation tool was available. JS tests cover engine timing, fake media DOM/events, metadata timeouts, autoplay rejection, async seeks and adapter cleanup. They do not verify actual browser codecs, audio hardware, decoded frame timing, CSS or Livewire/Alpine DOM morphing in a browser. No screenshot or browser-console success is claimed.

1. Sign in, select a company and open a Playlist with two Pages. Put at least two image/video items in each of two visible Windows. Keep another Window hidden/empty.
2. In File Window, save image duration 3 seconds, set one video to Auto, and another video to manual duration shorter/longer than its intrinsic length. Reload and verify stored labels and proportional blocks. Test 0 and 86401 validation.
3. Open Playback Preview and press Play. Confirm concurrent Windows, ordered/repeated items, automatic Page changes, Page backgrounds and loop ON/OFF. Try Pause/Resume, Stop, Restart, Previous and Next.
4. Click and drag the timeline/playhead into an image, middle of a video and across a Page boundary. Seek rapidly back and forth, then pause. Confirm the latest target remains selected and old metadata/ended/seeked events do not restart a previous source.
5. Test mute/volume, Enable Audio after policy rejection, native fullscreen, keyboard shortcuts and Escape. Confirm input typing and range arrows do not trigger global seek shortcuts.
6. Inspect Network video Range requests (206/Content-Range) and Console while seeking and switching Pages. Test a missing/unsupported/slow media source: the Window must show fallback within the loading timeout while other Windows continue.
7. Close Preview and verify unsaved Playlist/Page/Window fields, geometry, lock, visibility, reorder, drag/resize and canvas settings remain intact. Inspect that preview sources stop loading/playing and listeners do not accumulate.
8. Repeat with a view-only user, a user without media.view, a foreign-company Playlist URL, and super-admin All Companies mode.

## Step 5 preparation

The manifest service, deterministic engine and separate renderer are reusable boundaries for the next stage. Before defining Step 5 features, run the real-browser acceptance matrix with representative media/codecs and long Playlists, resolve the six existing unrelated route-test failures, and decide the intended runtime/player contract and manifest versioning. No scheduling, device endpoints, publishing, transitions or additional playback-speed controls were started here.
