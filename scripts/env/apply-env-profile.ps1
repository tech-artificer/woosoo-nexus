param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('local', 'docker')]
    [string] $Profile,

    [switch] $DryRun,
    [switch] $AutoRun,
    [switch] $AllowOverride,
    [switch] $ForceReplace,

    [string] $SourcePath,
    [string] $TargetPath
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent (Split-Path -Parent $scriptDir)

function Exit-With {
    param(
        [int] $Code,
        [string] $Message
    )

    if ($Code -eq 0) {
        Write-Host $Message
    } else {
        [Console]::Error.WriteLine($Message)
    }

    exit $Code
}

function Resolve-ProfilePath {
    param([string] $RequestedProfile)

    if ($SourcePath) {
        return (Resolve-Path -LiteralPath $SourcePath -ErrorAction Stop).Path
    }

    $profilePath = Join-Path $repoRoot ".env.$RequestedProfile"
    if (Test-Path -LiteralPath $profilePath) {
        return $profilePath
    }

    $examplePath = Join-Path $repoRoot ".env.$RequestedProfile.example"
    if ($DryRun -and (Test-Path -LiteralPath $examplePath)) {
        Write-Warning "Using .env.$RequestedProfile.example for dry-run only. Create .env.$RequestedProfile before applying."
        return $examplePath
    }

    Exit-With 1 "Missing .env.$RequestedProfile. Copy .env.$RequestedProfile.example to .env.$RequestedProfile and fill local secrets first."
}

function Read-EnvFile {
    param([string] $Path)

    $entries = New-Object System.Collections.Generic.List[object]
    $counts = @{}
    $lineNumber = 0

    foreach ($line in Get-Content -LiteralPath $Path) {
        $lineNumber++
        if ($line -match '^\s*#' -or $line -match '^\s*$') {
            continue
        }

        if ($line -notmatch '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=') {
            continue
        }

        $key = $Matches[1]
        if (-not $counts.ContainsKey($key)) {
            $counts[$key] = 0
        }

        $counts[$key]++
        $entries.Add([pscustomobject]@{
            Key = $key
            Line = $line.TrimEnd()
            LineNumber = $lineNumber
        })
    }

    $duplicates = @($counts.GetEnumerator() | Where-Object { $_.Value -gt 1 } | Sort-Object Name | ForEach-Object { $_.Name })

    return [pscustomobject]@{
        Entries = $entries.ToArray()
        Duplicates = $duplicates
    }
}

function Merge-Entries {
    param(
        [object[]] $BaseEntries,
        [object[]] $ProfileEntries,
        [switch] $PermitOverride
    )

    $profileByKey = @{}
    foreach ($entry in $ProfileEntries) {
        $profileByKey[$entry.Key] = $entry
    }

    $baseKeys = @{}
    foreach ($entry in $BaseEntries) {
        $baseKeys[$entry.Key] = $true
    }

    $overlap = @($profileByKey.Keys | Where-Object { $baseKeys.ContainsKey($_) } | Sort-Object)
    if ($overlap.Count -gt 0 -and -not ($AllowOverride -or $PermitOverride)) {
        Exit-With 3 "Profile would overwrite existing .env keys without -AllowOverride: $($overlap -join ', ')"
    }

    $merged = New-Object System.Collections.Generic.List[string]

    foreach ($entry in $BaseEntries) {
        if ($profileByKey.ContainsKey($entry.Key)) {
            $merged.Add($profileByKey[$entry.Key].Line)
        } else {
            $merged.Add($entry.Line)
        }
    }

    foreach ($entry in $ProfileEntries) {
        if (-not $baseKeys.ContainsKey($entry.Key)) {
            $merged.Add($entry.Line)
        }
    }

    return @($merged)
}

function Write-Utf8NoBom {
    param(
        [string] $Path,
        [string[]] $Lines
    )

    $encoding = New-Object System.Text.UTF8Encoding $false
    $content = ($Lines -join [Environment]::NewLine) + [Environment]::NewLine
    [System.IO.File]::WriteAllText($Path, $content, $encoding)
}

