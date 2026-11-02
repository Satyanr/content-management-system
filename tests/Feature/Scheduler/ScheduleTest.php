<?php

use App\Livewire\Companies\CompanyTable;
use App\Livewire\Playlists\PlaylistTable;
use App\Livewire\Scheduler\ScheduleTable;
use App\Models\Company;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use App\Services\ScheduleClock;
use App\Services\ScheduleConflicts;
use App\Services\ScheduleManager;
use App\Services\ScheduleResolver;
use App\Services\WorkspaceService;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Database\Seeders\SchedulerPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 02:00:00', 'UTC'));
    foreach (['scheduler.view', 'scheduler.create', 'scheduler.edit', 'scheduler.delete', 'dashboard.view', 'playlists.view', 'playlists.delete'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Scheduler A', 'code' => 'SCHA', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Scheduler B', 'code' => 'SCHB', 'is_active' => true]);
    $this->playlist = Playlist::create(['company_id' => $this->company->id, 'name' => 'Lobby A']);
    $this->foreignPlaylist = Playlist::create(['company_id' => $this->other->id, 'name' => 'Private B']);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['scheduler.view', 'scheduler.create', 'scheduler.edit', 'scheduler.delete', 'dashboard.view', 'playlists.view', 'playlists.delete']);
    $this->actingAs($this->user);
});
afterEach(fn () => CarbonImmutable::setTestNow());

function weeklySchedule($test, array $data = []): Schedule
{
    return Schedule::create(array_replace(['company_id' => $test->company->id, 'playlist_id' => $test->playlist->id, 'name' => 'Weekly Lobby', 'schedule_type' => 'weekly', 'timezone' => 'Asia/Jakarta', 'start_date' => '2026-11-02', 'end_date' => '2026-11-30', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'weekdays' => [1, 3, 5], 'priority' => 0, 'is_active' => true], $data));
}
function onceSchedule($test, array $data = []): Schedule
{
    return Schedule::create(array_replace(['company_id' => $test->company->id, 'playlist_id' => $test->playlist->id, 'name' => 'Once Lobby', 'schedule_type' => 'once', 'timezone' => 'Asia/Jakarta', 'starts_at_utc' => '2026-11-02 02:00:00', 'ends_at_utc' => '2026-11-02 03:00:00', 'priority' => 0, 'is_active' => true], $data));
}
function schedulerForm($test, array $data = []): array
{
    return array_replace(['name' => 'Lunch Promotion', 'playlist_id' => $test->playlist->id, 'schedule_type' => 'weekly', 'timezone' => 'Asia/Jakarta', 'start_date' => '2026-11-02', 'end_date' => '2026-11-09', 'start_time' => '12:00', 'end_time' => '13:00', 'weekdays' => [1, 2, 3, 4, 5], 'priority' => 3, 'is_active' => true, 'once_start' => '2026-11-02T12:00', 'once_end' => '2026-11-02T13:00'], $data);
}
function resolverAt($test, string $time): ?Schedule
{
    return app(ScheduleResolver::class)->resolve($test->company->id, CarbonImmutable::parse($time, 'UTC'));
}

