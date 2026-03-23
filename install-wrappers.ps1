# install-wrappers.ps1
# Copies ml wrappers to %USERPROFILE%\bin, adds to user PATH, and dot-sources ml.ps1 into PowerShell profile.

$ScriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
$Dest = Join-Path $env:USERPROFILE 'bin'
if (-not (Test-Path $Dest)) {
    New-Item -ItemType Directory -Path $Dest | Out-Null
    Write-Output "Created folder: $Dest"
} else {
    Write-Output "Target folder exists: $Dest"
}

$Files = @('ml.cmd','ml.ps1')
foreach ($f in $Files) {
    $src = Join-Path $ScriptRoot $f
    if (Test-Path $src) {
        Copy-Item -Force -Path $src -Destination $Dest
        Write-Output "Copied $f to $Dest"
    } else {
        Write-Warning "Source file not found: $src"
    }
}

if (-not (Test-Path $PROFILE)) {
    New-Item -ItemType File -Force -Path $PROFILE | Out-Null
    Write-Output "Created PowerShell profile: $PROFILE"
}

$DotLine = ". '$Dest\ml.ps1'"
$profileContent = ''
try { $profileContent = Get-Content -Path $PROFILE -Raw -ErrorAction Stop } catch {}
if ($profileContent -notmatch [regex]::Escape($DotLine)) {
    Add-Content -Path $PROFILE -Value $DotLine
    Write-Output "Added dot-source to profile: $PROFILE"
} else {
    Write-Output "Profile already sources ml.ps1"
}

# Add destination to user PATH if missing
$oldPath = [Environment]::GetEnvironmentVariable('Path','User')
if ($oldPath -notlike "*$Dest*") {
    if ([string]::IsNullOrWhiteSpace($oldPath)) { $newPath = $Dest } else { $newPath = $oldPath + ';' + $Dest }
    [Environment]::SetEnvironmentVariable('Path',$newPath,'User')
    Write-Output "Added $Dest to user PATH. You may need to restart your shell to use it."
} else {
    Write-Output "User PATH already contains $Dest"
}

Write-Output "Installation complete. Run 'ml nav --new' in a new shell (or dot-source your profile) to test."
