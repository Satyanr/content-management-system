# Scheduler Foundation - Step 5

Implemented on the latest local Playlist Studio/Playback source. No Device Management, Player API, publishing, new dependencies, commit or push.

## Files created

- database/migrations/2026_11_02_000005_create_schedules_table.php
- app/Models/Schedule.php
- app/Casts/UtcDateTime.php
- app/Services/ScheduleClock.php
- app/Services/ScheduleResolver.php
- app/Services/ScheduleConflicts.php
- app/Services/ScheduleManager.php
- app/Livewire/Scheduler/ScheduleTable.php
- app/Http/Controllers/Admin/SchedulerController.php
- database/seeders/SchedulerPermissionSeeder.php
- resources/views/admin/scheduler/index.blade.php
- resources/views/livewire/scheduler/{schedule-table,list,weekly,conflicts,form}.blade.php
- tests/Feature/Scheduler/ScheduleTest.php
- docs/scheduler-step5.md

## Files modified

- routes/web.php: authenticated admin.scheduler.index at GET /admin/scheduler, with scheduler.view.
- database/seeders/MenuSeeder.php: existing Scheduler menu links to the new route and permission.
- database/seeders/DatabaseSeeder.php: calls the additive SchedulerPermissionSeeder.
- app/Models/{Company,Playlist}.php: schedules relationships.
- app/Livewire/Playlists/PlaylistTable.php: friendly reference checks and foreign-key race handling when deleting a scheduled Playlist.
- app/Livewire/Companies/CompanyTable.php: friendly guard before deleting a Company whose Schedules would restrict its cascading Playlist deletion.

Existing Playlist models, Studio, manifest and playback engine were retained. Scheduler never changes Pages, Zones, items or Playlist duration.

## Database

The new schedules table contains:

- id, company_id, playlist_id, nullable created_by
- name (150), schedule_type (once/weekly), timezone (IANA, 100)
- nullable starts_at_utc / ends_at_utc (DATETIME)
- nullable start_date / end_date (DATE), start_time / end_time (TIME), weekdays (JSON ISO integers 1-7)
- signed priority, is_active, created_at / updated_at

Foreign keys: Company cascades Schedule deletion; Playlist deletion is restricted; deleting the creator sets created_by to null. UI guards explain references before attempting deletion. No existing schema/data was dropped.

Indexes cover company/active/type, company/once UTC range and company/weekly date range. The application validates same-company Playlist ownership; scoped Schedule reads also require the related Playlist to have the same company, guarding against corrupted direct database references.

Only fields relevant to the selected type are persisted; switching type clears irrelevant columns. Custom UTC casts keep Once storage/reads UTC even when app.timezone differs.

## Recurrence and resolver rules

Contract: ScheduleResolver::resolve(int $companyId, CarbonImmutable $atUtc): ?Schedule.

The resolver requires no session, Auth facade or HTTP request. The internal caller supplies a trusted company ID. UTC inputs are normalized to UTC. Only active Companies, active Schedules and active same-company Playlists are eligible. No match returns null; there is no implicit fallback Playlist.

All intervals are half-open [start, end): adjacent schedules touching at an endpoint do not conflict. Priority sorts descending; equal priority uses the lowest Schedule ID. This is independent of query timing or timestamp precision.

Weekly rules:

- ISO weekdays refer to the local start day in the stored Schedule timezone.
- start_date/end_date include eligible start days. end_date is optional.
- End time earlier than start time means the following local calendar day. Equal times are rejected, not interpreted as a 24-hour Schedule.
- An overnight occurrence starting on end_date is allowed to finish the next day.
- The resolver uses the same occurrence builder for direct lookup, conflict checks, calendar blocks and upcoming preview.

DST rules:

- Wall-time conversion enumerates actual timezone offsets and round-trips local time; it does not accept PHP's silent normalization of nonexistent wall times.
- Once endpoints in a clock gap are rejected.
- Weekly occurrences with either endpoint in a gap are skipped.
- In a repeated hour, the start uses the earliest UTC occurrence and the end uses the latest. An occurrence can therefore span both folds.
- Boundaries and durations remain actual UTC instants. Rules use the installed PHP timezone database.

