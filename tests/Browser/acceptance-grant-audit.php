<?php

// Read-only audit: never print raw GRANT statements or connection errors.
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/acceptance-db-guard.php';
try {
    $main = \Dotenv\Dotenv::parse(file_get_contents(__DIR__.'/../../.env'));
    if (($main['APP_ENV'] ?? '') !== 'local' || ! empty($main['DB_URL']) || empty($main['DB_DATABASE']) || strcasecmp($main['DB_DATABASE'], 'cms_acceptance_test') === 0) throw new RuntimeException('main_configuration_unsafe');
    // Connect without choosing a database first, so a denied USE can still be diagnosed read-only.
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'cms_acceptance_user', getenv('CMS_ACCEPTANCE_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $identity = $pdo->query('SELECT CURRENT_USER() AS account, @@port AS port')->fetch(PDO::FETCH_ASSOC);
    if ((int) $identity['port'] !== 3306 || explode('@', $identity['account'], 2)[0] !== 'cms_acceptance_user') throw new RuntimeException('identity_mismatch');
    $variable = $pdo->query("SHOW VARIABLES LIKE 'partial_revokes'")->fetch(PDO::FETCH_ASSOC);
    $partial = strtoupper((string) ($variable['Value'] ?? 'OFF')) === 'ON';
    $grants = []; $target = false; $unsafe = false;
    foreach ($pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_NUM) as $row) {
        if (! preg_match('/^GRANT (.+?) ON (.+?) TO /i', $row[0], $m)) { $grants[] = ['scope' => 'role_or_unrecognized']; $unsafe = true; continue; }
        $privileges = strtoupper(trim($m[1])); $scope = str_replace('`', '', $m[2]);
        if (! preg_match('/^[A-Z_, ()]+$/D', $privileges)) { $grants[] = ['scope' => 'role_or_unrecognized']; $unsafe = true; continue; }
        if ($scope === '*.*' && $privileges === 'USAGE') $kind = 'global_usage_only';
        elseif ($scope === '*.*') { $kind = 'global_privileges'; $unsafe = true; }
        elseif (acceptanceSchemaGrantIsLiteral($scope, 'cms_acceptance_test', $partial)) { $kind = 'acceptance_schema_literal'; $target = true; }
        else { $kind = 'other_or_wildcard_scope'; $unsafe = true; }
        $grants[] = ['scope' => $kind, 'privileges' => $privileges];
    }
    $databaseAccess = false; $databaseCode = null;
    try {
        $pdo->exec('USE `cms_acceptance_test`');
        $databaseAccess = $pdo->query('SELECT DATABASE()')->fetchColumn() === 'cms_acceptance_test';
    } catch (PDOException $e) {
        $databaseCode = isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
    }
    echo json_encode(['connected' => true, 'database_matches' => $databaseAccess, 'database_access_error' => $databaseCode, 'account_matches' => true, 'port_matches' => true, 'partial_revokes' => $partial, 'grants' => $grants, 'restricted_to_acceptance' => $target && ! $unsafe], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['connected' => false, 'error' => $e instanceof PDOException ? 'connection_or_query_failed' : $e->getMessage(),
        'driver_code' => $e instanceof PDOException && isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null]);
    exit(1);
}
