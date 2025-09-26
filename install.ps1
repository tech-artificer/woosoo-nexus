# -------------------------------------------
# Woosoo App BackgroundWorker Installer using NSSM
# -------------------------------------------

# --- Auto-elevate to Administrator if needed ---
if (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole] "Administrator"))
{
    Write-Host "Restarting script as Administrator..."
    Start-Process powershell "-ExecutionPolicy Bypass -File `"$PSCommandPath`"" -Verb RunAs
    exit
}

# --- Paths ---
$AppPath      = Split-Path -Parent $MyInvocation.MyCommand.Path
$PhpPath      = Join-Path $AppPath "dependency\php\php.exe"
$Nssm         = Join-Path $AppPath "dependency\nssm\win64\nssm.exe"
$LogPath      = Join-Path $AppPath "logs"

Write-Host "======================================="
Write-Host " Woosoo App Service Installer"
Write-Host " Base Path: $AppPath"
Write-Host "======================================="

# Ensure logs folder exists
if (-Not (Test-Path $LogPath)) {
    New-Item -ItemType Directory -Force -Path $LogPath | Out-Null
    Write-Host "Created log folder at $LogPath"
}

# --- Helper function to (re)install a service ---
function Install-Service {
    param(
        [string]$Name,
        [string]$Command
    )

    Write-Host "Installing service: $Name"

    # Remove service if it already exists
    if (Get-Service -Name $Name -ErrorAction SilentlyContinue) {
        & $Nssm remove $Name confirm
        Start-Sleep -Seconds 1
        Write-Host "Removed existing service $Name"
    }

    # Detect runtime
    if ($Command -like "node*") {
        # Node.js is expected to be in PATH
        $Parts = $Command.Split(" ", 2)
        $Exe   = $Parts[0]        # "node"
        $Args  = $Parts[1]        # "print-service/index.js"
        $Args  = Join-Path $AppPath $Args
        & $Nssm install $Name $Exe $Args
    }
    else {
        # PHP artisan service
        & $Nssm install $Name $PhpPath $Command
    }

    # Common settings
    & $Nssm set $Name AppDirectory $AppPath
    & $Nssm set $Name AppStdout "$LogPath\$Name.log"
    & $Nssm set $Name AppStderr "$LogPath\$Name-error.log"
    & $Nssm set $Name Start SERVICE_AUTO_START
    & $Nssm set $Name AppRestartDelay 2000

    try {
        Start-Service $Name
        Write-Host "✅ Service $Name installed and started"
    }
    catch {
        Write-Warning "Could not start $Name automatically. Try: Start-Service $Name"
    }
}

# --- Install all required services ---
Install-Service -Name "woosoo-scheduler" -Command "artisan schedule:work"
Install-Service -Name "woosoo-reverb"    -Command "artisan reverb:start"
Install-Service -Name "woosoo-queue"     -Command "artisan queue:work"
Install-Service -Name "woosoo-printer"   -Command "node print-service/index.js"

Write-Host "======================================="
Write-Host " ✅ All services installed successfully!"
Write-Host " Logs are available in: $LogPath"
Write-Host "======================================="