function Invoke-Hygiene {
    Push-Location $repoRoot
    try {
        if ($Profile -eq 'docker') {
            Write-Host 'Running Docker profile hygiene inside app container...'
            & docker compose exec app php artisan optimize:clear
            if ($LASTEXITCODE -ne 0) { Exit-With 4 'docker compose exec app php artisan optimize:clear failed' }
            & docker compose exec app php artisan config:clear
            if ($LASTEXITCODE -ne 0) { Exit-With 4 'docker compose exec app php artisan config:clear failed' }
            return
        }

        Write-Host 'Running local profile hygiene on host PHP...'
        & php artisan optimize:clear
        if ($LASTEXITCODE -ne 0) { Exit-With 4 'php artisan optimize:clear failed' }
        & php artisan config:clear
        if ($LASTEXITCODE -ne 0) { Exit-With 4 'php artisan config:clear failed' }
    } finally {
        Pop-Location
    }
}

$resolvedSource = Resolve-ProfilePath $Profile
$resolvedTarget = if ($TargetPath) { $TargetPath } else { Join-Path $repoRoot '.env' }

$source = Read-EnvFile $resolvedSource
if ($source.Duplicates.Count -gt 0) {
    Exit-With 2 "Duplicate keys in source profile $resolvedSource`: $($source.Duplicates -join ', ')"
}

$targetEntries = @()
if ((Test-Path -LiteralPath $resolvedTarget) -and -not $ForceReplace) {
    $target = Read-EnvFile $resolvedTarget
    if ($target.Duplicates.Count -gt 0) {
        Exit-With 3 "Duplicate keys in target .env $resolvedTarget`: $($target.Duplicates -join ', ')"
    }
    $targetEntries = $target.Entries
}

$baselineEntries = @()
if ($ForceReplace) {
    $baselinePath = Join-Path $repoRoot '.env.example'
    if (-not (Test-Path -LiteralPath $baselinePath)) {
        Exit-With 1 "Cannot use -ForceReplace: missing baseline template $baselinePath"
    }

    $baseline = Read-EnvFile $baselinePath
    if ($baseline.Duplicates.Count -gt 0) {
        Exit-With 3 "Duplicate keys in baseline .env.example $baselinePath`: $($baseline.Duplicates -join ', ')"
    }

    $baselineEntries = $baseline.Entries
}

$bodyLines = if ($ForceReplace) {
    Merge-Entries -BaseEntries $baselineEntries -ProfileEntries $source.Entries -PermitOverride
} else {
    Merge-Entries -BaseEntries $targetEntries -ProfileEntries $source.Entries
}

$header = @(
    "# PROFILE=$Profile",
    "# GENERATED_AT=$((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))",
    '# Generated by scripts/env/apply-env-profile.ps1',
    ''
)

$finalLines = @($header + $bodyLines)

if ($DryRun) {
    Write-Host "Dry run OK. Source: $resolvedSource"
    Write-Host "Target: $resolvedTarget"
    Write-Host "Mode: $(if ($ForceReplace) { 'force replace (baseline + profile overrides)' } else { 'merge' })"
    Write-Host "Keys: $((@($bodyLines | Where-Object { $_ -match '^\s*[A-Za-z_][A-Za-z0-9_]*\s*=' }).Count))"
    exit 0
}

try {
    $targetParent = Split-Path -Parent $resolvedTarget
    if ($targetParent -and -not (Test-Path -LiteralPath $targetParent)) {
        New-Item -ItemType Directory -Path $targetParent | Out-Null
    }
    Write-Utf8NoBom -Path $resolvedTarget -Lines $finalLines
} catch {
    Exit-With 4 "Failed to write $resolvedTarget`: $($_.Exception.Message)"
}

if ($AutoRun) {
    Invoke-Hygiene
} else {
    Write-Host 'Profile applied. Recommended hygiene:'
    if ($Profile -eq 'docker') {
        Write-Host '  docker compose exec app php artisan optimize:clear'
        Write-Host '  docker compose exec app php artisan config:clear'
    } else {
        Write-Host '  php artisan optimize:clear'
        Write-Host '  php artisan config:clear'
    }
}

Exit-With 0 "Applied $Profile profile to $resolvedTarget"
