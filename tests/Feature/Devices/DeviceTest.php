<?php

use App\Livewire\Devices\DeviceTable;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\DeviceLifecycle;
use App\Services\WorkspaceService;
use Carbon\CarbonImmutable;
use Database\Seeders\DevicePermissionSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 03:00:00', 'UTC'));
    foreach (['devices.view', 'devices.create', 'devices.edit', 'devices.delete', 'devices.activate', 'devices.revoke', 'dashboard.view', 'media.view', 'media.create'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $this->company = Company::create(['name' => 'Device A', 'code' => 'DEVA', 'is_active' => true]);
    $this->other = Company::create(['name' => 'Device B', 'code' => 'DEVB', 'is_active' => true]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->givePermissionTo(['devices.view', 'devices.create', 'devices.edit', 'devices.delete', 'devices.activate', 'devices.revoke', 'dashboard.view', 'media.view', 'media.create']);
    $this->device = Device::create(['company_id' => $this->company->id, 'name' => 'Lobby Screen', 'platform' => 'windows', 'created_by' => $this->user->id]);
    $this->foreign = Device::create(['company_id' => $this->other->id, 'name' => 'Foreign Screen', 'platform' => 'android']);
    $this->group = DeviceGroup::create(['company_id' => $this->company->id, 'name' => 'Lobby']);
    $this->foreignGroup = DeviceGroup::create(['company_id' => $this->other->id, 'name' => 'Foreign Group']);
    $this->actingAs($this->user);
});
afterEach(fn () => CarbonImmutable::setTestNow());

function deviceForm(array $data = []): array
{
    return array_replace(['name' => 'New Screen', 'description' => 'Welcome', 'location' => 'Lobby', 'platform' => 'windows', 'orientation' => 'landscape', 'screen_width' => 1920, 'screen_height' => 1080, 'groups' => []], $data);
}
function activationPayload(string $code, array $data = []): array
{
    return array_replace(['activation_code' => $code, 'platform' => 'windows', 'player_version' => '1.0.0', 'screen_width' => 1920, 'screen_height' => 1080], $data);
}
function activateFixture($test): string
{
    $code = app(DeviceLifecycle::class)->issue($test->company->id, $test->device->id);
    $response = $test->postJson('/api/device/activate', activationPayload($code))->assertCreated();

    return $response->json('credential');
}

test('admin can create edit and delete Device without changing public identity', function () {
    $component = Livewire::test(DeviceTable::class)->call('openCreate')->set('form', deviceForm(['groups' => [$this->group->id]]))->call('save')->assertHasNoErrors()->assertReturned(true);
    $device = Device::where('name', 'New Screen')->sole();
    $uuid = $device->public_id;
    expect($device->status)->toBe('pending');
    expect($device->groups()->count())->toBe(1);
    $component->call('edit', $device->id)->set('form.name', 'Renamed Screen')->call('save')->assertHasNoErrors();
    expect($device->fresh()->public_id)->toBe($uuid);
    $component->call('delete', $device->id);
    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    $this->assertDatabaseHas('device_groups', ['id' => $this->group->id]);
});

test('Device Groups support create rename multiple membership removal and safe delete', function () {
    $component = Livewire::test(DeviceTable::class)->call('createGroup')->set('groupForm', ['name' => 'Floor 2', 'description' => 'Second floor'])->call('saveGroup')->assertHasNoErrors();
    $group = DeviceGroup::where('name', 'Floor 2')->sole();
    $component->call('editGroup', $group->id)->set('groupForm.name', 'Upstairs')->call('saveGroup')->assertHasNoErrors()
        ->set('groupMembers.'.$group->id, (string) $this->device->id)->call('addToGroup', $group->id)
        ->set('groupMembers.'.$this->group->id, (string) $this->device->id)->call('addToGroup', $this->group->id);
    expect($this->device->groups()->count())->toBe(2);
    $component->call('removeFromGroup', $this->group->id, $this->device->id);
    expect($this->device->groups()->count())->toBe(1);
    $component->call('deleteGroup', $group->id);
    $this->assertDatabaseHas('devices', ['id' => $this->device->id]);
    expect($this->device->groups()->count())->toBe(0);
});

test('forged Device and Group IDs cannot cross company boundaries', function () {
    Livewire::test(DeviceTable::class)->assertDontSee('Foreign Screen')->assertDontSee('Foreign Group');
    foreach (['edit', 'delete', 'generateCode', 'cancelCode', 'revoke', 'disable', 'enable', 'rePair'] as $method) {
        $component = Livewire::test(DeviceTable::class);
        expect(fn () => $component->call($method, $this->foreign->id))->toThrow(ModelNotFoundException::class);
    }
    foreach (['editGroup', 'deleteGroup'] as $method) {
        $component = Livewire::test(DeviceTable::class);
        expect(fn () => $component->call($method, $this->foreignGroup->id))->toThrow(ModelNotFoundException::class);
    }
    $component = Livewire::test(DeviceTable::class)->set('groupMembers.'.$this->foreignGroup->id, (string) $this->device->id);
    expect(fn () => $component->call('addToGroup', $this->foreignGroup->id))->toThrow(ModelNotFoundException::class);
    $component = Livewire::test(DeviceTable::class)->set('groupMembers.'.$this->group->id, (string) $this->foreign->id);
    expect(fn () => $component->call('addToGroup', $this->group->id))->toThrow(ModelNotFoundException::class);
    Livewire::test(DeviceTable::class)->call('openCreate')->set('form', deviceForm(['groups' => [$this->foreignGroup->id]]))->call('save')->assertHasErrors('form.groups');
    $this->assertDatabaseMissing('devices', ['name' => 'New Screen']);
});

test('database composite foreign keys reject a cross-company membership even outside the service', function () {
    expect(fn () => DB::table('device_group_device')->insert(['company_id' => $this->company->id, 'device_id' => $this->device->id, 'device_group_id' => $this->foreignGroup->id]))->toThrow(QueryException::class);
});

test('valid pairing activates atomically and stores only hashes', function () {
    $service = app(DeviceLifecycle::class);
    $code = $service->issue($this->company->id, $this->device->id);
    $pair = $this->device->pairingCodes()->sole();
    expect(hash_equals($pair->code_hash, hash('sha256', $service::normalizeCode($code))))->toBeTrue();
    expect($pair->expires_at->diffInSeconds(CarbonImmutable::now('UTC'), true))->toBe(600.0);
    $response = $this->postJson('/api/device/activate', activationPayload($code))->assertCreated()->assertJsonPath('device.public_id', $this->device->public_id)->assertJsonPath('device.status', 'active');
    $token = $response->json('credential');
    expect(is_string($token) && strlen($token) > 90)->toBeTrue();
    $credential = $this->device->credentials()->sole();
    $secret = explode('.', $token)[1];
    expect(hash_equals($credential->token_hash, hash('sha256', $secret)))->toBeTrue();
    expect($this->device->fresh()->status)->toBe('active');
    expect($pair->fresh()->consumed_at)->not->toBeNull();
    expect($this->device->fresh()->last_seen_at)->toBeNull();
    $serialized = $credential->toJson();
    expect(str_contains($serialized, $credential->token_hash))->toBeFalse();
    expect(str_contains($serialized, $token))->toBeFalse();
});

test('expired cancelled replaced and replayed pairing codes are rejected', function () {
    $service = app(DeviceLifecycle::class);
    $code = $service->issue($this->company->id, $this->device->id);
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(10));
    $this->postJson('/api/device/activate', activationPayload($code))->assertStatus(422);
    $code = $service->issue($this->company->id, $this->device->id);
    $service->cancel($this->company->id, $this->device->id);
    $this->postJson('/api/device/activate', activationPayload($code))->assertStatus(422);
    $old = $service->issue($this->company->id, $this->device->id);
    $new = $service->issue($this->company->id, $this->device->id);
    $this->postJson('/api/device/activate', activationPayload($old))->assertStatus(422);
    $this->postJson('/api/device/activate', activationPayload($new))->assertCreated();
    $this->postJson('/api/device/activate', activationPayload($new))->assertStatus(422);
    expect($this->device->credentials()->count())->toBe(1);
});

