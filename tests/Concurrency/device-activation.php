<?php

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Device;
use App\Services\DeviceLifecycle;
use Illuminate\Contracts\Console\Kernel;

// Standalone MySQL row-lock test. Never wipes/truncates; removes only its own random fixture.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? '') === '--worker') {
    try {
        $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        while (microtime(true) < $input['start']) {
            usleep(1000);
        }
        $result = app(DeviceLifecycle::class)->activate($input['payload']);
        // Do not output credential or code, even in this isolated test.
        echo json_encode(['activated' => $result !== null]);
        exit(0);
    } catch (Throwable) {
        echo json_encode(['error' => true]);
        exit(1);
    }
}
if (! $app->environment(['local', 'testing']) || config('database.default') !== 'mysql') {
    fwrite(STDERR, "SKIP: requires local/testing MySQL and proc_open.\n");
    exit(2);
}
if (! function_exists('proc_open')) {
    fwrite(STDERR, "SKIP: proc_open unavailable.\n");
    exit(2);
}
$tag = 'RACE-'.strtoupper(bin2hex(random_bytes(8)));
$company = null;
$processes = [];
$passed = false;
try {
    $company = Company::create(['name' => 'Device activation concurrency fixture', 'code' => $tag, 'is_active' => true]);
    $device = Device::create(['company_id' => $company->id, 'name' => 'Concurrent fixture', 'platform' => 'windows']);
    $code = app(DeviceLifecycle::class)->issue($company->id, $device->id);
    $payload = ['activation_code' => $code, 'platform' => 'windows', 'player_version' => 'concurrency-test', 'screen_width' => 1920, 'screen_height' => 1080];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start workers.');
        }
        $processes[] = ['process' => $process, 'pipes' => $pipes];
    }
    $start = microtime(true) + 1;
    foreach ($processes as $entry) {
        fwrite($entry['pipes'][0], json_encode(compact('start', 'payload')));
        fclose($entry['pipes'][0]);
    }
    $successes = 0;
    foreach ($processes as $entry) {
        $result = json_decode(stream_get_contents($entry['pipes'][1]), true);
        fclose($entry['pipes'][1]);
        stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][2]);
        $exit = proc_close($entry['process']);
        if ($exit !== 0 || isset($result['error'])) {
            throw new RuntimeException('Worker failed.');
        }$successes += (int) ($result['activated'] ?? false);
    }
    $processes = [];
    $credentials = $device->credentials()->count();
    $consumed = $device->pairingCodes()->whereNotNull('consumed_at')->count();
    $passed = $successes === 1 && $credentials === 1 && $consumed === 1;
    echo json_encode(['test' => 'mysql_activation_race', 'passed' => $passed, 'successful_requests' => $successes, 'credentials' => $credentials, 'consumed_codes' => $consumed]).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Concurrency test failed: '.get_class($e).PHP_EOL);
} finally {
    foreach ($processes as $entry) {
        if (is_resource($entry['process'])) {
            proc_terminate($entry['process']);
            proc_close($entry['process']);
        }
    }
    if ($company && Company::whereKey($company->id)->where('code', $tag)->exists()) {
        ActivityLog::where('company_id', $company->id)->where('module', 'devices')->delete();
        Company::whereKey($company->id)->where('code', $tag)->delete();
    }
}
exit($passed ? 0 : 1);