test('once intervals are UTC half-open and resolver works without a session', function () {
    $s = onceSchedule($this);
    auth()->logout();
    expect(resolverAt($this, '2026-11-02 01:59:59'))->toBeNull();
    expect(resolverAt($this, '2026-11-02 02:00:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-02 02:59:59')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-02 03:00:00'))->toBeNull();
});

test('weekly uses ISO local weekdays rather than UTC weekday', function () {
    $s = weeklySchedule($this, ['weekdays' => [1], 'start_time' => '00:30:00', 'end_time' => '01:30:00']);
    expect(resolverAt($this, '2026-11-01 17:30:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-02 17:30:00'))->toBeNull();
    expect(resolverAt($this, '2026-11-01 18:30:00'))->toBeNull();
});

test('overnight extends past inclusive end_date but cannot start after it', function () {
    $s = weeklySchedule($this, ['start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'weekdays' => [1], 'start_time' => '22:00:00', 'end_time' => '02:00:00']);
    expect(resolverAt($this, '2026-11-02 14:59:59'))->toBeNull();
    expect(resolverAt($this, '2026-11-02 15:00:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-02 18:00:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-02 19:00:00'))->toBeNull();
    expect(resolverAt($this, '2026-11-09 15:00:00'))->toBeNull();
});

test('start and end dates include their selected start day', function () {
    $s = weeklySchedule($this, ['weekdays' => [1], 'end_date' => '2026-11-09']);
    expect(resolverAt($this, '2026-10-26 02:30:00'))->toBeNull();
    expect(resolverAt($this, '2026-11-02 02:30:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-09 02:30:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-16 02:30:00'))->toBeNull();
});

test('priority and stable lowest ID tie break ignore inactive schedules and Playlists', function () {
    $first = onceSchedule($this);
    $second = onceSchedule($this, ['name' => 'Second']);
    expect(resolverAt($this, '2026-11-02 02:30:00')->id)->toBe($first->id);
    $second->update(['priority' => 10]);
    expect(resolverAt($this, '2026-11-02 02:30:00')->id)->toBe($second->id);
    $second->update(['is_active' => false]);
    expect(resolverAt($this, '2026-11-02 02:30:00')->id)->toBe($first->id);
    $this->playlist->update(['is_active' => false]);
    expect(resolverAt($this, '2026-11-02 02:30:00'))->toBeNull();
});

test('DST gaps skip weekly occurrences and reject invalid once wall times', function () {
    $s = weeklySchedule($this, ['timezone' => 'America/New_York', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'weekdays' => [7], 'start_time' => '02:30:00', 'end_time' => '03:30:00']);
    expect(resolverAt($this, '2026-03-08 07:15:00'))->toBeNull();
    expect(resolverAt($this, '2026-03-15 06:45:00')->id)->toBe($s->id);
    $input = schedulerForm($this, ['schedule_type' => 'once', 'timezone' => 'America/New_York', 'once_start' => '2026-03-08T02:30', 'once_end' => '2026-03-08T03:30']);
    expect(fn () => app(ScheduleManager::class)->prepare($this->company->id, $input))->toThrow(ValidationException::class);
});

test('DST repeated hours select first start and last end deterministically', function () {
    $s = weeklySchedule($this, ['timezone' => 'America/New_York', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'weekdays' => [7], 'start_time' => '01:10:00', 'end_time' => '01:40:00']);
    $interval = app(ScheduleClock::class)->interval($s, '2026-11-01');
    expect($interval['start']->format('Y-m-d H:i:s'))->toBe('2026-11-01 05:10:00');
    expect($interval['end']->format('Y-m-d H:i:s'))->toBe('2026-11-01 06:40:00');
    expect(resolverAt($this, '2026-11-01 06:20:00')->id)->toBe($s->id);
    expect(resolverAt($this, '2026-11-01 06:40:00'))->toBeNull();
});

test('once conversion and casts stay UTC even when application timezone differs', function () {
    config(['app.timezone' => 'Asia/Tokyo']);
    $input = schedulerForm($this, ['schedule_type' => 'once', 'once_start' => '2026-11-02T09:00', 'once_end' => '2026-11-02T10:00']);
    $s = app(ScheduleManager::class)->save($this->company->id, null, $input, $this->user->id);
    expect($s->fresh()->starts_at_utc->format('Y-m-d H:i:s P'))->toBe('2026-11-02 02:00:00 +00:00');
    expect($s->start_date)->toBeNull();
    expect($s->weekdays)->toBeNull();
});

test('CRUD, enable disable and type changes preserve Playlist data', function () {
    $attributes = $this->playlist->fresh()->getAttributes();
    $component = Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this))->call('save')->assertHasNoErrors()->assertReturned(true);
    $s = Schedule::sole();
    expect($s->created_by)->toBe($this->user->id);
    $component->call('edit', $s->id)->set('form.name', 'Updated')->set('form.schedule_type', 'once')->set('form.once_start', '2026-11-03T12:00')->set('form.once_end', '2026-11-03T13:00')->call('save')->assertHasNoErrors();
    expect($s->fresh()->schedule_type)->toBe('once');
    expect($s->fresh()->weekdays)->toBeNull();
    $component->call('toggleActive', $s->id)->assertHasNoErrors();
    expect($s->fresh()->is_active)->toBeFalse();
    $component->call('toggleActive', $s->id);
    expect($s->fresh()->is_active)->toBeTrue();
    $component->call('delete', $s->id);
    $this->assertDatabaseMissing('schedules', ['id' => $s->id]);
    expect($this->playlist->fresh()->getAttributes())->toBe($attributes);
});

test('conflicts show priority winner and require the UI save-anyway action', function () {
    $existing = weeklySchedule($this, ['priority' => 9, 'start_time' => '12:00:00', 'end_time' => '14:00:00']);
    $component = Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this))->call('save')->assertReturned(false)->assertSee('Schedule conflicts detected')->assertSee('The competing Schedule wins this pair.');
    $this->assertDatabaseCount('schedules', 1);
    $component->call('save', true)->assertHasNoErrors()->assertReturned(true);
    $this->assertDatabaseCount('schedules', 2);
    expect(resolverAt($this, '2026-11-02 05:30:00')->id)->toBe($existing->id);
});

