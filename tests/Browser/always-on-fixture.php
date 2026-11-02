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
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment(['local', 'testing'])) {
    throw new RuntimeException('fixture_environment_invalid');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$tag = $input['tag'] ?? '';
if (! preg_match('/^[a-f0-9]{12}$/D', $tag)) {
    throw new RuntimeException('fixture_tag_invalid');
}$code = 'AOP-'.strtoupper($tag);
if ($input['action'] === 'cleanup') {
    foreach (Company::where('code', $code)->get() as $company) {
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
    $company = Company::where('code', $code)->firstOrFail();
    $events = DB::table('playback_events')->where('company_id', $company->id);
    $counts = (clone $events)->where('status', 'completed')->selectRaw('playlist_zone_item_id,COUNT(*) as plays')->groupBy('playlist_zone_item_id')->get();
    $device = Device::where('company_id', $company->id)->firstOrFail();
    echo json_encode(['counts' => $counts, 'events' => $events->count(), 'applied' => $device->reported_playlist_id === $device->primary_playlist_id && $device->reported_command_id === $device->playlist_command_id, 'historyRevision' => DB::table('device_playlist_changes')->where('company_id',$company->id)->where('device_id',$device->id)->where('source','player_report')->whereNotNull('manifest_revision')->exists(), 'pending' => $device->pending_playback_events]);
    exit;
}
if ($input['action'] !== 'setup') {
    throw new RuntimeException('fixture_action_invalid');
}
$company = Company::create(['name' => 'Always-On Acceptance', 'code' => $code, 'is_active' => true]);
$password = bin2hex(random_bytes(16));
$user = new User;
$user->forceFill(['company_id' => $company->id, 'name' => 'Acceptance Operator', 'email' => $tag.'@always-on.test', 'password' => Hash::make($password), 'email_verified_at' => now()])->save();
$user->givePermissionTo(['dashboard.view', 'playlists.view', 'devices.view', 'devices.assign', 'playback.view', 'playback.export', 'playback.reset', 'media.view']);
Auth::setUser($user);
$playlist = Playlist::create(['company_id' => $company->id, 'name' => 'Always-On Real Playback', 'canvas_width' => 640, 'canvas_height' => 360, 'background_color' => '#102030', 'is_active' => true]);
$page = $playlist->pages()->create(['name' => 'Parallel Windows', 'duration_seconds' => 15]);
$bytes = base64_decode($input['video'] ?? '', true);
if (! is_string($bytes) || strlen($bytes) > 2000000 || ! str_contains(substr($bytes, 0, 100), 'ftyp')) {
    throw new RuntimeException('fixture_video_invalid');
}
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=');
$ids = [];
foreach ([['video', $bytes, 'mp4', 3], ['image', $png, 'png', 5]] as $i => [$type,$content,$ext,$max]) {
    $path = 'testing/always-on/'.$tag.'/media.'.$ext;
    Storage::disk('local')->put($path, $content);
    $asset = MediaAsset::create(['company_id' => $company->id, 'title' => 'Actual '.$type, 'type' => $type, 'mime_type' => $type === 'video' ? 'video/mp4' : 'image/png', 'file_name' => 'media.'.$ext, 'original_name' => 'media.'.$ext, 'path' => $path, 'disk' => 'local', 'size' => strlen($content), 'is_active' => true]);
    $zone = $page->zones()->create(['name' => 'Window '.($i + 1), 'x' => $i * 320, 'y' => 0, 'width' => 320, 'height' => 360, 'is_visible' => true, 'fit_mode' => 'contain']);
    $item = $zone->items()->create(['media_asset_id' => $asset->id, 'duration_seconds' => $type === 'image' ? 1 : null, 'max_plays' => $max]);
    $ids[$type] = $item->id;
}
$device = Device::create(['company_id' => $company->id, 'name' => 'Real Windows Acceptance', 'platform' => 'windows']);
app(DevicePlaylistAssignment::class)->apply($playlist->id, [$device->id]);
$activation = app(DeviceLifecycle::class)->issue($company->id, $device->id);
echo json_encode(['email' => $user->email, 'password' => $password, 'activation' => $activation, 'ids' => $ids]);
