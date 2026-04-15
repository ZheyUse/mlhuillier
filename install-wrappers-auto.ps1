try {
    $ErrorActionPreference = 'Stop'
    # Use the script's folder as the source repository location so the helper
    # works when downloaded and executed remotely (not just on the dev box).
    $ScriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
    if (-not $ScriptRoot) { $ScriptRoot = $PSScriptRoot }
    $repo = $ScriptRoot
    $bin = Join-Path $env:USERPROFILE 'bin'
    $tools = 'C:\ML CLI\Tools'
    New-Item -ItemType Directory -Path $bin -Force | Out-Null
    New-Item -ItemType Directory -Path $tools -Force | Out-Null
    # Ensure both the command shim and the PowerShell wrapper are copied.
    $files = @('ml.cmd','ml.ps1')
    $copied = 0; $skipped = 0; $copiedTools = 0; $skippedTools = 0
    foreach ($f in $files) {
        $src = Join-Path $repo $f
        if (-not (Test-Path $src)) { Write-Output "MISSING_SRC: $src"; continue }
        $dst = Join-Path $bin $f
        if ($src -ieq $dst) {
            Write-Output "SKIP_COPY_BIN_SELF: $src"
        } else {
            Copy-Item $src $dst -Force; $copied++; Write-Output "COPIED_BIN: $dst"
        }
        $dst2 = Join-Path $tools $f
        if ($src -ieq $dst2) {
            Write-Output "SKIP_COPY_TOOLS_SELF: $src"
        } else {
            Copy-Item $src $dst2 -Force; $copiedTools++; Write-Output "COPIED_TOOLS: $dst2"
        }
    }

    # Update User PATH: ensure %USERPROFILE%\bin is present and prioritized so CMD
    # resolves our ml.cmd wrapper before npm shims.
    $userPath = [Environment]::GetEnvironmentVariable('Path','User')
    if ([string]::IsNullOrWhiteSpace($userPath)) { $userPath = '' }
    $parts = @()
    if ($userPath.Trim().Length -gt 0) {
        $parts = $userPath -split ';' | Where-Object { $_ -and $_.Trim() -ne '' }
    }

    $normalizedBin = $bin.TrimEnd('\\')
    $withoutBin = @($parts | Where-Object { $_.TrimEnd('\\') -ine $normalizedBin })

    # Keep original order for other entries and de-duplicate while preserving first occurrence.
    $seen = @{}
    $ordered = @()
    foreach ($p in @($bin) + $withoutBin) {
        $k = $p.TrimEnd('\\').ToLowerInvariant()
        if (-not $seen.ContainsKey($k)) {
            $seen[$k] = $true
            $ordered += $p
        }
    }

    $newPath = ($ordered -join ';')
    if ($newPath -ne $userPath) {
        [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        Write-Output "PATH_UPDATED: prioritized $bin in User PATH"
        $pathChanged = $true
    } else {
        Write-Output "PATH_OK: $bin already prioritized in User PATH"
        $pathChanged = $false
    }

    # Update PowerShell profiles (function shim only; do not dot-source ml.ps1)
    $profileTargets = @(
        $PROFILE,
        $PROFILE.CurrentUserCurrentHost,
        $PROFILE.CurrentUserAllHosts,
        (Join-Path $HOME 'Documents\WindowsPowerShell\Microsoft.PowerShell_profile.ps1'),
        (Join-Path $HOME 'Documents\WindowsPowerShell\profile.ps1'),
        (Join-Path $HOME 'Documents\WindowsPowerShell\Microsoft.VSCode_profile.ps1'),
        (Join-Path $HOME 'Documents\PowerShell\Microsoft.PowerShell_profile.ps1'),
        (Join-Path $HOME 'Documents\PowerShell\profile.ps1'),
        (Join-Path $HOME 'Documents\PowerShell\Microsoft.VSCode_profile.ps1')
    ) | Where-Object { $_ -and -not [string]::IsNullOrWhiteSpace([string]$_) } | Select-Object -Unique

    # Add a lightweight PowerShell shim function `ml` that forwards to ml.cmd when present.
    $shimTemplate = @'
function ml {
    param([Parameter(ValueFromRemainingArguments=$true)][object[]]$Args)
    if ($Args.Count -gt 0 -and [string]$Args[0] -eq 'nav') {
        $htdocsPath = 'C:\xampp\htdocs'
        $navArg = if ($Args.Count -gt 1) { [string]$Args[1] } else { '' }
        if ([string]::IsNullOrWhiteSpace($navArg)) {
            Set-Location $htdocsPath
            Write-Output "Now in $htdocsPath"
            return
        }
        if ($navArg -eq '--new') {
            Set-Location $htdocsPath
            Write-Output "Now in $htdocsPath"
            return
        }
        $projectName = if ($navArg.StartsWith('--')) { $navArg.Substring(2) } else { $navArg }
        if ([string]::IsNullOrWhiteSpace($projectName)) {
            Write-Output 'Project not found.'
            return
        }
        $projectPath = Join-Path $htdocsPath $projectName
        if (Test-Path $projectPath -PathType Container) {
            Set-Location $projectPath
            Write-Output "Now in $projectPath"
            $openVsCode = Read-Host "Open $projectName in VSCode? (Y/N)"
            if ($openVsCode -match '^(?i)y(es)?$') {
                $codeCmd = Get-Command code -ErrorAction SilentlyContinue
                if ($null -eq $codeCmd) {
                    Write-Output 'VSCode CLI (code) not found in PATH.'
                } else {
                    $isCodeRunning = @(Get-Process -Name Code -ErrorAction SilentlyContinue).Count -gt 0
                    if ($isCodeRunning) {
                        & $codeCmd.Source '--new-window' $projectPath | Out-Null
                    } else {
                        & $codeCmd.Source $projectPath | Out-Null
                    }
                }
            }
        } else {
            Write-Output 'Project not found.'
        }
        return
    }
    $cmd = Join-Path "__BIN__" 'ml.cmd'
    if (Test-Path $cmd) {
        & $cmd @Args
    } else {
        $ps = Join-Path "__BIN__" 'ml.ps1'
        if (Test-Path $ps) {
            & $ps @Args
        } else {
            Write-Output 'ml wrapper not found'
        }
    }
}
'@
    $shim = $shimTemplate.Replace('__BIN__', $bin)
    $markerStart = '# >>> ML CLI shim >>>'
    $markerEnd = '# <<< ML CLI shim <<<'
    $shimBlock = "$markerStart`r`n$shim`r`n$markerEnd"

    $profileChanged = $false
    foreach ($profilePath in $profileTargets) {
        $profileDir = Split-Path $profilePath -Parent
        if (-not (Test-Path $profileDir)) { New-Item -ItemType Directory -Path $profileDir -Force | Out-Null }
        if (-not (Test-Path $profilePath)) {
            New-Item -ItemType File -Path $profilePath -Force | Out-Null
            Write-Output "PROFILE_CREATED: $profilePath"
        } else {
            Write-Output "PROFILE_OK: $profilePath exists"
        }

        try { $profileContent = Get-Content -Path $profilePath -Raw -ErrorAction SilentlyContinue } catch { $profileContent = '' }

        if ($profileContent -match [regex]::Escape($markerStart) -and $profileContent -match [regex]::Escape($markerEnd)) {
            $startEsc = [regex]::Escape($markerStart)
            $endEsc = [regex]::Escape($markerEnd)
            $updated = [regex]::Replace($profileContent, "(?s)$startEsc.*?$endEsc", [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $shimBlock })
            if ($updated -ne $profileContent) {
                Set-Content -Path $profilePath -Value $updated
                Write-Output "PROFILE_SHIM_UPDATED: ml function in $profilePath"
                $profileChanged = $true
            } else {
                Write-Output "PROFILE_SHIM_OK: marker block already current in $profilePath"
            }
        } else {
            Add-Content -Path $profilePath -Value $shimBlock
            Write-Output "PROFILE_SHIM_ADDED: ml function in $profilePath"
            $profileChanged = $true
        }
    }

    # Ensure profile scripts can load for CurrentUser so `ml` function is available in new PS sessions.
    try {
        $currentPolicy = Get-ExecutionPolicy -Scope CurrentUser
        if ($currentPolicy -in @('Undefined','Restricted')) {
            Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned -Force
            Write-Output "EXEC_POLICY_UPDATED: CurrentUser=RemoteSigned"
        } else {
            Write-Output "EXEC_POLICY_OK: CurrentUser=$currentPolicy"
        }
    } catch {
        Write-Output "EXEC_POLICY_WARN: $($_.Exception.Message)"
    }

    Write-Output "RESULT: copied:$copied skipped:$skipped copiedTools:$copiedTools skippedTools:$skippedTools PATH_CHANGED:$pathChanged PROFILE_CHANGED:$profileChanged"
    exit 0
} catch {
    Write-Error "INSTALLER_ERROR: $($_.Exception.Message)"
    exit 2
}