test('conflicts handle once versus weekly overnight, cross-timezone and touching boundaries', function () {
    $s = weeklySchedule($this, ['start_time' => '22:00:00', 'end_time' => '02:00:00', 'weekdays' => [1]]);
    $other = onceSchedule($this, ['starts_at_utc' => '2026-11-02 18:00:00', 'ends_at_utc' => '2026-11-02 20:00:00', 'timezone' => 'UTC']);
    $match = app(ScheduleConflicts::class)->overlap($s, $other);
    expect($match)->not->toBeNull();
    expect($match['end']->format('H:i'))->toBe('19:00');
    $other->ends_at_utc = '2026-11-02 15:00:00';
    $other->starts_at_utc = '2026-11-02 14:00:00';
    expect(app(ScheduleConflicts::class)->overlap($s, $other))->toBeNull();
});

test('unbounded weekly non-overlaps terminate using timezone regimes rather than expanding every day', function () {
    $a = weeklySchedule($this, ['end_date' => null, 'timezone' => 'America/New_York', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'weekdays' => [1]]);
    $b = weeklySchedule($this, ['end_date' => null, 'timezone' => 'America/New_York', 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'weekdays' => [1]]);
    expect(app(ScheduleConflicts::class)->overlap($a, $b))->toBeNull();
});

test('upcoming winner segments exactly agree with the resolver including gaps', function () {
    onceSchedule($this);
    onceSchedule($this, ['priority' => 10, 'starts_at_utc' => '2026-11-02 02:30:00', 'ends_at_utc' => '2026-11-02 04:00:00']);
    $from = CarbonImmutable::parse('2026-11-02 01:00:00', 'UTC');
    $to = $from->addHours(4);
    $resolver = app(ScheduleResolver::class);
    $segments = $resolver->timeline($this->company->id, $from, $to);
    expect($segments)->toHaveCount(4);
    foreach ($segments as $segment) {
        expect($segment['schedule']?->id)->toBe($resolver->resolve($this->company->id, $segment['start'])->id ?? null);
    }
});

test('tenant data and forged Schedule and Playlist IDs cannot cross company boundaries', function () {
    $foreign = onceSchedule($this, ['company_id' => $this->other->id, 'playlist_id' => $this->foreignPlaylist->id, 'name' => 'Foreign Schedule']);
    Livewire::test(ScheduleTable::class)->assertDontSee('Foreign Schedule')->assertDontSee('Private B');
    foreach (['edit', 'delete', 'toggleActive', 'showDetails'] as $method) {
        $component = Livewire::test(ScheduleTable::class);
        expect(fn () => $component->call($method, $foreign->id))->toThrow(ModelNotFoundException::class);
    }
    Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this, ['playlist_id' => $this->foreignPlaylist->id]))->call('save')->assertHasErrors('form.playlist_id');
    $component = Livewire::test(ScheduleTable::class)->call('openCreate');
    expect(fn () => $component->set('scheduleId', $foreign->id))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(resolverAt($this, '2026-11-02 02:30:00'))->toBeNull();
});

