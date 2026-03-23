param([Parameter(ValueFromRemainingArguments=$true)] $Args)

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

$bat = Join-Path $PSScriptRoot 'ml.bat'
$out = & $bat @Args 2>&1

foreach ($line in $out) {
    if ($line -match '^CD_TO:\s*(.+)$') {
        Set-Location $Matches[1]
        break
    }
}

$out | Write-Output
