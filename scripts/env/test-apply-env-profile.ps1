param()

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$scriptPath = Join-Path $scriptDir 'apply-env-profile.ps1'
$tmpRoot = Join-Path $env:TEMP ('woosoo-env-profile-test-' + [guid]::NewGuid().ToString('N'))

function Assert-True {
    param(
        [bool] $Condition,
        [string] $Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

function Invoke-ProfileScript {
    param(
        [string[]] $Arguments,
        [int[]] $ExpectedExitCodes = @(0)
    )

    $previousErrorAction = $ErrorActionPreference
    $hadNativePref = Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue
    $previousNativePref = $null

    try {
        $ErrorActionPreference = 'Continue'
        if ($hadNativePref) {
            $previousNativePref = $PSNativeCommandUseErrorActionPreference
            $PSNativeCommandUseErrorActionPreference = $false
        }

        $output = & powershell -NoProfile -ExecutionPolicy Bypass -File $scriptPath @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
        if ($hadNativePref) {
            $PSNativeCommandUseErrorActionPreference = $previousNativePref
        }
    }

    if ($ExpectedExitCodes -notcontains $exitCode) {
        $output | Out-Host
    }

    return $exitCode
}

try {
    New-Item -ItemType Directory -Path $tmpRoot | Out-Null

    Assert-True (Test-Path $scriptPath) 'apply-env-profile.ps1 must exist'

    $source = Join-Path $tmpRoot '.env.local'
    $target = Join-Path $tmpRoot '.env'

    @'
APP_ENV=local
APP_DEBUG=true
DB_HOST=127.0.0.1
CACHE_STORE=file
QUEUE_CONNECTION=sync
'@ | Set-Content -Path $source -Encoding utf8

    $exitCode = Invoke-ProfileScript @(
        '-Profile', 'local',
        '-SourcePath', $source,
        '-TargetPath', $target,
        '-DryRun'
    ) -ExpectedExitCodes @(0)
    Assert-True ($exitCode -eq 0) 'dry run with a valid profile should exit 0'
    Assert-True (-not (Test-Path $target)) 'dry run must not write target .env'

    $exitCode = Invoke-ProfileScript @(
        '-Profile', 'local',
        '-SourcePath', $source,
        '-TargetPath', $target,
        '-ForceReplace'
    ) -ExpectedExitCodes @(0)
    Assert-True ($exitCode -eq 0) 'force replace with a valid profile should exit 0'

    $written = Get-Content -Path $target -Raw
    Assert-True ($written.Contains('# PROFILE=local')) 'target .env should include profile header'
    Assert-True ($written.Contains('DB_HOST=127.0.0.1')) 'target .env should include profile values'

    @'
APP_ENV=local
APP_ENV=production
'@ | Set-Content -Path $source -Encoding utf8

    $exitCode = Invoke-ProfileScript @(
        '-Profile', 'local',
        '-SourcePath', $source,
        '-TargetPath', $target,
        '-DryRun'
    ) -ExpectedExitCodes @(2)
    Assert-True ($exitCode -eq 2) 'source duplicate keys should exit 2'

    @'
APP_ENV=local
DB_HOST=127.0.0.1
'@ | Set-Content -Path $source -Encoding utf8

    @'
QUEUE_CONNECTION=sync
QUEUE_CONNECTION=redis
'@ | Set-Content -Path $target -Encoding utf8

    $exitCode = Invoke-ProfileScript @(
        '-Profile', 'local',
        '-SourcePath', $source,
        '-TargetPath', $target,
        '-DryRun'
    ) -ExpectedExitCodes @(3)
    Assert-True ($exitCode -eq 3) 'target duplicate keys should exit 3'

    Write-Host 'apply-env-profile smoke tests passed'
} finally {
    if (Test-Path $tmpRoot) {
        Remove-Item -LiteralPath $tmpRoot -Recurse -Force
    }
}