test('All Companies and workspace switching reject create and existing forms', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Livewire::test(ScheduleTable::class)->assertSee('Select a company workspace')->call('openCreate')->assertForbidden();
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $component = Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this));
    session()->put(WorkspaceService::SESSION_KEY, $this->other->id);
    $component->call('save')->assertForbidden();
    $this->assertDatabaseCount('schedules', 0);
});

test('view-only and inactive workspace cannot mutate schedules', function () {
    $s = onceSchedule($this);
    $this->user->revokePermissionTo(['scheduler.create', 'scheduler.edit', 'scheduler.delete']);
    foreach (['openCreate' => [], 'save' => [], 'edit' => [$s->id], 'delete' => [$s->id], 'toggleActive' => [$s->id]] as $method => $args) {
        Livewire::test(ScheduleTable::class)->call($method, ...$args)->assertForbidden();
    }
    $this->company->update(['is_active' => false]);
    Livewire::test(ScheduleTable::class)->assertForbidden();
});

test('inactive Playlists cannot be targeted or re-enabled and referenced Playlist deletion is safe', function () {
    $s = onceSchedule($this, ['is_active' => false]);
    $this->playlist->update(['is_active' => false]);
    Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this))->call('save')->assertHasErrors('form.playlist_id');
    Livewire::test(ScheduleTable::class)->call('toggleActive', $s->id)->assertHasErrors('schedule');
    Livewire::test(PlaylistTable::class)->call('delete', $this->playlist->id)->assertSee('Playlist is used by a Schedule');
    $this->assertDatabaseHas('playlists', ['id' => $this->playlist->id]);
    $this->assertDatabaseHas('schedules', ['id' => $s->id]);
});

test('company timezone setting is the default and is snapshotted on save', function () {
    Setting::create(['company_id' => $this->company->id, 'key' => 'timezone', 'value' => 'Asia/Tokyo', 'group' => 'general', 'type' => 'text', 'is_public' => true]);
    $component = Livewire::test(ScheduleTable::class)->call('openCreate')->assertSet('form.timezone', 'Asia/Tokyo')->set('form', schedulerForm($this, ['timezone' => 'Asia/Tokyo']))->call('save')->assertHasNoErrors();
    Setting::where('company_id', $this->company->id)->where('key', 'timezone')->update(['value' => 'Europe/London']);
    expect(Schedule::sole()->timezone)->toBe('Asia/Tokyo');
    Setting::where('company_id', $this->company->id)->where('key', 'timezone')->update(['value' => 'invalid/timezone']);
    Livewire::test(ScheduleTable::class)->call('openCreate')->assertSet('form.timezone', 'Asia/Jakarta');
});

test('conditional validation rejects invalid intervals weekdays timezone and date ranges', function () {
    foreach ([['start_time' => '09:00', 'end_time' => '09:00'], ['weekdays' => []], ['weekdays' => [0, 8]], ['weekdays' => [1, 1]], ['timezone' => 'GMT+7'], ['end_date' => '2026-01-01'], ['schedule_type' => 'invalid']] as $data) {
        Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this, $data))->call('save')->assertHasErrors();
    }
    Livewire::test(ScheduleTable::class)->call('openCreate')->set('form', schedulerForm($this, ['schedule_type' => 'once', 'once_end' => '2026-11-02T11:00']))->call('save')->assertHasErrors('form.once_end');
});

test('list weekly views filters navigation and time lookup render actual data', function () {
    onceSchedule($this, ['name' => 'Morning Lobby']);
    $this->seed(MenuSeeder::class);
    $this->get('/admin/scheduler')->assertOk()->assertSee('Upcoming 7 Days')->assertSee(route('admin.scheduler.index'));
    $component = Livewire::test(ScheduleTable::class)->assertSee('Morning Lobby')->set('view', 'weekly')->assertSee('Scheduler - Weekly View')->assertSee('Lobby A')
        ->call('shiftWeek', 1)->assertSet('weekStart', '2026-11-09')->call('currentWeek')->assertSet('weekStart', '2026-11-02')
        ->set('probeTime', '2026-11-02T09:30')->call('inspectTime')->assertSee('Morning Lobby')->assertHasNoErrors();
    $this->user->revokePermissionTo('scheduler.view');
    $this->get('/admin/scheduler')->assertForbidden();
});

