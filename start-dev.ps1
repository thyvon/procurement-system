<#
.SYNOPSIS
  Long-term dev stack: PHP API (artisan serve :8000) + queue worker
  (database queue) + Cloudflare tunnel (Telegram bot webhook ingress for
  mjqe-dev.systemsolution.online).

  Usage:
    powershell -ExecutionPolicy Bypass -File start-dev.ps1          # start (idempotent)
    powershell -ExecutionPolicy Bypass -File start-dev.ps1 -Stop    # stop all three

  The tunnel token lives outside the repo (user profile) so it can never
  be committed: C:\Users\vun.thy\cloudflared\procurement-webhook.token
#>
param([switch]$Stop)

$apiDir       = Join-Path $PSScriptRoot 'apps\api'
$php          = (Get-Command php -ErrorAction Stop).Source
$cloudflared  = 'C:\Users\vun.thy\cloudflared\cloudflared.exe'
$tokenFile    = 'C:\Users\vun.thy\cloudflared\procurement-webhook.token'
$tunnelLog    = 'C:\Users\vun.thy\cloudflared\dev-tunnel.log'
$workerLog    = 'C:\Users\vun.thy\cloudflared\dev-worker.log'
$publicUrl    = 'https://mjqe-dev.systemsolution.online'

function Get-ApiProcess {
    (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1)
}

function Get-TunnelProcess {
    Get-CimInstance Win32_Process -Filter "Name = 'cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match 'tunnel' } | Select-Object -First 1
}

function Get-WorkerProcess {
    Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match 'queue:work' } | Select-Object -First 1
}

if ($Stop) {
    $api = Get-ApiProcess
    if ($api) { Stop-Process -Id $api.OwningProcess -Force -ErrorAction SilentlyContinue; 'Stopped API (port 8000).' }
    else { 'API not running.' }
    $worker = Get-WorkerProcess
    if ($worker) { Stop-Process -Id $worker.ProcessId -Force -ErrorAction SilentlyContinue; 'Stopped queue worker.' }
    else { 'Queue worker not running.' }
    $tun = Get-TunnelProcess
    if ($tun) { Stop-Process -Id $tun.ProcessId -Force -ErrorAction SilentlyContinue; 'Stopped cloudflared tunnel.' }
    else { 'Tunnel not running.' }
    exit 0
}

if (-not (Get-ApiProcess)) {
    Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', '--port=8000') `
        -WorkingDirectory $apiDir -WindowStyle Hidden
    'API starting on http://127.0.0.1:8000 ...'
} else { 'API already running on port 8000.' }

if (-not (Get-WorkerProcess)) {
    Start-Process -FilePath $php -ArgumentList @('artisan', 'queue:work', '--queue=default,telegram', '--sleep=1', '--no-interaction') `
        -WorkingDirectory $apiDir -WindowStyle Hidden `
        -RedirectStandardOutput $workerLog -RedirectStandardError "$workerLog.err"
    'Queue worker starting (log: ' + $workerLog + ') ...'
} else { 'Queue worker already running.' }

if (-not (Get-TunnelProcess)) {
    if (-not (Test-Path $tokenFile)) { throw "Tunnel token missing: $tokenFile" }
    $token = (Get-Content $tokenFile -Raw).Trim()
    Start-Process -FilePath $cloudflared -ArgumentList @('tunnel', 'run', '--token', $token) `
        -WindowStyle Hidden -RedirectStandardOutput $tunnelLog -RedirectStandardError "$tunnelLog.err"
    'Tunnel starting (log: ' + $tunnelLog + ') ...'
} else { 'Tunnel already running.' }

for ($i = 1; $i -le 8; $i++) {
    Start-Sleep -Seconds 5
    try {
        $resp = Invoke-WebRequest "$publicUrl/api/health" -UseBasicParsing -TimeoutSec 10
        if ($resp.StatusCode -eq 200) { "Public health OK ($publicUrl/api/health) - webhook base is live."; break }
    } catch {
        if ($i -eq 8) { "Public health not reachable yet: $($_.Exception.Message)" }
    }
}
