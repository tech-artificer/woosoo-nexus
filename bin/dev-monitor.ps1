Param()

$ErrorActionPreference = 'Stop'

# Config
$VITE_PORT = if ($env:VITE_DEV_PORT) { [int]$env:VITE_DEV_PORT } else { 3000 }
$VITE_URL = if ($env:VITE_DEV_SERVER_URL) { $env:VITE_DEV_SERVER_URL } else { "http://localhost:$VITE_PORT" }
$LOG_FILE = "vite.log"
$MAX_RESTARTS = 3
$global:RESTART_COUNT = 0

function Start-Server {
    if (Test-Path $LOG_FILE) { Remove-Item $LOG_FILE -ErrorAction SilentlyContinue }
    Write-Host "Starting dev server (npm run dev) at $VITE_URL..."
    $cmd = "npm run dev > \"$($PWD.Path)\$LOG_FILE\" 2>&1"
    $proc = Start-Process -FilePath "cmd.exe" -ArgumentList "/C", $cmd -PassThru
    return $proc
}

function Wait-ForServer([int]$timeoutSeconds = 120) {
    Write-Host "Waiting for $VITE_URL to respond (timeout ${timeoutSeconds}s)..."
    $elapsed = 0
    while ($elapsed -lt $timeoutSeconds) {
        try {
            Invoke-WebRequest -Uri $VITE_URL -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop | Out-Null
            return $true
        } catch {
            Start-Sleep -Seconds 2
            $elapsed += 2
        }
    }
    return $false
}

function Restart-WithBackoff([ref]$procRef) {
    $global:RESTART_COUNT = $global:RESTART_COUNT + 1
    if ($global:RESTART_COUNT -gt $MAX_RESTARTS) {
        Write-Host "Exceeded max restarts ($MAX_RESTARTS). Exiting."
        return $false
    }
    $backoff = $global:RESTART_COUNT * 3
    Write-Host "Restart attempt $global:RESTART_COUNT in $backoff seconds..."
    Start-Sleep -Seconds $backoff
    try { if ($procRef.Value -and -not $procRef.Value.HasExited) { $procRef.Value.Kill() } } catch {}
    $procRef.Value = Start-Server
    if (Wait-ForServer) { return $true } else { return $false }
}

function Tail-Log-Job($logFile) {
    return Start-Job -ScriptBlock { param($lf) Get-Content -Path $lf -Tail 0 -Wait } -ArgumentList $logFile
}

function Main {
    $proc = Start-Server
    if (Wait-ForServer) {
        Write-Host "Dev server is up. Tailing $LOG_FILE"
        $tailJob = Tail-Log-Job $LOG_FILE
        try {
            Wait-Process -Id $proc.Id
        } catch {}
        Write-Host "Dev server process exited."
        try { Stop-Job $tailJob -Force -ErrorAction SilentlyContinue; Remove-Job $tailJob -Force -ErrorAction SilentlyContinue } catch {}

        while ($global:RESTART_COUNT -lt $MAX_RESTARTS) {
            if (Restart-WithBackoff ([ref]$proc)) {
                Write-Host "Restart success; resuming log tail."
                $tailJob = Tail-Log-Job $LOG_FILE
                try { Wait-Process -Id $proc.Id } catch {}
                try { Stop-Job $tailJob -Force -ErrorAction SilentlyContinue; Remove-Job $tailJob -Force -ErrorAction SilentlyContinue } catch {}
            } else {
                break
            }
        }

        Write-Host "All restart attempts exhausted. Showing last 200 lines of $LOG_FILE:"
        if (Test-Path $LOG_FILE) { Get-Content -Path $LOG_FILE -Tail 200 | ForEach-Object { Write-Output $_ } } else { Write-Host "$LOG_FILE not found" }
        exit 1
    } else {
        Write-Host "Dev server did not respond within timeout. Showing $LOG_FILE:"
        if (Test-Path $LOG_FILE) { Get-Content -Path $LOG_FILE -Tail 200 | ForEach-Object { Write-Output $_ } } else { Write-Host "$LOG_FILE not found" }
        exit 1
    }
}

Main
