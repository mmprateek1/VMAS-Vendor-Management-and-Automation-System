$ErrorActionPreference = 'Stop'
$vmasApplication = Split-Path $PSScriptRoot
$vmasPhp = Join-Path (Split-Path $vmasApplication) '.tools/php/php.exe'
Push-Location $vmasApplication
try {
    if (!(Test-Path '.env.testing')) { throw 'Configure the isolated vmas_test database in .env.testing first.' }
    & $vmasPhp artisan test
    if ($LASTEXITCODE -ne 0) { throw 'VMAS tests failed.' }
} finally { Pop-Location }
