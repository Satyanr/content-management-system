<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Device;
use App\Models\Menu;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleResolver;
use App\Services\WorkspaceService;
use Carbon\CarbonImmutable;
use Database\Seeders\DashboardNavigationSeeder;
use Database\Seeders\MenuSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 03:00:00', 'UTC'));
    foreach (['dashboard.view', 'devices.view', 'playlists.view', 'scheduler.view', 'activity_logs.view', 'users.view', 'companies.view', 'roles.view', 'permissions.view', 'menus.view', 'settings.view', 'media.view', 'login_histories.view'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Dashboard Company A', 'code' => 'DASH-A', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Dashboard Company B', 'code' => 'DASH-B', 'is_active' => true]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['dashboard.view', 'devices.view', 'playlists.view', 'scheduler.view', 'activity_logs.view']);
    $this->actingAs($this->user);
});
afterEach(fn () => CarbonImmutable::setTestNow());

function dashboardDevice($test, string $name, string $status = 'active', ?string $seen = null, bool $credential = true): Device
{
    $device = Device::create(['company_id' => $test->company->id, 'name' => $name, 'platform' => 'windows']);
    $device->forceFill(['status' => $status, 'last_seen_at' => $seen])->save();
    if ($credential) {
        $device->credentials()->create(['token_hash' => hash('sha256', $name), 'token_identifier' => substr(hash('sha256', $name), 0, 32)]);
    }

    return $device;
}
function dashboardSchedule($test, string $name, string $start = '2026-11-02 02:00:00', string $end = '2026-11-02 04:00:00'): Schedule
{
    $playlist = Playlist::create(['company_id' => $test->company->id, 'name' => $name.' Playlist', 'is_active' => true]);

    return Schedule::create(['company_id' => $test->company->id, 'playlist_id' => $playlist->id, 'name' => $name, 'schedule_type' => 'once', 'timezone' => 'Asia/Jakarta', 'starts_at_utc' => $start, 'ends_at_utc' => $end, 'is_active' => true]);
}

test('dashboard counts genuine device connections and uses resolver winner and transition', function () {
    dashboardDevice($this, 'Online', seen: '2026-11-02 02:59:30');
    dashboardDevice($this, 'Stale', seen: '2026-11-02 02:00:00');
    dashboardDevice($this, 'No credential', seen: '2026-11-02 02:59:30', credential: false);
    dashboardDevice($this, 'Pending', 'pending');
    dashboardDevice($this, 'Disabled', 'disabled', '2026-11-02 02:59:30');
    dashboardDevice($this, 'Never');
    dashboardDevice($this, 'Future', seen: '2026-11-02 04:00:00');
    $schedule = dashboardSchedule($this, 'Morning');
    dashboardSchedule($this, 'Upcoming', '2026-11-03 02:00:00', '2026-11-03 04:00:00');
    dashboardSchedule($this, 'Expired', '2026-10-02 02:00:00', '2026-10-02 04:00:00');
    $winner = app(ScheduleResolver::class)->resolve($this->company->id, CarbonImmutable::now('UTC'));
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Dijadwalkan Tayang')->assertViewHas('deviceStats', ['total' => 7, 'online' => 1, 'offline' => 3, 'pending' => 1, 'other' => 2])
        ->assertViewHas('scheduleStats', ['active' => 1, 'upcoming' => 1])->assertViewHas('scheduled', fn ($value) => $value->id === $winner->id && $value->id === $schedule->id)
        ->assertViewHas('transition', fn ($value) => $value->format('H:i') === '04:00');
});

test('regular company dashboard ignores forged workspace and foreign data', function () {
    $schedule = dashboardSchedule($this, 'Own schedule');
    $foreignPlaylist = Playlist::create(['company_id' => $this->other->id, 'name' => 'Secret foreign Playlist']);
    Device::create(['company_id' => $this->other->id, 'name' => 'Secret foreign Device', 'platform' => 'android']);
    ActivityLog::create(['company_id' => $this->other->id, 'action' => 'create', 'description' => 'Secret foreign activity']);
    $this->withSession([WorkspaceService::SESSION_KEY => $this->other->id])->get(route('admin.dashboard'))->assertOk()->assertViewHas('activePlaylists', 1)
        ->assertViewHas('deviceStats', fn ($stats) => $stats['total'] === 0)->assertSee($schedule->name)->assertDontSee('Secret foreign');
});

