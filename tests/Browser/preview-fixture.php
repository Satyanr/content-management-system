<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
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
if (! is_string($tag) || ! preg_match('/^[a-f0-9]{12}$/D', $tag)) {
    throw new RuntimeException('fixture_tag_invalid');
}
$code = 'PHF-'.strtoupper($tag);
if ($input['action'] === 'cleanup') {
    foreach (Company::whereIn('code', [$code, $code.'B'])->get() as $company) {
        foreach (MediaAsset::where('company_id', $company->id)->get() as $asset) {
            if (str_starts_with($asset->path, 'testing/preview-hotfix/'.$tag.'/')) {
                Storage::disk('local')->delete($asset->path);
            }
        }
        Playlist::where('company_id', $company->id)->delete();
        foreach (User::where('company_id', $company->id)->get() as $user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('login_histories')->where('user_id', $user->id)->delete();
            $user->delete();
        }
        ActivityLog::where('company_id', $company->id)->delete();
        $company->delete();
    }
    echo '{"cleaned":true}';
    exit;
}
abort_unless($input['action'] === 'setup', 422);
$bytes = base64_decode($input['video'], true);
if (! is_string($bytes) || strlen($bytes) >= 2000000 || ! str_contains(substr($bytes, 0, 100), 'ftyp')) {
    throw new RuntimeException('fixture_video_invalid');
}
$company = Company::create(['code' => $code, 'name' => 'Preview browser fixture', 'is_active' => true]);
$other = Company::create(['code' => $code.'B', 'name' => 'Preview foreign fixture', 'is_active' => true]);
$password = bin2hex(random_bytes(16));
$user = new User;
$user->forceFill(['name' => 'Preview browser fixture', 'email' => $tag.'@preview.test', 'password' => Hash::make($password), 'company_id' => $company->id, 'email_verified_at' => now()])->save();
$user->givePermissionTo(['dashboard.view', 'playlists.view', 'playlists.edit', 'media.view']);
$path = 'testing/preview-hotfix/'.$tag.'/valid.mp4';
Storage::disk('local')->put($path, $bytes);
$asset = MediaAsset::create(['company_id' => $company->id, 'title' => 'H264 browser fixture', 'type' => 'video', 'mime_type' => 'video/mp4', 'file_name' => 'valid.mp4', 'original_name' => 'valid.mp4', 'disk' => 'local', 'path' => $path, 'size' => strlen($bytes), 'is_active' => true]);
Storage::disk('local')->put('testing/preview-hotfix/'.$tag.'/invalid.mp4', 'invalid mp4 container');
$invalid = $asset->replicate();
$invalid->fill(['title' => 'Invalid video fixture', 'path' => 'testing/preview-hotfix/'.$tag.'/invalid.mp4', 'size' => 21])->save();
$foreign = MediaAsset::create(['company_id' => $other->id, 'title' => 'Foreign fixture', 'type' => 'video', 'mime_type' => 'video/mp4', 'file_name' => 'foreign.mp4', 'original_name' => 'foreign.mp4', 'disk' => 'local', 'path' => $path, 'is_active' => true]);
$playlist = Playlist::create(['company_id' => $company->id, 'name' => 'Preview H264 browser fixture', 'canvas_width' => 640, 'canvas_height' => 360, 'background_color' => '#112233', 'is_active' => true]);
$page = $playlist->pages()->create(['name' => 'Page 1', 'duration_seconds' => 15, 'sort_order' => 1]);
foreach ([null, 4] as $index => $duration) {
    $zone = $page->zones()->create(['name' => $duration ? 'Manual' : 'Auto', 'x' => $index * 320, 'y' => 0, 'width' => 320, 'height' => 360, 'z_index' => $index, 'is_visible' => true, 'fit_mode' => 'contain']);
    $zone->items()->create(['media_asset_id' => $asset->id, 'duration_seconds' => $duration, 'sort_order' => 1]);
}
$cases = [];
foreach (['invalid' => $invalid, 'timeout' => $asset] as $case => $source) {
    $testPlaylist = $playlist->replicate();
    $testPlaylist->name = 'Preview '.$case.' fixture';
    $testPlaylist->save();
    $testPage = $testPlaylist->pages()->create(['name' => 'Page 1', 'duration_seconds' => 15]);
    $testZone = $testPage->zones()->create(['name' => 'Auto', 'width' => 640, 'height' => 360, 'is_visible' => true]);
    $testZone->items()->create(['media_asset_id' => $source->id, 'duration_seconds' => null]);
    $cases[$case] = $testPlaylist->id;
}
echo json_encode(['email' => $user->email, 'password' => $password, 'playlist' => $playlist->id, 'media' => $asset->id, 'foreign' => $foreign->id] + $cases);
