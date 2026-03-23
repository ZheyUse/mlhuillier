param([Parameter(ValueFromRemainingArguments=$true)] $Args)

$bat = Join-Path $PSScriptRoot 'ml.bat'
$out = & $bat @Args 2>&1

foreach ($line in $out) {
    if ($line -match '^CD_TO:\s*(.+)$') {
        Set-Location $Matches[1]
        break
    }
}

$out | Write-Output