test('invalid activation attempts are rate limited and never accept client company identity', function () {
    $payload = activationPayload(str_repeat('F', 20));
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/device/activate', $payload)->assertStatus(422);
    }
    $this->postJson('/api/device/activate', $payload)->assertStatus(429);
});

test('activation ignores admin login and rejects identity overrides and query secrets', function () {
    $code = app(DeviceLifecycle::class)->issue($this->company->id, $this->device->id);
    $this->postJson('/api/device/activate', activationPayload($code, ['company_id' => $this->other->id]))->assertUnprocessable()->assertJsonValidationErrors('company_id');
    $this->postJson('/api/device/activate?activation_code=redacted', activationPayload($code))->assertUnprocessable();
    $this->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    $this->post('/api/device/activate', activationPayload($code))->assertStatus(415);
    auth()->logout();
    $this->postJson('/api/device/activate', activationPayload($code))->assertCreated();
});

test('heartbeat authenticates only the issued Device and uses server UTC not client time', function () {
    $token = activateFixture($this);
    $now = CarbonImmutable::now('UTC');
    $this->withToken($token)->postJson('/api/device/heartbeat', ['uptime_seconds' => 120, 'device_timestamp' => '2099-01-01T00:00:00Z', 'player_version' => '1.0.1'])->assertOk()->assertJsonPath('device_public_id', $this->device->public_id)->assertJsonMissingPath('playlist')->assertJsonMissingPath('schedule')->assertJsonMissingPath('media');
    expect($this->device->fresh()->last_seen_at->eq($now))->toBeTrue();
    expect($this->device->credentials()->sole()->last_used_at->eq($now))->toBeTrue();
    expect($this->foreign->fresh()->last_seen_at)->toBeNull();
    $this->withToken($token)->postJson('/api/device/heartbeat', ['device_id' => $this->foreign->id])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/device/heartbeat', ['company_id' => $this->other->id])->assertUnprocessable();
});

