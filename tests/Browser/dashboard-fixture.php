<?php

use App\Models\{ActivityLog, Company, Device, Playlist, Schedule, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{DB, Hash};
use Spatie\Permission\Models\{Permission, Role};

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment(['local', 'testing'])) throw new RuntimeException('fixture_environment_invalid');
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$tag = $input['tag'] ?? '';
if (! is_string($tag) || ! preg_match('/^[a-f0-9]{12}$/D', $tag)) throw new RuntimeException('fixture_tag_invalid');
$code = 'DNF-'.strtoupper($tag);
if ($input['action'] === 'cleanup') {
    foreach (Company::whereIn('code', [$code, $code.'B'])->get() as $company) {
        Schedule::where('company_id', $company->id)->delete();
        Device::where('company_id', $company->id)->delete();
        Playlist::where('company_id', $company->id)->delete();
        foreach (User::where('company_id', $company->id)->get() as $user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('login_histories')->where('user_id', $user->id)->delete();
            $user->delete();
        }
        ActivityLog::where('company_id', $company->id)->delete();
        $company->delete();
    }
    echo '{"cleaned":true}'; exit;
}
if ($input['action'] !== 'setup') throw new RuntimeException('fixture_action_invalid');
$company = Company::create(['name' => 'Nusantara Signage - Demo', 'code' => $code, 'is_active' => true]);
$other = Company::create(['name' => 'Workspace Kedua - Demo', 'code' => $code.'B', 'is_active' => true]);
$password = bin2hex(random_bytes(16));
$user = User::factory()->create(['name' => 'Admin Demo', 'email' => $tag.'@dashboard.test', 'password' => Hash::make($password), 'company_id' => $company->id, 'email_verified_at' => now()]);
$user->assignRole(Role::findByName('super-admin', 'web'));
foreach (['dashboard.view', 'devices.view', 'playlists.view', 'scheduler.view', 'media.view', 'activity_logs.view', 'users.view', 'companies.view', 'roles.view', 'permissions.view', 'menus.view', 'settings.view', 'login_histories.view'] as $permission) {
    if (Permission::where('name', $permission)->exists()) $user->givePermissionTo($permission);
}
$playlist = Playlist::create(['company_id' => $company->id, 'name' => 'Promosi Lobby', 'is_active' => true]);
$playlist->pages()->create(['name' => 'Page 1', 'duration_seconds' => 15]);
Schedule::create(['company_id' => $company->id, 'playlist_id' => $playlist->id, 'name' => 'Jam Operasional Lobby', 'schedule_type' => 'once', 'timezone' => 'Asia/Jakarta', 'starts_at_utc' => now()->subHour(), 'ends_at_utc' => now()->addHours(2), 'is_active' => true]);
foreach (['Layar Lobby', 'Layar Kasir', 'Layar Ruang Tunggu'] as $i => $name) {
    $device = Device::create(['company_id' => $company->id, 'name' => $name, 'platform' => $i === 2 ? 'android' : 'windows']);
    if ($i < 2) {
        $device->forceFill(['status' => 'active', 'last_seen_at' => $i ? now()->subHours(2) : now()->subSeconds(15)])->save();
        $device->credentials()->create(['token_identifier' => bin2hex(random_bytes(16)), 'token_hash' => hash('sha256', random_bytes(32))]);
    }
}
ActivityLog::create(['company_id' => $company->id, 'user_id' => $user->id, 'action' => 'create', 'module' => 'playlist', 'description' => 'Playlist Promosi Lobby disiapkan untuk penayangan.']);
echo json_encode(['email' => $user->email, 'password' => $password, 'company' => $company->id, 'other' => $other->id, 'playlist' => $playlist->id]);
