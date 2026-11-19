<?php

function acceptanceSchemaGrantIsLiteral(string $scope, string $database, bool $partialRevokes): bool
{
    $name = $partialRevokes ? $database : str_replace('_', '\\_', $database);

    return strcasecmp(str_replace('`', '', $scope), $name.'.*') === 0;
}

/** Validate every bypass prerequisite before writing its audit event. */
function acceptanceGrantInspectionBypassed(string|false $flag, string $environment, array $configured, array $mainEnv, array $acceptance, callable $identity, callable $audit): bool
{
    if ($flag !== '1') return false;
    if (! in_array($environment, ['local', 'testing'], true)
        || in_array(strtolower((string) ($mainEnv['APP_ENV'] ?? '')), ['production', 'prod', 'staging', 'stage'], true)) {
        throw new RuntimeException('fixture_environment_invalid');
    }
    $database = $acceptance['database'] ?? '';
    $user = $acceptance['username'] ?? '';
    $allowlist = array_filter(array_map('trim', explode(',', $acceptance['allowlist'] ?? '')));
    $port = (string) ($acceptance['port'] ?? '');
    if (! preg_match('/^[a-zA-Z0-9_]+_acceptance_test$/D', $database)
        || ! in_array($database, $allowlist, true)
        || empty($mainEnv['DB_DATABASE']) || ! empty($mainEnv['DB_URL'])
        || strcasecmp($mainEnv['DB_DATABASE'], $database) === 0
        || ($configured['database'] ?? null) !== $database
        || ($configured['host'] ?? null) !== '127.0.0.1'
        || ($acceptance['host'] ?? null) !== '127.0.0.1'
        || ! preg_match('/^[1-9][0-9]{0,4}$/D', $port) || (int) $port > 65535
        || (string) ($configured['port'] ?? '') !== $port
        || ! preg_match('/^[a-zA-Z0-9_]+$/D', $user) || strtolower($user) === 'root'
        || ($configured['username'] ?? null) !== $user
        || ! empty($configured['url']) || ! empty($configured['unix_socket'])) {
        throw new RuntimeException('fixture_database_not_isolated');
    }
    $actual = $identity();
    $account = explode('@', (string) ($actual->account ?? ''), 2);
    if (($actual->database_name ?? null) !== $database || (string) ($actual->server_port ?? '') !== $port
        || count($account) !== 2 || $account[0] !== $user || $account[1] === '') {
        throw new RuntimeException('fixture_database_identity_invalid');
    }
    $audit($database, $account[0]);
    return true;
}
