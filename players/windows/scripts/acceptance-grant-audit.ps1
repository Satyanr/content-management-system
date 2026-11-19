param([Parameter(Mandatory=$true)][string]$StatusPath)
$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../../..')).Path
$expected = Join-Path $repo 'storage/app/phase15-grant-audit.json'
if ([IO.Path]::GetFullPath($StatusPath) -ne [IO.Path]::GetFullPath($expected) -or (Test-Path -LiteralPath $StatusPath)) { throw 'Invalid or existing audit path.' }
function Write-Audit($data) { $data | ConvertTo-Json -Depth 8 -Compress | Set-Content -LiteralPath $StatusPath -Encoding UTF8 }
try {
    Write-Audit @{stage='waiting_for_password'}
    Write-Host 'Audit read-only: masukkan password cms_acceptance_user secara tersembunyi.'
    $secret = Read-Host 'Password' -AsSecureString
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    try { $env:CMS_ACCEPTANCE_DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
    $env:APP_ENV='testing'; $env:CMS_REAL_ACCEPTANCE='1'; $env:DB_CONNECTION='mysql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='3306'; $env:DB_DATABASE='cms_acceptance_test'; $env:DB_USERNAME='cms_acceptance_user'; $env:DB_PASSWORD=$env:CMS_ACCEPTANCE_DB_PASSWORD; $env:DB_URL=''; $env:DB_SOCKET=''
    $env:CMS_ACCEPTANCE_DB_DATABASE='cms_acceptance_test'; $env:CMS_ACCEPTANCE_DB_USERNAME='cms_acceptance_user'; $env:CMS_ACCEPTANCE_DB_HOST='127.0.0.1'; $env:CMS_ACCEPTANCE_DB_PORT='3306'
    Remove-Item Env:ACCEPTANCE_SKIP_GRANT_INSPECTION -ErrorAction SilentlyContinue
    Set-Location -LiteralPath $repo
    $oldPreference=$ErrorActionPreference; $ErrorActionPreference='Continue'
    try { $auditText = & php tests/Browser/acceptance-grant-audit.php 2>&1 | Out-String; $auditCode=$LASTEXITCODE } finally { $ErrorActionPreference=$oldPreference }
    try { $audit=$auditText.Trim() | ConvertFrom-Json } catch { throw 'audit_failed' }
    if ($auditCode -ne 0 -or -not $audit.connected) { Write-Audit @{stage='failed';audit=$audit}; exit 1 }
    $payload=@{action='preflight';tag='000000000000';owner=('0'*64)} | ConvertTo-Json -Compress
    $oldPreference=$ErrorActionPreference; $ErrorActionPreference='Continue'
    try { $output=$payload | & php tests/Browser/always-on-fixture.php 2>&1 | Out-String } finally { $ErrorActionPreference=$oldPreference }
    try { $preflight=$output.Trim() | ConvertFrom-Json; $passed=$preflight.safe -eq $true } catch { $passed=$false }
    Write-Audit @{stage='complete';audit=$audit;preflight_without_bypass=$passed}
    Write-Host 'Audit selesai; status tersanitasi tersedia untuk Codex.'
} catch { Write-Audit @{stage='failed';error='audit_failed'}; Write-Host 'Audit tidak dapat diselesaikan.' }
finally { Remove-Item Env:CMS_ACCEPTANCE_DB_PASSWORD,Env:DB_PASSWORD -ErrorAction SilentlyContinue }
