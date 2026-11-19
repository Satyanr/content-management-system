param([string]$StatusPath, [switch]$SkipGrantInspection)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../../..')).Path
$player = Join-Path $repo 'players/windows'
if (-not $StatusPath) { throw 'Status path is required.' }
$allowedStatus = Join-Path $repo 'storage/app/phase1-acceptance-status.json'
if ([IO.Path]::GetFullPath($StatusPath) -ne [IO.Path]::GetFullPath($allowedStatus) -or (Test-Path -LiteralPath $StatusPath)) { throw 'Invalid or already existing status path.' }
function Set-Status([string]$stage, [string]$message) {
    @{ stage = $stage; message = $message } | ConvertTo-Json -Compress | Set-Content -LiteralPath $StatusPath -Encoding UTF8
}
function Invoke-Fixture([string]$action) {
    $payload = @{ action = $action; tag = '000000000000'; owner = ('0' * 64) } | ConvertTo-Json -Compress
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $output = $payload | & php (Join-Path $repo 'tests/Browser/always-on-fixture.php') 2>&1 | Out-String
    } finally { $ErrorActionPreference = $previousPreference }
    try { return $output.Trim() | ConvertFrom-Json } catch {
        $code = if ($output -match 'fixture_(environment_invalid|database_not_isolated|database_identity_invalid|database_grants_invalid|tag_invalid)') { $Matches[0] }
        elseif ($output -match '\b(1045|1049|2002|2003|2005)\b') { 'mysql_' + $Matches[1] }
        else { 'unclassified' }
        throw ('Database preflight or permission seed failed: ' + $code)
    }
}

try {
    Set-Status 'waiting_for_password' 'Waiting for local password input.'
    Write-Host 'Fase 1: masukkan password MySQL untuk cms_acceptance_user di 127.0.0.1.'
    Write-Host 'Password hanya disimpan dalam proses ini dan tidak ditampilkan.'
    $secret = Read-Host 'Password' -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    try { $env:CMS_ACCEPTANCE_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
    $env:CMS_REAL_ACCEPTANCE = '1'
    $env:CMS_ACCEPTANCE_DB_HOST = '127.0.0.1'
    $env:CMS_ACCEPTANCE_DB_PORT = '3306'
    $env:CMS_ACCEPTANCE_DB_DATABASE = 'cms_acceptance_test'
    $env:CMS_ACCEPTANCE_DB_USERNAME = 'cms_acceptance_user'
    $env:CMS_ACCEPTANCE_DISPOSABLE_DATABASES = 'cms_acceptance_test'
    if ($SkipGrantInspection) { $env:ACCEPTANCE_SKIP_GRANT_INSPECTION = '1' }
    else { Remove-Item Env:ACCEPTANCE_SKIP_GRANT_INSPECTION -ErrorAction SilentlyContinue }
    $env:APP_ENV = 'testing'
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = $env:CMS_ACCEPTANCE_DB_HOST
    $env:DB_PORT = $env:CMS_ACCEPTANCE_DB_PORT
    $env:DB_DATABASE = $env:CMS_ACCEPTANCE_DB_DATABASE
    $env:DB_USERNAME = $env:CMS_ACCEPTANCE_DB_USERNAME
    $env:DB_PASSWORD = $env:CMS_ACCEPTANCE_DB_PASSWORD
    $env:DB_URL = ''
    $env:DB_SOCKET = ''
    $env:CACHE_STORE = 'array'
    $env:SESSION_DRIVER = 'database'
    $env:QUEUE_CONNECTION = 'sync'
    $env:MAIL_MAILER = 'array'
    $fixtureVideo = Join-Path $repo 'storage/app/phase1-acceptance-bear.mp4'
    if (Test-Path -LiteralPath $fixtureVideo) { $env:CMS_ACCEPTANCE_VIDEO_PATH = $fixtureVideo }
    Set-Location -LiteralPath $repo
    Set-Status 'preflight' 'Checking the isolated database and account grants.'
    $preflight = Invoke-Fixture 'preflight'
    if ($preflight.safe -ne $true -or ($preflight.table_count -ne 0 -and $preflight.schema_ready -ne $true)) { throw 'Database preflight failed or the existing schema is incomplete; nothing was migrated.' }
    if (Test-Path -LiteralPath (Join-Path $repo 'public/hot')) { throw 'public/hot exists; stop Vite normally before acceptance.' }
    if ($preflight.table_count -eq 0) {
        Set-Status 'migration' 'Running normal migrations on the verified empty acceptance schema.'
        $migrationOutput = & php artisan migrate --force 2>&1 | Out-String
        if ($LASTEXITCODE -ne 0) { throw 'Migration failed; the acceptance schema may be partially initialized. No automatic reset was attempted.' }
        Set-Status 'permissions' 'Creating only the permissions required by the fixture.'
        $seed = Invoke-Fixture 'seed-permissions'
        if ($seed.seeded -ne $true) { throw 'Acceptance permission setup failed.' }
    }
    Set-Status 'acceptance' 'Running the real Laravel and Electron acceptance test.'
    Set-Location -LiteralPath $player
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $testOutput = & npx.cmd playwright test tests/e2e/always-on-localhost.spec.js 2>&1 | Out-String
    } finally { $ErrorActionPreference = $previousPreference }
    if ($LASTEXITCODE -ne 0) { throw 'Real acceptance failed. Inspect local Playwright results after checking them for secrets.' }
    if ($testOutput -notmatch '"realLaravel":true' -or $testOutput -notmatch '"realElectron":true') { throw 'Acceptance completed without expected evidence; result is inconclusive.' }
    Set-Status 'passed' 'Real Laravel and Electron acceptance passed. Installer build remains a separate verified step.'
    Write-Host 'Acceptance selesai. Status: passed.'
} catch {
    Set-Status 'failed' $_.Exception.Message
    Write-Host ('Acceptance berhenti: ' + $_.Exception.Message)
} finally {
    Remove-Item Env:CMS_ACCEPTANCE_DB_PASSWORD, Env:DB_PASSWORD -ErrorAction SilentlyContinue
    Write-Host 'Anda dapat menutup jendela ini.'
}