The form accepts minute-precision local times. Storage/resolution supports seconds. Supported stored start/end dates are within the MySQL calendar range, before 9999-12-30 to leave room for overnight calculations.

## Timezone settings

New forms default to SettingService timezone for the current Company, with Asia/Jakarta fallback when missing/invalid. SettingService's existing company/global lookup behavior is preserved. Timezones are validated against PHP's IANA identifier list, including valid aliases.

The selected timezone is stored on each Schedule. Changing company Settings does not reinterpret old rules or Once UTC values. The calendar and upcoming panel use the current Company timezone for a shared display axis; details/form retain the Schedule's own timezone. Repeated-hour Once details/upcoming rows include the UTC offset while the main UI remains local-time based.

## Conflicts

Create/Edit computes overlaps with other eligible Schedules in the same Company. A warning lists the competing Schedule and Playlist, each priority, an example overlapping instant and the winner of that pair. The first Save with conflicts asks for Save despite conflicts; saving never deletes or disables competitors. Enable also reports conflicts after updating status.

Conflict detection examines the intersecting stored validity periods rather than just the visible week or upcoming seven days. It samples complete weekly patterns between timezone transitions and transition neighborhoods, caching equivalent patterns away from validity boundaries. Unbounded weekly schedules use the supported calendar horizon instead of expanding millions of daily occurrences. Seasonal overlaps and fold-only overlaps are explicitly tested.

No per-second cron or background scheduler is created. Eligibility is calculated on demand from UTC time.

## UI

/admin/scheduler provides:

- List and Weekly views using existing CMS controls.
- Create/Edit/Delete with confirmation, Enable/Disable, search and Playlist/status/type filters.
- Active Playlists for new scheduling; an existing inactive target is labeled and must be replaced before saving/enabling.
- Seven-column weekly navigation, stable per-Playlist colors, local-time blocks, overnight continuation, muted inactive blocks and active-overlap indicators. Clicking a block opens details; edit remains permission protected.
- Filtered List/Weekly results. Overlap indicators still consider competing schedules hidden by filters.
- What's Playing at Time? resolves local company time through the real resolver. A repeated-hour lookup uses its first occurrence; nonexistent times are rejected.
- Upcoming 7 Days displays merged winner intervals and unscheduled gaps from ScheduleResolver::timeline, which reuses the same choose/activeAt logic. It intentionally shows company-wide winners regardless of list filters, with progressive Show more.

All Companies mode hides schedules and disables mutation by server authorization. Component identity and form identity are locked. Workspace changes invalidate existing component/forms and require reloading; inactive workspaces reject operations. View-only users can inspect but cannot create/edit/delete/toggle.

## Examples (Asia/Jakarta)

1. Lobby: Weekly Monday-Friday, 2026-11-02 onward, 09:00-18:00, priority 0.
2. Promotion: Once 2026-11-02 12:00-13:00, priority 10. It wins over Lobby during that hour, after which Lobby resumes.
3. Night banner: Weekly Friday, 22:00-02:00, ending on 2026-11-06. Its final occurrence begins Friday 6 November and finishes Saturday 7 November at 02:00.

In America/New_York on 2026-11-01, a 01:10-01:40 occurrence resolves to 05:10-06:40 UTC under the fold rule. A weekly 02:30 endpoint on the 2026-03-08 gap is skipped; a Once form using it is rejected.

## Executed validation (2026-11-02)

- Before changes: all 50 Playlist tests passed, 360 assertions; all 38 JavaScript tests passed.
- Scheduler tests: 26 passed, 130 assertions.
- Combined Scheduler + Playlist suites: 76 passed, 490 assertions.
- JavaScript Studio/Playback suite: 38 passed; playback sources were unchanged.
- PHP lint passed on all 18 new/modified PHP files.
- The schedules migration was applied successfully to configured MySQL in batch 4. SchedulerPermissionSeeder and MenuSeeder ran successfully. Existing data was not reset.
- route:list resolves GET /admin/scheduler to admin.scheduler.index.
- npm.cmd run build passed.
- php artisan view:cache passed; generated view cache cleared afterward.
- git diff --check passed.