test('permission seeder is additive on repeated runs', function () {
    Permission::firstOrCreate(['name' => 'custom.scheduler', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('custom.scheduler');
    $this->seed(SchedulerPermissionSeeder::class);
    $this->seed(SchedulerPermissionSeeder::class);
    expect($role->fresh()->hasPermissionTo('custom.scheduler'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('scheduler.delete'))->toBeTrue();
});

test('conflicts find seasonal overlaps outside the first preview weeks', function () {
    $a = weeklySchedule($this, ['timezone' => 'UTC', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'weekdays' => [1], 'start_time' => '14:00:00', 'end_time' => '14:30:00']);
    $b = weeklySchedule($this, ['timezone' => 'America/New_York', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'weekdays' => [1], 'start_time' => '09:00:00', 'end_time' => '09:30:00']);
    $overlap = app(ScheduleConflicts::class)->overlap($a, $b);
    expect($overlap['start']->format('Y-m-d H:i'))->toBe('2026-11-02 14:00');
});

test('fold expansion can create conflicts between normally disjoint local time ranges', function () {
    $a = weeklySchedule($this, ['timezone' => 'America/New_York', 'start_date' => '2026-10-01', 'end_date' => '2026-11-30', 'weekdays' => [7], 'start_time' => '01:00:00', 'end_time' => '01:20:00']);
    $b = weeklySchedule($this, ['timezone' => 'America/New_York', 'start_date' => '2026-10-01', 'end_date' => '2026-11-30', 'weekdays' => [7], 'start_time' => '01:30:00', 'end_time' => '01:40:00']);
    $overlap = app(ScheduleConflicts::class)->overlap($a, $b);
    expect($overlap['start']->format('Y-m-d H:i'))->toBe('2026-11-01 05:30');
    expect($overlap['end']->format('Y-m-d H:i'))->toBe('2026-11-01 06:20');
});

test('corrupt cross-company Playlist references never leak through scoped Schedule reads', function () {
    $bad = onceSchedule($this, ['playlist_id' => $this->foreignPlaylist->id, 'name' => 'Bad Schedule']);
    Livewire::test(ScheduleTable::class)->assertDontSee('Bad Schedule')->assertDontSee('Private B');
    $component = Livewire::test(ScheduleTable::class);
    expect(fn () => $component->call('showDetails', $bad->id))->toThrow(ModelNotFoundException::class);
    expect(resolverAt($this, '2026-11-02 02:30:00'))->toBeNull();
});

test('search and Playlist status type filters constrain the list and weekly view', function () {
    onceSchedule($this, ['name' => 'Once Target']);
    weeklySchedule($this, ['name' => 'Inactive Target', 'is_active' => false]);
    $component = Livewire::test(ScheduleTable::class)->set('typeFilter', 'once')->assertSee('Once Target')->assertDontSee('Inactive Target')
        ->set('typeFilter', '')->set('activeFilter', '0')->assertSee('Inactive Target');
    $component->set('activeFilter', '')->set('search', 'No such Schedule')->assertSee('No schedules match these filters.')
        ->set('view', 'weekly')->assertSee('No schedules in this view');
    $component->set('search','')->set('playlistFilter',(string) $this->foreignPlaylist->id)->assertSee('No schedules in this view');
});

test('Company deletion reports Schedule references without cascading into restricted Playlists',function () {
    Permission::firstOrCreate(['name' => 'companies.delete', 'guard_name' => 'web']);
    $this->user->givePermissionTo('companies.delete');
    onceSchedule($this,['company_id' => $this->other->id, 'playlist_id' => $this->foreignPlaylist->id]);
    Livewire::test(CompanyTable::class)->call('delete',$this->other->id)->assertSee('Company cannot be deleted because it has schedules');
    $this->assertDatabaseHas('companies',['id' => $this->other->id]);
});