test('invalid revoked disabled deleted and inactive-company credentials fail', function () {
    $token = activateFixture($this);
    $this->withToken($this->device->public_id)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    $this->withToken($token.'bad')->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    $this->company->update(['is_active' => false]);
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    $this->company->update(['is_active' => true]);
    app(DeviceLifecycle::class)->disable($this->company->id, $this->device->id, true);
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    app(DeviceLifecycle::class)->disable($this->company->id, $this->device->id, false);
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    $this->device->delete();
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
});

test('revoke and re-pair permanently invalidate every old credential', function () {
    $old = activateFixture($this);
    $service = app(DeviceLifecycle::class);
    $service->revoke($this->company->id, $this->device->id);
    $this->withToken($old)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    $code = $service->issue($this->company->id, $this->device->id, true);
    $this->flushHeaders();
    $new = $this->postJson('/api/device/activate', activationPayload($code))->assertCreated()->json('credential');
    $this->withToken($new)->postJson('/api/device/heartbeat', [])->assertOk();
    $this->withToken($old)->postJson('/api/device/heartbeat', [])->assertUnauthorized();
    expect($this->device->credentials()->count())->toBe(2);
    expect($this->device->activeCredentials()->count())->toBe(1);
});

test('Online Offline Never Pending Disabled and Revoked are derived from server state', function () {
    expect($this->device->connectionStatus())->toBe('Pending');
    $token = activateFixture($this);
    expect($this->device->fresh()->connectionStatus())->toBe('Never connected');
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertOk();
    expect($this->device->fresh()->connectionStatus())->toBe('Online');
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addSeconds(180));
    expect($this->device->fresh()->connectionStatus())->toBe('Online');
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addSecond());
    expect($this->device->fresh()->connectionStatus())->toBe('Offline');
    app(DeviceLifecycle::class)->revoke($this->company->id, $this->device->id);
    expect($this->device->fresh()->connectionStatus())->toBe('Revoked');
    app(DeviceLifecycle::class)->disable($this->company->id, $this->device->id, true);
    expect($this->device->fresh()->connectionStatus())->toBe('Disabled');
});