test('selected super admin is scoped and All Companies only aggregates without global winner', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    dashboardSchedule($this, 'Private A');
    Playlist::create(['company_id' => $this->other->id, 'name' => 'Other Playlist', 'is_active' => true]);
    $this->withSession([WorkspaceService::SESSION_KEY => $this->other->id])->get(route('admin.dashboard'))->assertOk()->assertViewHas('activePlaylists', 1)->assertDontSee('Private A');
    $this->withSession([WorkspaceService::SESSION_KEY => null])->get(route('admin.dashboard'))->assertOk()->assertViewHas('activePlaylists', 2)->assertViewHas('scheduled', null)->assertSee('Ringkasan agregat')->assertDontSee('Private A');
});

test('cards activities and actions are permission gated and empty states are useful', function () {
    $this->user->syncPermissions(['dashboard.view']);
    $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('deviceStats', null)->assertViewHas('activePlaylists', null)->assertViewHas('scheduleStats', null)
        ->assertDontSee('Status Perangkat')->assertDontSee('Aktivitas Terbaru')->assertDontSee('Buka Playlist')->assertSee('belum memiliki akses');
    $this->user->givePermissionTo(['devices.view', 'playlists.view', 'scheduler.view']);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Belum ada jadwal aktif')->assertSee('Belum ada perangkat');
});

test('activity feed is scoped limited to five and not login session metrics', function () {
    foreach (range(1, 7) as $i) {
        ActivityLog::create(['company_id' => $this->company->id, 'action' => 'create', 'description' => 'Activity '.$i]);
    }
    $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('latestActivities', fn ($rows) => $rows->count() === 5)->assertDontSee('Active sessions')->assertDontSee('Riwayat Login Terbaru');
});

test('navigation keeps database custom hierarchy hides invalid routes and enforces route permissions', function () {
    $this->seed(MenuSeeder::class);
    $parent = Menu::create(['title' => 'Menu Kustom', 'is_active' => true, 'sort_order' => 30]);
    Menu::create(['title' => 'Koleksi Saya', 'parent_id' => $parent->id, 'route' => 'admin.playlists.index', 'is_active' => true]);
    Menu::create(['title' => 'Broken Link', 'route' => 'missing.route', 'is_active' => true]);
    Menu::create(['title' => 'Bypass Users', 'route' => 'admin.users.index', 'is_active' => true]);
    Menu::create(['title' => 'Requires Parameter', 'route' => 'admin.devices.show', 'is_active' => true]);
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('Menu Kustom')->assertSee('Koleksi Saya')->assertSee('Operasional')
        ->assertDontSee('Broken Link')->assertDontSee('Bypass Users')->assertDontSee('Requires Parameter')->assertDontSee('Monitoring');
    expect(Menu::where('title', 'Koleksi Saya')->value('parent_id'))->toBe($parent->id);
    $this->get(route('admin.playlists.index'))->assertOk()->assertSee('aria-current="page"', false);
});

test('navigation seeder preserves renamed disabled relocated database menus', function () {
    $this->seed(MenuSeeder::class);
    $entry = Menu::where('route', 'admin.activity-logs.index')->sole();
    $entry->update(['title' => 'Audit Kustom', 'parent_id' => null, 'is_active' => false]);
    $this->seed(DashboardNavigationSeeder::class);
    expect($entry->fresh()->title)->toBe('Audit Kustom')->and($entry->fresh()->is_active)->toBeFalse();
    expect(Menu::where('route', 'admin.activity-logs.index')->count())->toBe(1);
});

test('account profile stays in the admin layout with authenticated forms', function () {
    $this->get(route('admin.profile'))->assertOk()->assertSee('Profil saya')->assertSeeLivewire('profile.update-profile-information-form')->assertSeeLivewire('profile.update-password-form');
});
