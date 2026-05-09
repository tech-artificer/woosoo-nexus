param(
    [string] $ManifestPath,
    [switch] $Apply
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent (Split-Path -Parent $scriptDir)
$manifestFile = if ($ManifestPath) { $ManifestPath } else { Join-Path $scriptDir 'generated-artifacts.cleanup.json' }

if (-not (Test-Path -LiteralPath $manifestFile)) {
    Write-Error "Missing cleanup manifest: $manifestFile"
    exit 1
}

$manifest = Get-Content -LiteralPath $manifestFile -Raw | ConvertFrom-Json
$candidates = New-Object System.Collections.Generic.List[System.IO.DirectoryInfo]

foreach ($glob in $manifest.allowedRootGlobs) {
    $fullGlob = Join-Path $repoRoot $glob
    foreach ($match in Get-ChildItem -Path $fullGlob -Directory -Force -ErrorAction SilentlyContinue) {
        $resolved = $match.FullName
        if (-not $resolved.StartsWith($repoRoot, [StringComparison]::OrdinalIgnoreCase)) {
            Write-Error "Refusing path outside repo root: $resolved"
            exit 2
        }

        $relative = $resolved.Substring($repoRoot.Length).TrimStart('\', '/')
        $excluded = $false
        foreach ($exclude in $manifest.excludeGlobs) {
            if ($relative -like $exclude) {
                $excluded = $true
                break
            }
        }

        if (-not $excluded) {
            $candidates.Add($match)
        }
    }
}

$unique = @($candidates | Sort-Object FullName -Unique)

if ($unique.Count -eq 0) {
    Write-Host 'No generated artifact directories matched the manifest.'
    exit 0
}

Write-Host 'Generated artifact cleanup candidates:'
foreach ($candidate in $unique) {
    Write-Host "  $($candidate.FullName)"
}

if (-not $Apply) {
    Write-Host 'Dry run only. Re-run with -Apply to delete these directories.'
    exit 0
}

foreach ($candidate in $unique) {
    Remove-Item -LiteralPath $candidate.FullName -Recurse -Force
}

Write-Host "Deleted $($unique.Count) generated artifact director$(if ($unique.Count -eq 1) { 'y' } else { 'ies' })."
