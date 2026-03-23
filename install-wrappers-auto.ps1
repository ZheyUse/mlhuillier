try {
    $ErrorActionPreference = 'Stop'
    $repo = 'C:\xampp\htdocs\mlhuillier'
    $bin = Join-Path $env:USERPROFILE 'bin'
    $tools = 'C:\ML CLI\Tools'
    New-Item -ItemType Directory -Path $bin -Force | Out-Null
    New-Item -ItemType Directory -Path $tools -Force | Out-Null
    $files = @('ml.cmd')
    $copied = 0; $skipped = 0; $copiedTools = 0; $skippedTools = 0
    foreach ($f in $files) {
        $src = Join-Path $repo $f
        if (-not (Test-Path $src)) { Write-Output "MISSING_SRC: $src"; continue }
        $dst = Join-Path $bin $f
        Copy-Item $src $dst -Force; $copied++; Write-Output "COPIED_BIN: $dst"
        $dst2 = Join-Path $tools $f
        Copy-Item $src $dst2 -Force; $copiedTools++; Write-Output "COPIED_TOOLS: $dst2"
    }

    # Update User PATH
    $userPath = [Environment]::GetEnvironmentVariable('Path','User')
    if ([string]::IsNullOrWhiteSpace($userPath)) { $userPath = '' }
    if ($userPath -notlike "*$bin*") {
        if ($userPath.Trim().Length -eq 0) { $newPath = $bin } else { $newPath = $userPath.TrimEnd(';') + ';' + $bin }
        [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        Write-Output "PATH_UPDATED: added $bin to User PATH"
        $pathChanged = $true
    } else { Write-Output "PATH_OK: $bin already in User PATH"; $pathChanged = $false }

    # Update PowerShell profiles (function shim only; do not dot-source ml.ps1)
    $profileTargets = @(
        $PROFILE,
        (Join-Path $HOME 'Documents\WindowsPowerShell\Microsoft.PowerShell_profile.ps1'),
        (Join-Path $HOME 'Documents\WindowsPowerShell\profile.ps1')
    ) | Where-Object { $_ } | Select-Object -Unique

    # Add a lightweight PowerShell shim function `ml` that forwards to ml.cmd when present.
    $shimTemplate = @'
function ml {
    param([Parameter(ValueFromRemainingArguments=$true)][object[]]$Args)
    if ($Args.Count -gt 0 -and [string]$Args[0] -eq 'nav') {
        Write-Output ''
        Write-Output '=============================='
        Write-Output 'ML CLI - M LHUILLIER FILE GENERATOR'
        Write-Output 'https://github.com/ZheyUse'
        Write-Output '=============================='
        Write-Output ''
        Write-Output 'Executing navigation helper...'
        Write-Output ''
        $htdocsPath = 'C:\xampp\htdocs'
        $navArg = if ($Args.Count -gt 1) { [string]$Args[1] } else { '' }
        if ([string]::IsNullOrWhiteSpace($navArg)) {
            $navArg = Read-Host 'Where do you want to go?'
        }
        if ([string]::IsNullOrWhiteSpace($navArg)) {
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
        if ($profileContent -notmatch 'function\s+ml') {
            Add-Content -Path $profilePath -Value $shim
            Write-Output "PROFILE_SHIM_ADDED: ml function in $profilePath"
            $profileChanged = $true
        } else {
            Write-Output "PROFILE_SHIM_OK: function ml exists in $profilePath"
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