PHP automated tests use isolated SQLite :memory: and do not reset live MySQL. Tests cover Once boundaries, weekly days, overnight/end dates, priority/ties, inactive states, DST gap/fold, UTC casts under another app timezone, seasonal/unbounded conflicts, CRUD, filters/views/lookup, ownership/forged IDs, All Companies, workspace switching, permission restrictions, timezone snapshots and reference protection.

Reproduce:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest tests/Feature/Scheduler tests/Feature/Playlists
node --test tests/js/*.test.mjs
npm.cmd run build
php artisan view:cache
php artisan view:clear
git diff --check
```

Full PHP suite: 96/102 passed, 551 assertions. The same six existing Authentication/Profile test failures from Step 4 remain (two 404 assertions and four missing dashboard route errors). They refer to dashboard, /dashboard and /profile rather than the established admin-prefixed routes; these routes/features were not rewritten for Scheduler. No new full-suite failure was observed.

## Browser acceptance and outstanding limits

No browser automation tool was available, so actual Scheduler rendering, Alpine interactions and a real-codec playback smoke test could not be verified. HTTP/Livewire rendering and regression tests passed; no browser screenshot or Console success is claimed.

1. Sign in and select a Company. Open /admin/scheduler from the sidebar.
2. Create an active Playlist if needed. Add Lobby and Promotion using the examples. Check the conflict warning/priorities and Save despite conflicts; confirm both rows remain.
3. Toggle List/Weekly, filters and week navigation. Check colored blocks, overlap borders, inactive styling and click-to-detail/edit.
4. Resolve 2026-11-02 12:30 in Asia/Jakarta: Promotion should win. At 13:00 it should return Lobby. Verify upcoming segments show the same transition.
5. Add the Friday overnight example and inspect Friday/Saturday blocks, including the end-date carry. Check New York DST gap/fold examples with appropriate Company/Schedule timezones.
6. Edit/disable/delete schedules and reload. Try deleting a referenced Playlist: it must be retained with a clear message.
7. Repeat with a view-only account, another Company, and super-admin All Companies. Switch workspace in another tab before saving an open form; the stale form must be rejected.
8. Open Playlist Studio Playback Preview, Play/Pause, scrub across Pages and inspect Console/Network. This manual smoke test remains necessary for actual codecs/audio and DOM morphing.

No Device Management, assignment, device authentication, Player endpoint or publishing is implemented in this step.

## Proposed read-only Player contract (not an endpoint)

The future device layer must authenticate a registered Device and derive company/device scope from that credential. It must not accept an arbitrary company_id from a public request or rely on an admin session. ScheduleResolver can then be called internally with that trusted company ID and server UTC time.

Illustrative payload fields:

```json
{
  "schemaVersion": 1,
  "scope": { "companyId": 7, "deviceId": "authenticated-device-id" },
  "serverTimeUtc": "2026-11-02T05:15:00Z",
  "schedule": { "id": 42, "timezone": "Asia/Jakarta", "priority": 10 },
  "playlist": { "id": 8, "revision": "content-hash" },
  "revision": "schedule-and-content-hash",
  "nextTransitionUtc": "2026-11-02T06:00:00Z"
}
```

No eligible Schedule should return schedule/playlist null with explicit no-schedule state. The next transition must account for competing winners, not just the current Schedule end. Revisions should hash canonical relevant Schedule fields plus a content revision covering Pages/Zones/items/media, rather than relying only on second-resolution updated_at.

The future device media/manifest delivery layer needs its own device-scoped authorization. Do not expose the admin Studio manifest or its session-authorized media URLs as a public Player API.

## Step 6 - Device Management preparation

Next scope can define Device identity/registration, company ownership, credentials, revocation, capabilities and health reporting. Decide assignment/default behavior before connecting devices to Scheduler. Reuse the read-only resolver and revision contract, add device-isolation tests, and complete the real-browser/playback acceptance matrix first. No Step 6 implementation was started.
