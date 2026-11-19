<?php

require_once __DIR__.'/../Browser/acceptance-db-guard.php';

test('acceptance database grants cannot use underscore wildcards', function () {
    expect(acceptanceSchemaGrantIsLiteral('`cms\\_acceptance\\_test`.*', 'cms_acceptance_test', false))->toBeTrue();
    expect(acceptanceSchemaGrantIsLiteral('`cms_acceptance_test`.*', 'cms_acceptance_test', false))->toBeFalse();
    expect(acceptanceSchemaGrantIsLiteral('`cms_acceptance_test`.*', 'cms_acceptance_test', true))->toBeTrue();
    expect(acceptanceSchemaGrantIsLiteral('*.*', 'cms_acceptance_test', true))->toBeFalse();
});

function bypassFixture(array $changes = []): array
{
    $config = ['database' => 'cms_acceptance_test', 'host' => '127.0.0.1', 'port' => '3306', 'username' => 'cms_acceptance_user', 'url' => '', 'unix_socket' => ''];
    $acceptance = ['database' => 'cms_acceptance_test', 'host' => '127.0.0.1', 'port' => '3306', 'username' => 'cms_acceptance_user', 'allowlist' => 'cms_acceptance_test'];
    return array_replace(['flag' => '1', 'environment' => 'testing', 'configured' => $config, 'mainEnv' => ['APP_ENV' => 'local', 'DB_DATABASE' => 'cms_main'], 'acceptance' => $acceptance,
        'actual' => (object) ['database_name' => 'cms_acceptance_test', 'account' => 'cms_acceptance_user@localhost', 'server_port' => 3306]], $changes);
}

test('grant bypass checks connection identity and logs only after successful validation', function () {
    $f = bypassFixture(); $events = [];
    $result = acceptanceGrantInspectionBypassed($f['flag'], $f['environment'], $f['configured'], $f['mainEnv'], $f['acceptance'], fn () => $f['actual'], function ($db, $user) use (&$events) { $events[] = [$db, $user]; });
    expect($result)->toBeTrue()->and($events)->toBe([['cms_acceptance_test', 'cms_acceptance_user']]);
    $f['flag'] = false;
    expect(acceptanceGrantInspectionBypassed($f['flag'], $f['environment'], $f['configured'], $f['mainEnv'], $f['acceptance'], fn () => throw new Exception('unexpected connection'), fn () => throw new Exception('unexpected log')))->toBeFalse();
});

test('grant bypass rejects unsafe environment, allowlist and connection identities', function (callable $modify, string $message) {
    $f = bypassFixture(); $modify($f); $logged = false;
    try {
        acceptanceGrantInspectionBypassed($f['flag'], $f['environment'], $f['configured'], $f['mainEnv'], $f['acceptance'], fn () => $f['actual'], function () use (&$logged) { $logged = true; });
        $this->fail('Unsafe bypass was accepted');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe($message)->and($logged)->toBeFalse();
    }
})->with([
    'production' => [function (&$f) { $f['environment'] = 'production'; }, 'fixture_environment_invalid'],
    'staging' => [function (&$f) { $f['environment'] = 'staging'; }, 'fixture_environment_invalid'],
    'production main configuration' => [function (&$f) { $f['mainEnv']['APP_ENV'] = 'production'; }, 'fixture_environment_invalid'],
    'missing allowlist' => [function (&$f) { $f['acceptance']['allowlist'] = ''; }, 'fixture_database_not_isolated'],
    'not allowlisted' => [function (&$f) { $f['acceptance']['allowlist'] = 'other_acceptance_test'; }, 'fixture_database_not_isolated'],
    'main database' => [function (&$f) { $f['mainEnv']['DB_DATABASE'] = 'cms_acceptance_test'; }, 'fixture_database_not_isolated'],
    'nonlocal host' => [function (&$f) { $f['configured']['host'] = 'remote'; }, 'fixture_database_not_isolated'],
    'socket bypass' => [function (&$f) { $f['configured']['unix_socket'] = 'other'; }, 'fixture_database_not_isolated'],
    'root user' => [function (&$f) { $f['configured']['username'] = $f['acceptance']['username'] = 'root'; }, 'fixture_database_not_isolated'],
    'connection database mismatch' => [function (&$f) { $f['actual']->database_name = 'cms_main'; }, 'fixture_database_identity_invalid'],
    'connection user mismatch' => [function (&$f) { $f['actual']->account = 'root@localhost'; }, 'fixture_database_identity_invalid'],
    'connection port mismatch' => [function (&$f) { $f['actual']->server_port = 3307; }, 'fixture_database_identity_invalid'],
]);

test('acceptance fixture rejects unsafe environments before it can touch a database', function (array $overrides, string $expected) {
    $environment = array_replace(getenv(), [
        'APP_ENV' => 'testing', 'CMS_REAL_ACCEPTANCE' => '1', 'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CMS_ACCEPTANCE_DB_DATABASE' => 'cms_acceptance_test',
    ], $overrides);
    $process = proc_open([PHP_BINARY, __DIR__.'/../Browser/always-on-fixture.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
    expect($process)->toBeResource();
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    expect($output)->toContain($expected)->not->toContain('{"safe":true}');
})->with([
    'sqlite instead of isolated MySQL' => [[], 'fixture_database_not_isolated'],
    'production environment' => [['APP_ENV' => 'production'], 'fixture_environment_invalid'],
]);
