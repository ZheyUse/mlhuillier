param(
    [string]$TargetVersion,
    [string]$LocalJsonPath,
    [string]$PrimaryUrl,
    [string]$FallbackUrl,
    [int]$MaxHighlights = 3
)

$ErrorActionPreference = 'Stop'

function Get-VersionHistory {
    param(
        [string]$LocalPath,
        [string]$Primary,
        [string]$Fallback
    )

    if ($LocalPath -and (Test-Path -LiteralPath $LocalPath)) {
        $raw = Get-Content -Raw -LiteralPath $LocalPath
        if ($raw) {
            return ($raw | ConvertFrom-Json)
        }
    }

    if ($Primary) {
        try {
            return (Invoke-RestMethod -Uri $Primary)
        } catch {
        }
    }

    if ($Fallback) {
        return (Invoke-RestMethod -Uri $Fallback)
    }

    throw 'No changelog source available.'
}

function Get-Release {
    param(
        $History,
        [string]$Version
    )

    if (-not $History -or -not $History.releases) {
        return $null
    }

    $release = $null

    if ($Version) {
        $release = $History.releases | Where-Object { $_.version -eq $Version } | Select-Object -First 1
    }

    if (-not $release) {
        $release = $History.releases | Where-Object { $_.isLatest -eq $true } | Select-Object -First 1
    }

    if (-not $release) {
        $release = $History.releases | Select-Object -First 1
    }

    return $release
}

try {
    $history = Get-VersionHistory -LocalPath $LocalJsonPath -Primary $PrimaryUrl -Fallback $FallbackUrl
    $release = Get-Release -History $history -Version $TargetVersion

    if (-not $release) {
        Write-Output 'Unable to load changelog summary right now.'
        exit 2
    }

    $versionText = [string]$release.version
    if ([string]::IsNullOrWhiteSpace($versionText)) {
        $versionText = 'latest'
    }

    Write-Output ("What's New in v$versionText")

    if ($release.dateRange -and $release.dateRange.from -and $release.dateRange.to) {
        Write-Output ("Period: $($release.dateRange.from) to $($release.dateRange.to)")
    }

    Write-Output ''
    Write-Output 'Top highlights:'

    $highlights = @($release.highlights)
    if ($highlights.Count -gt 0) {
        $highlights | Select-Object -First $MaxHighlights | ForEach-Object {
            Write-Output ("  - $_")
        }
    } else {
        Write-Output '  - No highlight summary available yet.'
    }

    Write-Output ''
    Write-Output 'Full changelog: https://zheyuse.github.io/mlhuillier/documentation/'
    exit 0
} catch {
    Write-Output 'Unable to load changelog summary right now.'
    Write-Output 'Full changelog: https://zheyuse.github.io/mlhuillier/documentation/'
    exit 2
}
