<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\User;
use App\Services\DeviceLifecycle;
use App\Services\DevicePlaylistAssignment;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/acceptance-db-guard.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || getenv('CMS_REAL_ACCEPTANCE') !== '1' || $app->configurationIsCached()) {
    throw new RuntimeException('fixture_environment_invalid');
}
$configured = config('database.connections.mysql');
$approvedDatabase = getenv('CMS_ACCEPTANCE_DB_DATABASE');
$mainEnv = is_file(base_path('.env')) ? \Dotenv\Dotenv::parse(file_get_contents(base_path('.env'))) : [];
if (config('database.default') !== 'mysql' || ! is_string($approvedDatabase)
    || ! preg_match('/^[a-zA-Z0-9_]+_acceptance_test$/D', $approvedDatabase)
    || ($configured['database'] ?? null) !== $approvedDatabase
    || ($configured['host'] ?? null) !== '127.0.0.1'
    || ($mainEnv['DB_DATABASE'] ?? '') === '' || ! empty($mainEnv['DB_URL'])
    || strcasecmp($mainEnv['DB_DATABASE'], $approvedDatabase) === 0
    || empty($configured['username']) || strtolower($configured['username']) === 'root'
    || ! empty($configured['url'])) {
    throw new RuntimeException('fixture_database_not_isolated');
}
$skipGrantInspection = acceptanceGrantInspectionBypassed(
    getenv('ACCEPTANCE_SKIP_GRANT_INSPECTION'), $app->environment(), $configured, $mainEnv,
    ['database' => $approvedDatabase, 'username' => getenv('CMS_ACCEPTANCE_DB_USERNAME'),
        'host' => getenv('CMS_ACCEPTANCE_DB_HOST'), 'port' => getenv('CMS_ACCEPTANCE_DB_PORT'),
        'allowlist' => getenv('CMS_ACCEPTANCE_DISPOSABLE_DATABASES') ?: ''],
    fn () => DB::selectOne('SELECT DATABASE() AS database_name, CURRENT_USER() AS account, @@port AS server_port'),
    fn ($database, $user) => \Illuminate\Support\Facades\Log::warning('Acceptance grant inspection bypassed', ['database' => $database, 'database_user' => $user])
);
// The default remains fail-closed for broad grants, roles and wildcard schemas.
if (! $skipGrantInspection) {
$schemaGrant = false;
$partialRevokes = DB::selectOne("SHOW VARIABLES LIKE 'partial_revokes'");
$partialRevokesEnabled = strtoupper((string) ($partialRevokes->Value ?? 'OFF')) === 'ON';
foreach (DB::select('SHOW GRANTS FOR CURRENT_USER') as $grantRow) {
    $grant = (string) array_values((array) $grantRow)[0];
    if (! preg_match('/^GRANT (.+?) ON (.+?) TO /i', $grant, $matches)) throw new RuntimeException('fixture_database_grants_invalid');
    $scope = str_replace('`', '', $matches[2]);
    if ($scope === '*.*' && strtoupper(trim($matches[1])) === 'USAGE') continue;
    if (! acceptanceSchemaGrantIsLiteral($scope, $approvedDatabase, $partialRevokesEnabled)) throw new RuntimeException('fixture_database_grants_invalid');
    $schemaGrant = true;
}
if (! $schemaGrant) throw new RuntimeException('fixture_database_grants_invalid');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$tag = $input['tag'] ?? '';
if (! is_string($tag) || ! preg_match('/^[a-f0-9]{12}$/D', $tag) || ! is_string($input['owner'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $input['owner'])) {
    throw new RuntimeException('fixture_tag_invalid');
}$code = 'AOP-'.strtoupper($tag);
$ownerName = 'Acceptance '.hash('sha256', $input['owner']);
$ownedCompany = static function () use ($code, $ownerName) {
    $company = Company::where('code', $code)->first();
    if ($company && ! hash_equals($ownerName, $company->name)) {
        throw new RuntimeException('fixture_ownership_invalid');
    }
    return $company;
};
if ($input['action'] === 'preflight') {
    $tables = DB::select('SHOW FULL TABLES');
    $ready = false;
    if (count($tables) > 0 && \Illuminate\Support\Facades\Schema::hasTable('migrations') && \Illuminate\Support\Facades\Schema::hasTable('permissions')) {
        $expected = array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php')));
        $applied = DB::table('migrations')->pluck('migration')->all();
        sort($expected); sort($applied);
        $permissions = ['dashboard.view', 'playlists.view', 'devices.view', 'devices.assign', 'playback.view', 'playback.export', 'playback.reset', 'media.view'];
        $ready = $expected === $applied && DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $permissions)->distinct()->count('name') === count($permissions);
    }
    echo json_encode(['safe' => true, 'table_count' => count($tables), 'schema_ready' => $ready]);
    exit;
}
if ($input['action'] === 'seed-permissions') {
    foreach (['dashboard.view', 'playlists.view', 'devices.view', 'devices.assign', 'playback.view', 'playback.export', 'playback.reset', 'media.view'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    echo '{"seeded":true}';
    exit;
}
if ($input['action'] === 'cleanup') {
    if ($company = $ownedCompany()) {
        foreach (['playback_events', 'device_playback_packages', 'device_item_counters', 'device_playlist_changes'] as $table) {
            DB::table($table)->where('company_id', $company->id)->delete();
        }
        DB::table('schedules')->where('company_id', $company->id)->delete();
        Device::where('company_id', $company->id)->delete();
        Playlist::where('company_id', $company->id)->delete();
        foreach (MediaAsset::where('company_id', $company->id)->get() as $asset) {
            if (str_starts_with($asset->path, 'testing/always-on/'.$tag.'/')) {
                Storage::disk('local')->delete($asset->path);
            }$asset->delete();
        }
        foreach (User::where('company_id', $company->id)->get() as $user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('login_histories')->where('user_id', $user->id)->delete();
            $user->delete();
        }
        DB::table('activity_logs')->where('company_id', $company->id)->delete();
        $company->delete();
    }echo '{"cleaned":true}';
    exit;
}
if ($input['action'] === 'inspect') {
    $company = $ownedCompany() ?? throw new RuntimeException('fixture_not_found');
    $events = DB::table('playback_events')->where('company_id', $company->id);
    $counts = (clone $events)->where('status', 'completed')->selectRaw('playlist_zone_item_id,COUNT(*) as plays')->groupBy('playlist_zone_item_id')->get();
    $device = Device::where('company_id', $company->id)->firstOrFail();
    $summary = (clone $events)->selectRaw("media_asset_id, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status='interrupted' THEN 1 ELSE 0 END) as partial, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed, SUM(actual_played_ms) as played_ms")->groupBy('media_asset_id')->get();
    echo json_encode(['counts' => $counts, 'summary' => $summary, 'events' => $events->count(), 'applied' => $device->reported_playlist_id === $device->primary_playlist_id && $device->reported_command_id === $device->playlist_command_id, 'historyRevision' => DB::table('device_playlist_changes')->where('company_id',$company->id)->where('device_id',$device->id)->where('source','player_report')->whereNotNull('manifest_revision')->exists(), 'pending' => $device->pending_playback_events]);
    exit;
}
if ($input['action'] !== 'setup') {
    throw new RuntimeException('fixture_action_invalid');
}
if (Company::where('code', $code)->exists()) throw new RuntimeException('fixture_tag_collision');
$bytes = base64_decode($input['video'] ?? '', true);
if (! is_string($bytes) || strlen($bytes) > 2000000 || ! str_contains(substr($bytes, 0, 100), 'ftyp')) throw new RuntimeException('fixture_video_invalid');
$paths = ['testing/always-on/'.$tag.'/media.mp4', 'testing/always-on/'.$tag.'/media.png'];
foreach ($paths as $path) if (Storage::disk('local')->exists($path)) throw new RuntimeException('fixture_path_collision');
$written = [];
DB::beginTransaction();
try {
$company = Company::create(['name' => $ownerName, 'code' => $code, 'is_active' => true]);
$password = bin2hex(random_bytes(16));
$user = new User;
$user->forceFill(['company_id' => $company->id, 'name' => 'Acceptance Operator', 'email' => $tag.'@always-on.test', 'password' => Hash::make($password), 'email_verified_at' => now()])->save();
$user->givePermissionTo(['dashboard.view', 'playlists.view', 'devices.view', 'devices.assign', 'playback.view', 'playback.export', 'playback.reset', 'media.view']);
Auth::setUser($user);
$playlist = Playlist::create(['company_id' => $company->id, 'name' => 'Always-On Real Playback', 'canvas_width' => 640, 'canvas_height' => 360, 'background_color' => '#102030', 'is_active' => true]);
$page = $playlist->pages()->create(['name' => 'Parallel Windows', 'duration_seconds' => 15]);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=');
$ids = [];
foreach ([['video', $bytes, 'mp4', 3], ['image', $png, 'png', 5]] as $i => [$type,$content,$ext,$max]) {
    $path = 'testing/always-on/'.$tag.'/media.'.$ext;
    if (! Storage::disk('local')->put($path, $content)) throw new RuntimeException('fixture_media_write_failed');
    $written[] = $path;
    $asset = MediaAsset::create(['company_id' => $company->id, 'title' => 'Actual '.$type, 'type' => $type, 'mime_type' => $type === 'video' ? 'video/mp4' : 'image/png', 'file_name' => 'media.'.$ext, 'original_name' => 'media.'.$ext, 'path' => $path, 'disk' => 'local', 'size' => strlen($content), 'is_active' => true]);
    $zone = $page->zones()->create(['name' => 'Window '.($i + 1), 'x' => $i * 320, 'y' => 0, 'width' => 320, 'height' => 360, 'is_visible' => true, 'fit_mode' => 'contain']);
    $item = $zone->items()->create(['media_asset_id' => $asset->id, 'duration_seconds' => $type === 'image' ? 1 : null, 'max_plays' => $max]);
    $ids[$type] = $item->id;
    $ids[$type.'_media'] = $asset->id;
}
$device = Device::create(['company_id' => $company->id, 'name' => 'Real Windows Acceptance', 'platform' => 'windows']);
app(DevicePlaylistAssignment::class)->apply($playlist->id, [$device->id]);
$activation = app(DeviceLifecycle::class)->issue($company->id, $device->id);
DB::commit();
echo json_encode(['email' => $user->email, 'password' => $password, 'activation' => $activation, 'ids' => $ids]);
} catch (Throwable $e) {
    DB::rollBack();
    foreach ($written as $path) Storage::disk('local')->delete($path);
    throw $e;
}
