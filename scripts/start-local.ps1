param([switch]$SkipBrowser)
$ErrorActionPreference = 'Stop'
$vmasApplication = Split-Path $PSScriptRoot
$vmasWorkspace = Split-Path $vmasApplication
$vmasTools = Join-Path $vmasWorkspace '.tools'
$vmasPhp = Join-Path $vmasTools 'php/php.exe'
$vmasMysql = Join-Path $vmasTools 'mysql/mysql-8.4.13-winx64/bin/mysqld.exe'
if (!(Test-Path -LiteralPath $vmasPhp) -or !(Test-Path -LiteralPath $vmasMysql)) { throw 'Local runtimes are missing. Follow docs/INSTALLATION.md.' }
if (!(Test-Path -LiteralPath (Join-Path $vmasApplication '.env'))) { throw 'Configure application/.env first.' }
if (!(Test-Path -LiteralPath (Join-Path $vmasApplication 'public/build/manifest.json'))) { throw 'Run npm run build in application/ first.' }
$vmasPidFile = Join-Path $vmasTools 'mysql.pid'
$vmasRunning = $false
if (Test-Path -LiteralPath $vmasPidFile) {
    $vmasProcess = Get-Process -Id ([int](Get-Content -LiteralPath $vmasPidFile)) -ErrorAction SilentlyContinue
    $vmasRunning = $vmasProcess -and ($vmasProcess.Path -eq $vmasMysql.Replace('/', '\'))
}
if (!$vmasRunning) {
    $vmasBase = (Split-Path (Split-Path $vmasMysql)).Replace('\', '/')
    $vmasData = (Join-Path $vmasTools 'mysql-data').Replace('\', '/')
    if (!(Test-Path -LiteralPath $vmasData)) { throw 'The dedicated local database is not initialized. See docs/INSTALLATION.md.' }
    $vmasProcess = Start-Process -FilePath $vmasMysql -ArgumentList '--no-defaults', "--basedir=`"$vmasBase`"", "--datadir=`"$vmasData`"", '--bind-address=127.0.0.1', '--port=3307', '--mysqlx=0', '--skip-log-bin', '--console' -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $vmasTools 'mysql-stdout.log') -RedirectStandardError (Join-Path $vmasTools 'mysql-stderr.log')
    $vmasProcess.Id | Set-Content -LiteralPath $vmasPidFile
    Start-Sleep -Seconds 3
    if ($vmasProcess.HasExited) { throw 'MySQL could not start. Check .tools/mysql-stderr.log. Existing services are never stopped by this script.' }
}
Push-Location $vmasApplication
try {
    & $vmasPhp artisan vmas:status
    if ($LASTEXITCODE -ne 0) { throw 'VMAS database is unavailable.' }
    & $vmasPhp artisan vmas:needs-admin
    if ($LASTEXITCODE -eq 10) {
        & $vmasPhp artisan vmas:create-admin
        if ($LASTEXITCODE -ne 0) { throw 'Administrator setup was not completed.' }
    }
    $vmasServerPidFile = Join-Path $vmasTools 'app-server.pid'
    $vmasServer = $null
    if (Test-Path -LiteralPath $vmasServerPidFile) {
        $vmasCandidate = Get-CimInstance Win32_Process -Filter ('ProcessId=' + [int](Get-Content -LiteralPath $vmasServerPidFile))
        if ($vmasCandidate -and $vmasCandidate.ExecutablePath -eq $vmasPhp.Replace('/', '\') -and $vmasCandidate.CommandLine -match '127\.0\.0\.1:8000') { $vmasServer = $vmasCandidate }
    }
    if (!$vmasServer) {
        $vmasListener = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
        if ($vmasListener) { throw 'Port 8000 is already occupied by an unrecognized process. No process was stopped.' }
        $vmasServer = Start-Process -FilePath $vmasPhp -ArgumentList '-S', '127.0.0.1:8000', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php' -WorkingDirectory (Join-Path $vmasApplication 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $vmasTools 'app-server-stdout.log') -RedirectStandardError (Join-Path $vmasTools 'app-server-stderr.log')
        $vmasServer.Id | Set-Content -LiteralPath $vmasServerPidFile
    }
    $vmasHealthy = $false
    for ($vmasAttempt = 0; $vmasAttempt -lt 5; $vmasAttempt++) {
        try {
            $vmasPage = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/login' -TimeoutSec 5
            $vmasHealthy = $vmasPage.StatusCode -eq 200 -and $vmasPage.Content.Contains('Welcome back') -and $vmasPage.Content.Contains('VMAS')
        } catch { $vmasHealthy = $false }
        if ($vmasHealthy) { break }
        Start-Sleep -Seconds 1
    }
    if (!$vmasHealthy) { throw 'The VMAS login page failed its health check. Inspect .tools/app-server-stderr.log.' }
    & $vmasPhp artisan vmas:prune-imports --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Import retention cleanup failed.' }
    $vmasSchedulerPidFile = Join-Path $vmasTools 'scheduler.pid'
    $vmasScheduler = $null
    if (Test-Path -LiteralPath $vmasSchedulerPidFile) {
        $vmasCandidate = Get-CimInstance Win32_Process -Filter ('ProcessId=' + [int](Get-Content -LiteralPath $vmasSchedulerPidFile))
        if ($vmasCandidate -and $vmasCandidate.ExecutablePath -eq $vmasPhp.Replace('/', '\') -and $vmasCandidate.CommandLine -match 'schedule:work') { $vmasScheduler = $vmasCandidate }
    }
    if (!$vmasScheduler) {
        $vmasScheduler = Start-Process -FilePath $vmasPhp -ArgumentList 'artisan','schedule:work' -WorkingDirectory $vmasApplication -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $vmasTools 'scheduler-stdout.log') -RedirectStandardError (Join-Path $vmasTools 'scheduler-stderr.log')
        $vmasScheduler.Id | Set-Content -LiteralPath $vmasSchedulerPidFile
    }
    Write-Host 'VMAS is ready: http://127.0.0.1:8000 (local development only).'
    Write-Host 'The web server and daily import cleanup run in the background. Running this script again reuses them.'
    if (!$SkipBrowser) { Start-Process 'http://127.0.0.1:8000/vendors' }
} finally { Pop-Location }
