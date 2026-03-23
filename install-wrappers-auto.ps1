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

    # Update PowerShell profile (function shim only; do not dot-source ml.ps1)
    $profileDir = Split-Path $PROFILE -Parent
    if (-not (Test-Path $profileDir)) { New-Item -ItemType Directory -Path $profileDir -Force | Out-Null }
    if (-not (Test-Path $PROFILE)) {
        New-Item -ItemType File -Path $PROFILE -Force | Out-Null
        Write-Output "PROFILE_CREATED: $PROFILE"
        $profileChanged = $true
    } else {
        Write-Output "PROFILE_OK: $PROFILE exists"
        $profileChanged = $false
    }

    # Add a lightweight PowerShell shim function `ml` that forwards to ml.cmd when present.
    # This ensures `ml nav` works even when script execution is restricted.
    try { $profileContent = Get-Content -Path $PROFILE -Raw -ErrorAction SilentlyContinue } catch { $profileContent = '' }
    if ($profileContent -notmatch 'function\s+ml') {
        $shim = @'
function ml {
    param([Parameter(ValueFromRemainingArguments=$true)][object[]]$Args)
    if ($Args.Count -gt 0 -and [string]$Args[0] -eq 'nav') {
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
                if ($null -ne $codeCmd) {
                    & $codeCmd.Source $projectPath | Out-Null
                }
            }
        } else {
            Write-Output 'Project not found.'
        }
        return
    }
    $cmd = Join-Path "{0}" 'ml.cmd'
    if (Test-Path $cmd) {
        & $cmd @Args
    } else {
        $ps = Join-Path "{0}" 'ml.ps1'
        if (Test-Path $ps) {
            & $ps @Args
        } else {
            Write-Output 'ml wrapper not found'
        }
    }
}
'@ -f $bin
        Add-Content -Path $PROFILE -Value $shim
        Write-Output "PROFILE_SHIM_ADDED: ml function"
    } else {
        Write-Output "PROFILE_SHIM_OK: function ml exists"
    }

    Write-Output "RESULT: copied:$copied skipped:$skipped copiedTools:$copiedTools skippedTools:$skippedTools PATH_CHANGED:$pathChanged PROFILE_CHANGED:$profileChanged"
    exit 0
} catch {
    Write-Error "INSTALLER_ERROR: $($_.Exception.Message)"
    exit 2
}