test('activation and heartbeat enforce payload and field bounds', function () {
    $this->postJson('/api/device/activate', ['padding' => str_repeat('x', 9000)])->assertStatus(413);
    $code = app(DeviceLifecycle::class)->issue($this->company->id, $this->device->id);
    $this->postJson('/api/device/activate', activationPayload($code, ['platform' => 'invalid', 'screen_width' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['platform', 'screen_width']);
    $token = $this->postJson('/api/device/activate', activationPayload($code))->assertCreated()->json('credential');
    $this->withToken($token)->postJson('/api/device/heartbeat', ['screen_width' => 1])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/device/heartbeat', ['uptime_seconds' => -1, 'player_version' => str_repeat('a', 51)])->assertUnprocessable();
});

test('view-only cannot mutate Devices Groups or secrets', function () {
    $this->user->revokePermissionTo(['devices.create', 'devices.edit', 'devices.delete', 'devices.activate', 'devices.revoke']);
    foreach (['openCreate' => [], 'save' => [], 'edit' => [$this->device->id], 'delete' => [$this->device->id], 'generateCode' => [$this->device->id], 'rePair' => [$this->device->id], 'revoke' => [$this->device->id], 'cancelCode' => [$this->device->id], 'disable' => [$this->device->id], 'enable' => [$this->device->id], 'createGroup' => [], 'saveGroup' => [], 'editGroup' => [$this->group->id], 'deleteGroup' => [$this->group->id], 'addToGroup' => [$this->group->id], 'removeFromGroup' => [$this->group->id, $this->device->id]] as $method => $args) {
        Livewire::test(DeviceTable::class)->call($method, ...$args)->assertForbidden();
    }
});

test('All Companies inactive workspaces and stale forms cannot mutate Devices', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    Livewire::test(DeviceTable::class)->assertSee('Select a company workspace')->call('openCreate')->assertForbidden();
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $component = Livewire::test(DeviceTable::class)->call('openCreate')->set('form', deviceForm());
    session()->put(WorkspaceService::SESSION_KEY, $this->other->id);
    $component->call('save')->assertForbidden();
    session()->put(WorkspaceService::SESSION_KEY, $this->company->id);
    $this->company->update(['is_active' => false]);
    Livewire::test(DeviceTable::class)->assertForbidden();
});

test('locked form identity cannot be forged', function () {
    $component = Livewire::test(DeviceTable::class)->call('openCreate');
    expect(fn () => $component->set('deviceId', $this->foreign->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('enabling cannot be used as an unauthorized revoke', function () {
    $token = activateFixture($this);
    $this->user->revokePermissionTo('devices.revoke');
    Livewire::test(DeviceTable::class)->call('enable', $this->device->id)->assertStatus(422);
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertOk();
});

test('admin secret display is one response only and details activity never contain secrets', function () {
    $issued = null;
    $component = Livewire::test(DeviceTable::class)->call('generateCode', $this->device->id)->assertDispatched('device-pairing-code', function ($name, $params) use (&$issued) {
        $issued = $params['code'];

        return is_string($issued);
    });
    expect($issued)->not->toBeNull();
    $component->assertDontSee($issued, false)->call('$refresh')->assertNotDispatched('device-pairing-code');
    $token = $this->postJson('/api/device/activate', activationPayload($issued))->assertCreated()->json('credential');
    $response = $this->get(route('admin.devices.show', $this->device->public_id))->assertOk();
    expect(str_contains($response->getContent(), $token))->toBeFalse();
    expect(str_contains($response->getContent(), $issued))->toBeFalse();
    $logs = ActivityLog::where('module', 'devices')->get()->toJson();
    expect(str_contains($logs, $issued))->toBeFalse();
    expect(str_contains($logs, $token))->toBeFalse();
    expect(ActivityLog::where('action', 'activated')->sole()->user_id)->toBeNull();
    $this->get(route('admin.devices.show', $this->foreign->public_id))->assertNotFound();
});

test('admin actions audit the selected Company rather than the super-admin home Company', function () {
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    session()->put(WorkspaceService::SESSION_KEY, $this->other->id);
    Livewire::test(DeviceTable::class)->call('generateCode', $this->foreign->id)->assertDispatched('device-pairing-code');
    expect(ActivityLog::where('action', 'pairing.generated')->sole()->company_id)->toBe($this->other->id);
});

test('Device routes list filters sorting pagination and group detail render company data', function () {
    $this->seed(MenuSeeder::class);
    $this->get('/admin/devices')->assertOk()->assertSee(route('admin.devices.index'));
    Livewire::test(DeviceTable::class)->set('view', 'list')->assertSee('Lobby Screen')->assertDontSee('Foreign Screen')->set('platformFilter', 'android')->assertSee('No Devices found.')->set('platformFilter', '')->set('sortBy', 'last_seen_at')->set('sortDirection', 'desc')->assertSee('Lobby Screen');
    $this->get(route('admin.devices.show', $this->device->public_id))->assertOk()->assertSee('Never connected');
});

test('Device permission seeder preserves custom grants', function () {
    Permission::firstOrCreate(['name' => 'custom.device', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->givePermissionTo('custom.device');
    $this->seed(DevicePermissionSeeder::class);
    $this->seed(DevicePermissionSeeder::class);
    expect($role->fresh()->hasPermissionTo('custom.device'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('devices.revoke'))->toBeTrue();
});

test('new uploads are private and media URLs remain authorized for legacy and private images PDF and video', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->post('/admin/media-assets/upload', ['files' => [UploadedFile::fake()->image('private.png')]])->assertOk();
    $media = MediaAsset::sole();
    expect($media->disk)->toBe('local');
    Storage::disk('local')->assertExists($media->path);
    Storage::disk('public')->assertMissing($media->path);
    expect($media->url)->toBe(route('admin.media-assets.stream', $media->id));
    $this->get($media->url)->assertOk();
    $this->get('/storage/companies/'.$this->company->id.'/media/known.png')->assertForbidden();
    $foreign = MediaAsset::create(['company_id' => $this->other->id, 'title' => 'Private', 'original_name' => 'a.pdf', 'file_name' => 'a.pdf', 'disk' => 'public', 'path' => 'a.pdf', 'type' => 'pdf', 'mime_type' => 'application/pdf']);
    Storage::disk('public')->put('a.pdf', '%PDF-1.4');
    $this->get($foreign->url)->assertNotFound();
    $this->user->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
    $this->get($media->url)->assertForbidden();
    session()->put(WorkspaceService::SESSION_KEY, $this->other->id);
    $this->get($foreign->url)->assertOk();
});

test('heartbeat throttles individual credentials and rejects oversized diagnostics', function () {
    $token = activateFixture($this);
    $this->withToken($token)->postJson('/api/device/heartbeat', ['padding' => str_repeat('x', 9000)])->assertStatus(413);
    for ($i = 0; $i < 10; $i++) {
        $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertOk();
    }
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertStatus(429);
});

test('known failed code attempts persist without issuing credentials', function () {
    $service = app(DeviceLifecycle::class);
    $code = $service->issue($this->company->id, $this->device->id);
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(11));
    for ($i = 0; $i < 6; $i++) {
        expect($service->activate(activationPayload($code)))->toBeNull();
    }
    expect($this->device->pairingCodes()->sole()->attempts)->toBe(6);
    expect($this->device->credentials()->count())->toBe(0);
});

test('Device credentials do not authenticate admin content endpoints', function () {
    $token = activateFixture($this);
    auth()->logout();
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertOk();
    $this->withToken($token)->getJson('/admin/playlists')->assertRedirect(route('login'));
    $this->withToken($token)->getJson('/admin/media-assets')->assertRedirect(route('login'));
});

test('heartbeat rate limits use separate buckets for Devices sharing an IP', function () {
    $a = activateFixture($this);
    $service = app(DeviceLifecycle::class);
    $other = Device::create(['company_id' => $this->company->id, 'name' => 'Second Screen', 'platform' => 'android']);
    $code = $service->issue($this->company->id, $other->id);
    $b = $this->postJson('/api/device/activate', activationPayload($code))->assertCreated()->json('credential');
    for ($i = 0; $i < 10; $i++) {
        $this->withToken($a)->postJson('/api/device/heartbeat', [])->assertOk();
    }
    $this->withToken($a)->postJson('/api/device/heartbeat', [])->assertStatus(429);
    $this->withToken($b)->postJson('/api/device/heartbeat', [])->assertOk();
});

test('malformed JSON and activation for inactive Company are rejected without credentials', function () {
    $this->call('POST', '/api/device/heartbeat', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{')->assertStatus(400);
    $code = app(DeviceLifecycle::class)->issue($this->company->id, $this->device->id);
    $this->company->update(['is_active' => false]);
    $this->postJson('/api/device/activate', activationPayload($code))->assertUnprocessable();
    expect($this->device->credentials()->count())->toBe(0);
});

test('connection filters agree with server freshness and credential state', function () {
    $token = activateFixture($this);
    Livewire::test(DeviceTable::class)->set('statusFilter', 'never')->assertSee('Lobby Screen')->set('statusFilter', 'online')->assertSee('No Devices found.');
    $this->withToken($token)->postJson('/api/device/heartbeat', [])->assertOk();
    Livewire::test(DeviceTable::class)->set('statusFilter', 'online')->assertSee('Lobby Screen')->set('statusFilter', 'offline')->assertSee('No Devices found.');
    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addSeconds(181));
    Livewire::test(DeviceTable::class)->set('statusFilter', 'offline')->assertSee('Lobby Screen');
    app(DeviceLifecycle::class)->revoke($this->company->id, $this->device->id);
    Livewire::test(DeviceTable::class)->set('statusFilter', 'revoked')->assertSee('Lobby Screen')->set('statusFilter','online')->assertSee('No Devices found.');
});
