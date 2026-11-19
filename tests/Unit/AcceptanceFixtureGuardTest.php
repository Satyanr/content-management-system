<?php

require_once __DIR__.'/../Browser/acceptance-db-guard.php';

test('acceptance database grants cannot use underscore wildcards', function () {
    expect(acceptanceSchemaGrantIsLiteral('`cms\\_acceptance\\_test`.*', 'cms_acceptance_test', false))->toBeTrue();
    expect(acceptanceSchemaGrantIsLiteral('`cms_acceptance_test`.*', 'cms_acceptance_test', false))->toBeFalse();
    expect(acceptanceSchemaGrantIsLiteral('`cms_acceptance_test`.*', 'cms_acceptance_test', true))->toBeTrue();
    expect(acceptanceSchemaGrantIsLiteral('*.*', 'cms_acceptance_test', true))->toBeFalse();
});

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
