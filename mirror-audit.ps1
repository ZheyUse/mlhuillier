# Mirror audit script for mlhuillier
# Run from repository root: C:\xampp\htdocs\mlhuillier

$repoPath = (Get-Location).Path
Write-Output "Repo: $repoPath"
$src = Join-Path $repoPath 'test'

# Clean old outputs
Remove-Item -Recurse -Force scaffold_templates -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force diffs -ErrorAction SilentlyContinue
Remove-Item -Force test-files.txt -ErrorAction SilentlyContinue
Remove-Item -Force audit-path-report.txt -ErrorAction SilentlyContinue
Remove-Item -Force audit-diffs-list.txt -ErrorAction SilentlyContinue

# 1) Export test file list
Write-Output "Exporting test file list..."
Get-ChildItem -Path $src -Recurse -File | ForEach-Object {
  $p = $_.FullName.Substring($src.Length+1).TrimStart('\')
  ($p -replace '\\','/')
} | Sort-Object | Out-File test-files.txt -Encoding utf8
Write-Output "Wrote test-files.txt"

# 2) Extract scaffolder templates
Write-Output "Extracting scaffolder templates (via --dump-templates)..."
php generate-file-structure.php --dump-templates scaffold_templates
if ($LASTEXITCODE -ne 0) {
  throw "Template dump failed."
}
Write-Output "scaffold_templates/ created (or updated)"

# 3) Path-level comparison
Write-Output "Running path-level comparison..."
$test = Get-Content test-files.txt
$scaffold = Get-ChildItem -Recurse -File scaffold_templates | ForEach-Object{
  $_.FullName.Substring((Get-Location).Path.Length+1) -replace '\\','/'
} | Sort-Object
$scaffold = @($scaffold)
$test = @($test)
$report = @()
$missing = Compare-Object -ReferenceObject $test -DifferenceObject $scaffold | Where-Object { $_.SideIndicator -eq '<=' }
$extra = Compare-Object -ReferenceObject $test -DifferenceObject $scaffold | Where-Object { $_.SideIndicator -eq '=>' }
foreach($m in $missing){ $report += "MISSING-IN-SCAFFOLD: $($m.InputObject)" }
foreach($e in $extra){ $report += "EXTRA-IN-SCAFFOLD: $($e.InputObject)" }
$report | Out-File audit-path-report.txt -Encoding utf8
Write-Output "Wrote audit-path-report.txt"

# 4) Content diffs for matching paths
Write-Output "Checking content diffs for matching paths..."
New-Item -ItemType Directory -Force -Path diffs | Out-Null
$baseTest = $src
$baseScaffold = Join-Path $repoPath 'scaffold_templates'

foreach($rel in (Get-ChildItem $baseTest -Recurse -File | ForEach-Object { $_.FullName.Substring($baseTest.Length+1) -replace '\\','/' })){
  $a = Join-Path $baseTest ($rel -replace '/','\\')
  $b = Join-Path $baseScaffold ($rel -replace '/','\\')
  if(Test-Path $b){
    $aa = Get-Content $a -Raw -ErrorAction SilentlyContinue
    $bb = Get-Content $b -Raw -ErrorAction SilentlyContinue
    if($aa -ne $bb){
      Write-Output "DIFF: $rel"
      $safe = $rel -replace '/','__'
      cmd /c fc "$a" "$b" | Out-File -FilePath (Join-Path 'diffs' ($safe + '.diff')) -Encoding utf8
      Add-Content -Path audit-diffs-list.txt -Value $rel
    }
  }
}
Write-Output "Diffs written to diffs/ and audit-diffs-list.txt (if any)"

# 5) Summary counts
$missingCount = (Get-Content audit-path-report.txt -ErrorAction SilentlyContinue | Select-String 'MISSING-IN-SCAFFOLD' -SimpleMatch).Count
$extraCount = (Get-Content audit-path-report.txt -ErrorAction SilentlyContinue | Select-String 'EXTRA-IN-SCAFFOLD' -SimpleMatch).Count
$diffCount = (Get-Content audit-diffs-list.txt -ErrorAction SilentlyContinue | Measure-Object).Count
"SUMMARY: Missing=$missingCount; Extra=$extraCount; Diffs=$diffCount" | Out-File audit-summary.txt -Encoding utf8
Write-Output "Wrote audit-summary.txt"

Write-Output "Mirror audit complete. Files produced: test-files.txt, scaffold_templates/, audit-path-report.txt, audit-diffs-list.txt, diffs/, audit-summary.txt"
