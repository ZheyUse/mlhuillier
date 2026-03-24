# Mirror audit script for mlhuillier
# Run from repository root: C:\xampp\htdocs\mlhuillier

$repoPath = (Get-Location).Path
Write-Output "Repo: $repoPath"
$src = Join-Path $repoPath 'test'
$auditRoot = Join-Path $repoPath 'audit'
$scaffoldDir = Join-Path $auditRoot 'scaffold_templates'
$diffsDir = Join-Path $auditRoot 'diffs'
$testFilesPath = Join-Path $auditRoot 'test-files.txt'
$pathReportPath = Join-Path $auditRoot 'audit-path-report.txt'
$diffListPath = Join-Path $auditRoot 'audit-diffs-list.txt'
$summaryPath = Join-Path $auditRoot 'audit-summary.txt'

# Clean old outputs
Remove-Item -Recurse -Force $auditRoot -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $auditRoot | Out-Null

# 1) Export test file list
Write-Output "Exporting test file list..."
Get-ChildItem -Path $src -Recurse -File | ForEach-Object {
  $p = $_.FullName.Substring($src.Length+1).TrimStart('\')
  ($p -replace '\\','/')
} | Sort-Object | Out-File $testFilesPath -Encoding utf8
Write-Output "Wrote $testFilesPath"

# 2) Extract scaffolder templates
Write-Output "Extracting scaffolder templates (via --dump-templates)..."
php generate-file-structure.php --dump-templates audit/scaffold_templates
if ($LASTEXITCODE -ne 0) {
  throw "Template dump failed."
}
Write-Output "$scaffoldDir created (or updated)"

# 3) Path-level comparison
Write-Output "Running path-level comparison..."
$test = Get-Content $testFilesPath
$scaffold = Get-ChildItem -Recurse -File $scaffoldDir | ForEach-Object{
  $_.FullName.Substring($scaffoldDir.Length+1) -replace '\\','/'
} | Sort-Object
$scaffold = @($scaffold)
$test = @($test)
$report = @()
$missing = Compare-Object -ReferenceObject $test -DifferenceObject $scaffold | Where-Object { $_.SideIndicator -eq '<=' }
$extra = Compare-Object -ReferenceObject $test -DifferenceObject $scaffold | Where-Object { $_.SideIndicator -eq '=>' }
foreach($m in $missing){ $report += "MISSING-IN-SCAFFOLD: $($m.InputObject)" }
foreach($e in $extra){ $report += "EXTRA-IN-SCAFFOLD: $($e.InputObject)" }
$report | Out-File $pathReportPath -Encoding utf8
Write-Output "Wrote $pathReportPath"

# 4) Content diffs for matching paths
Write-Output "Checking content diffs for matching paths..."
New-Item -ItemType Directory -Force -Path $diffsDir | Out-Null
$baseTest = $src
$baseScaffold = $scaffoldDir

foreach($rel in (Get-ChildItem $baseTest -Recurse -File | ForEach-Object { $_.FullName.Substring($baseTest.Length+1) -replace '\\','/' })){
  $a = Join-Path $baseTest ($rel -replace '/','\\')
  $b = Join-Path $baseScaffold ($rel -replace '/','\\')
  if(Test-Path $b){
    $aa = Get-Content $a -Raw -ErrorAction SilentlyContinue
    $bb = Get-Content $b -Raw -ErrorAction SilentlyContinue
    if($aa -ne $bb){
      Write-Output "DIFF: $rel"
      $safe = $rel -replace '/','__'
      cmd /c fc "$a" "$b" | Out-File -FilePath (Join-Path $diffsDir ($safe + '.diff')) -Encoding utf8
      Add-Content -Path $diffListPath -Value $rel
    }
  }
}
Write-Output "Diffs written to $diffsDir and $diffListPath (if any)"

# 5) Summary counts
$missingCount = (Get-Content $pathReportPath -ErrorAction SilentlyContinue | Select-String 'MISSING-IN-SCAFFOLD' -SimpleMatch).Count
$extraCount = (Get-Content $pathReportPath -ErrorAction SilentlyContinue | Select-String 'EXTRA-IN-SCAFFOLD' -SimpleMatch).Count
$diffCount = (Get-Content $diffListPath -ErrorAction SilentlyContinue | Measure-Object).Count
"SUMMARY: Missing=$missingCount; Extra=$extraCount; Diffs=$diffCount" | Out-File $summaryPath -Encoding utf8
Write-Output "Wrote $summaryPath"

Write-Output "Mirror audit complete. Files produced under: $auditRoot"
