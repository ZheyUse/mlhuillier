# audit-sync.ps1 — Smart audit + sync script for mlhuillier
# Purpose: Run mirror-audit.ps1, then auto-update generate-file-structure.php
#          with new/changed templates from the authoritative test/ folder.
#
# Usage:
#   .\audit-sync.ps1              # Default: dry-run, shows preview then prompts
#   .\audit-sync.ps1 -DryRun      # Preview only, no changes
#   .\audit-sync.ps1 -Force       # Auto-apply all changes (CI/automation)
#
# What happens:
#   1. Runs mirror-audit.ps1 to get diff list
#   2. Reads diff files from test/  (raw = authoritative source)
#   3. Builds PHP heredoc entries
#   4. Backs up generate-file-structure.php
#   5. Patches embedded template array in generate-file-structure.php
#   6. Validates: dumps templates + re-audits
#   7. Reports results
#
# Binary files (png/jpg/etc.) are skipped with a warning because heredoc
# cannot safely encode binary data.

param(
  [switch]$DryRun,
  [switch]$Force
)

$repoRoot = $PSScriptRoot
if (-not $repoRoot) { $repoRoot = (Get-Location).Path }
$projectRoot = Split-Path $repoRoot -Parent  # parent of mlhuillier = the IDE root (Zhey)

# If we were invoked from the repo root directly (double-click / no $PSScriptRoot)
if (-not $repoRoot -or $repoRoot -eq '') {
  $repoRoot = (Get-Location).Path
}

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ─────────────────────────────────────────────────────────────────────────────
# Helper: heredoc label from file extension
# ─────────────────────────────────────────────────────────────────────────────
function Get-HeredocLabel {
  param([string]$Path)
  $ext = [IO.Path]::GetExtension($Path).ToLowerInvariant()
  switch ($ext) {
    '.php'      { 'PHP' }
    '.css'      { 'CSS' }
    '.js'       { 'JS' }
    '.html'     { 'HTML' }
    '.htm'      { 'HTML' }
    '.json'     { 'JSON' }
    '.env'      { 'ENV' }
    '.txt'      { 'TXT' }
    '.sql'      { 'SQL' }
    '.md'       { 'MD' }
    '.xml'      { 'XML' }
    '.yaml'     { 'YAML' }
    '.yml'      { 'YAML' }
    '.htaccess' { 'HTACCESS' }
    '.gitignore'{ 'TEXT' }
    default     { (($ext -replace '^\.','').ToUpperInvariant() + 'FILE') -replace '[^A-Z0-9]', '_' -replace '_+', '_' }
  }
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper: is file binary?
# ─────────────────────────────────────────────────────────────────────────────
function Test-FileIsBinary {
  param([string]$Path)
  $ext = [IO.Path]::GetExtension($Path).ToLowerInvariant()
  $binary = @('.png','.jpg','.jpeg','.gif','.ico','.bmp','.webp','.svg','.ttf','.woff','.woff2','.eot','.otf','.zip','.tar','.gz','.rar')
  if ($binary -contains $ext) { return $true }

  # Heuristic: read first 8 KB, count non-printable bytes
  if (Test-Path $Path) {
    $bytes = [IO.File]::ReadAllBytes($Path)
    $sample = $bytes[0..[Math]::Min(8191, $bytes.Length-1)]
    $nonPrintable = ($sample | Where-Object { $_ -lt 32 -and $_ -notIn @(9,10,13) }).Count
    if ($nonPrintable / $sample.Length -gt 0.15) { return $true }
  }
  return $false
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper: safe heredoc content — escape label if it appears in content
# We write to a temp file and read it back to avoid quoting issues when
# file content contains $, `, or " characters.
# ─────────────────────────────────────────────────────────────────────────────
function New-HeredocEntry {
  param(
    [string]$Path,
    [string]$Content
  )
  $label = Get-HeredocLabel $Path

  # Escape label if it appears as bare word in content (would break the heredoc)
  # Look for "label," or "label\n" — a line that is just the label word + punctuation
  if ($Content -match "(?ms)^(\s*)$([regex]::Escape($label))[,\n]") {
    $label = $label + '_ESC'
  }

  # Write content to a temp file to recover cleanly (avoids PS string parsing issues
  # from content that contains $, `, or other special chars). Delete on exit.
  $tmp = [IO.Path]::GetTempFileName()
  try {
    [IO.File]::WriteAllText($tmp, $Content, [Text.Encoding]::UTF8)
    # Build heredoc entry using -join on an array to avoid quoting issues entirely.
    # Each line of the heredoc is a separate array element; join with CRLF.
    # This keeps the raw $Content completely separate from PowerShell string syntax.
    $header = "'$Path' => <<<'$label'"
    $footer = $label + ","
    $lines  = @($header)
    # Read the content back from the temp file to ensure exact bytes
    $fileLines = @(Get-Content $tmp -NoNewline)
    foreach ($l in $fileLines) { $lines += $l }
    $lines += $footer
    return ($lines -join "`r`n")
  }
  finally {
    Remove-Item $tmp -Force -ErrorAction SilentlyContinue
  }
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper: build label line (uppercase label + comma) for pattern matching
# ─────────────────────────────────────────────────────────────────────────────
function Get-LabelClosingPattern {
  param([string]$Path)
  $label = Get-HeredocLabel $Path
  return "`n$label,"
}

# ─────────────────────────────────────────────────────────────────────────────
# Helper: locate an entry in the PHP source string
# Returns @($startPos, $endPos) or @(-1,-1) if not found
# ─────────────────────────────────────────────────────────────────────────────
function Find-PhpHeredocEntry {
  param(
    [string]$Source,
    [string]$Path,
    [int]$SearchFrom,
    [int]$SearchTo
  )
  # Opening pattern: "'path' => <<<'LABEL'\n"
  $openRel = "'$Path' => <<.'"
  $openPos = $Source.IndexOf($openRel, $SearchFrom)
  if ($openPos -lt 0 -or $openPos -gt $SearchTo) { return @(-1,-1) }

  # Find the end of the opening line (newline after the opening)
  $afterOpen = $openPos + $openRel.Length
  $eolPos = $Source.IndexOf("`n", $afterOpen)
  if ($eolPos -gt $SearchTo) { return @(-1,-1) }

  # Extract LABEL from opening
  $beforeEOL = $Source.Substring($openPos, $eolPos - $openPos)
  if ($beforeEOL -notmatch "<<<'(.+)'") { return @(-1,-1) }
  $label = $matches[1]

  # Locate the label closing: "\nLABEL,\n" or "\nLABEL," at end
  $firstContentChar = $eolPos + 1  # first char after opening newline. May be space or indent.
  if ($firstContentChar -ge $SearchTo) { return @(-1,-1) }

  # Walk forward to find the closing label on its own line
  # Pattern: newline, then label, then comma (possibly with trailing spaces)
  $searchLen = $SearchTo - $firstContentChar
  $window = $Source.Substring($firstContentChar, $searchLen)

  $labelEscaped = [regex]::Escape($label)
  $closeIndex = -1
  # Match: newline followed by label followed by optional spaces and comma
  $match = [regex]::Match($window, "(?s)`n\s*$labelEscaped\s*,")
  if ($match.Success) {
    $rawPos = $firstContentChar + $match.Index + $match.Length  # last char (comma)
    # Trim trailing newlines/spaces after comma
    $i = $rawPos
    while ($i -lt $Source.Length -and $Source[$i] -in @(' ', "`t")) { $i++ }
    if ($i -lt $Source.Length -and $Source[$i] -eq "`n") {
      $closePos = $i  # end at newline
    } elseif ($i -ge $Source.Length) {
      $closePos = $Source.Length
    } else {
      $closePos = $rawPos + 1
    }
    return @($openPos, $closePos)
  }

  # Fallback: scan character-by-character to find newline+LABEL+comma
  $pos = $firstContentChar
  $labelLen = $label.Length
  while ($pos -lt $SearchTo) {
    if ($Source[$pos] -eq "`n") {
      $end = $pos + 1 + $labelLen
      if ($end -lt $Source.Length -and
          $Source.Substring($pos+1, $labelLen) -eq $label -and
          $end -lt $Source.Length -and
          $Source[$end] -eq ',') {
        $closePos = $end + 1
        # trim trailing whitespace before newline
        $i = $closePos
        while ($i -lt $Source.Length -and $Source[$i] -in @(' ', "`t")) { $i++ }
        if ($i -lt $Source.Length -and $Source[$i] -eq "`n") { $closePos = $i }
        return @($openPos, $closePos)
      }
    }
    $pos++
  }

  return @(-1,-1)
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 1 — Run audit
# ─────────────────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "──── Audit & Sync: generate-file-structure.php ────" -ForegroundColor Cyan
Write-Host ""

# Store pre-sync summary for comparison
$preSyncSummary = ""
$preSyncNormalCount = 0

Write-Host "[1/6] Running mirror-audit.ps1..." -ForegroundColor Yellow
$auditScript = Join-Path $repoRoot 'mirror-audit.ps1'
if (-not (Test-Path $auditScript)) {
  Write-Error "mirror-audit.ps1 not found at $auditScript"
  exit 1
}

# Capture audit output
$auditOut = & $auditScript 2>&1 | ForEach-Object { $_.ToString() }
$auditStatus = $LASTEXITCODE
$auditOut | ForEach-Object { Write-Verbose $_ }

if ($auditStatus -ne 0) {
  Write-Error "mirror-audit.ps1 exited with code $auditStatus. Check audit outputs manually."
  exit $auditStatus
}

# Read audit results
$diffListPath  = Join-Path $repoRoot 'audit\audit-diffs-list.txt'
$pathReportPath = Join-Path $repoRoot 'audit\audit-path-report.txt'
$summaryPath    = Join-Path $repoRoot 'audit\audit-summary.txt'

if (-not (Test-Path $diffListPath)) {
  Write-Error "Audit diff list not found: $diffListPath"
  exit 1
}

$diffFiles = Get-Content $diffListPath | Where-Object { $_.Trim() -ne '' } | ForEach-Object { $_.Trim() }
$pathReport = if (Test-Path $pathReportPath) { Get-Content $pathReportPath } else { @() }
$summary    = if (Test-Path $summaryPath) { (Get-Content $summaryPath | Select-Object -First 1) } else { "" }

Write-Host "      $summary" -ForegroundColor DarkGray

# ─────────────────────────────────────────────────────────────────────────────
# Step 2 — Classify and collect files
# ─────────────────────────────────────────────────────────────────────────────
Write-Host "[2/6] Classifying files..." -ForegroundColor Yellow

$testDir   = Join-Path $repoRoot 'test'
$syncList  = New-Object System.Collections.Generic.List[PSObject]
$skipList  = New-Object System.Collections.Generic.List[PSObject]

$missingInScaffold = $pathReport | Where-Object { $_ -match '^MISSING-IN-SCAFFOLD:' } |
  ForEach-Object { ($_ -replace '^MISSING-IN-SCAFFOLD:\s*','').Trim() }

foreach ($rel in $diffFiles + $missingInScaffold) {
  if ($rel -eq '') { continue }
  $testPath = Join-Path $testDir ($rel -replace '/', '\')

  if (-not (Test-Path $testPath)) {
    Write-Warning "  SKIP (not in test/):  $rel"
    $skipList.Add([PSCustomObject]@{ Path = $rel; Reason = 'Not found in test/' })
    continue
  }

  if (Test-FileIsBinary $testPath) {
    Write-Warning "  SKIP (binary):        $rel"
    $skipList.Add([PSCustomObject]@{ Path = $rel; Reason = 'Binary file (PNG/JPG/etc.)' })
    continue
  }

  $content = Get-Content $testPath -Raw -ErrorAction Stop
  $item = [PSCustomObject]@{
    RelativePath = $rel
    LocalPath     = $testPath
    Content       = $content
    IsNew         = ($missingInScaffold -contains $rel)
  }
  # Avoid duplicates
  if (-not ($syncList | Where-Object { $_.RelativePath -eq $rel })) {
    $syncList.Add($item)
  }
}

Write-Host "      Files to sync:  $($syncList.Count) (text)" -ForegroundColor White
Write-Host "      Files to skip:  $($skipList.Count) (binary / missing)" -ForegroundColor DarkGray

if ($syncList.Count -eq 0) {
  Write-Host ""
  Write-Host "No text files need syncing. generate-file-structure.php is up to date." -ForegroundColor Green
  exit 0
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 3 — Load PHP source and find array boundaries
# ─────────────────────────────────────────────────────────────────────────────
Write-Host "[3/6] Loading generate-file-structure.php..." -ForegroundColor Yellow
$genPath = Join-Path $repoRoot 'generate-file-structure.php'
if (-not (Test-Path $genPath)) {
  Write-Error "generate-file-structure.php not found at $genPath"
  exit 1
}

$phpSource = Get-Content $genPath -Raw -ErrorAction Stop
Write-Host "      Loaded $([Math]::Round($phpSource.Length / 1KB, 0)) KB, $(($phpSource -split '`n').Count) lines" -ForegroundColor DarkGray

# Find `$templates = [` (the fallback array only)
# Look for the one inside the if/else that starts the fallback: `} else {` ... `$templates = [`
# The safe approach: find the FIRST occurrence of `$templates = [` in the fallback context
$templatesMatch = [regex]::Match($phpSource, '(?s)\} else \{[^\n]*\n\s*\$templates = \[')
if ($templatesMatch.Success) {
  $arrayStart = $templatesMatch.Index + $templatesMatch.Length
} else {
  # Fallback: just use the one on line 239 (0-indexed: 238)
  Write-Warning "Could not locate fallback array via pattern. Using heuristic."
  $lines = ($phpSource -split '`n')
  # Search from line 230 onwards for the array start
  $arrayStart = 0
  for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match '^\s*\$templates = \[\s*$' -and $i -gt 220) {
      # compute character offset from line count
      for ($j = 0; $j -lt $i; $j++) { $arrayStart += 1 + $lines[$j].Length }
      break
    }
  }
}

# Find the closing `];` of this array (the first `];` after arrayStart whose indentation is 4 spaces and
# is inside the templates array context)
# Heuristic: scan from arrayStart, find the first `    ];` with 4-space indent
$searchWindow = $phpSource.Substring($arrayStart)
$searchLines  = ($searchWindow -split '`n')
$arrayEnd = $arrayStart
for ($i = 0; $i -lt $searchLines.Count; $i++) {
  if ($searchLines[$i] -match '^    \];\s*$') {
    # calculate char offset
    for ($j = 0; $j -le $i; $j++) { $arrayEnd += 1 + $searchLines[$j].Length }
    break
  }
}

if ($arrayEnd -eq $arrayStart) {
  Write-Error "Could not locate array closing boundary. Cannot proceed."
  exit 1
}

Write-Host "      Array section: offset $($arrayStart) → $($arrayEnd) (~$([Math]::Round(($arrayEnd-$arrayStart)/1KB, 0)) KB)" -ForegroundColor DarkGray

# ─────────────────────────────────────────────────────────────────────────────
# Step 4 — Build replacement entries
# ─────────────────────────────────────────────────────────────────────────────
Write-Host "[4/6] Building replacement entries..." -ForegroundColor Yellow

$builtEntries = @{}  #-relPath -> newEntryString (no surrounding newlines)
$buildErrors  = @()

foreach ($item in $syncList) {
  try {
    $entry = New-HeredocEntry -Path $item.RelativePath -Content $item.Content
    $builtEntries[$item.RelativePath] = $entry
    $statusMark = if ($item.IsNew) { "[NEW]" } else { "[UPD]" }
    Write-Host "      $statusMark $($item.RelativePath)" -ForegroundColor White
  } catch {
    $buildErrors += $item.RelativePath
    Write-Warning "  ERROR building entry for: $($item.RelativePath) — $_"
  }
}

if ($buildErrors.Count -gt 0) {
  Write-Warning "Failed to build entries for: $($buildErrors -join ', ')"
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 5 — Dry-run preview
# ─────────────────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "────────────────── DRY RUN PREVIEW ──────────────────" -ForegroundColor Magenta
Write-Host ""
Write-Host "The following $($builtEntries.Count) entries will be patched in generate-file-structure.php:"
Write-Host ""

$newEntriesSorted = $builtEntries.Keys | Sort-Object

# Show a condensed preview per entry
foreach ($path in $newEntriesSorted) {
  $item   = $syncList | Where-Object { $_.RelativePath -eq $path } | Select-Object -First 1
  $isNew  = $item.IsNew
  $label  = Get-HeredocLabel $path
  $sizeKb = [Math]::Round($builtEntries[$path].Length / 1KB, 1)
  $mark   = if ($isNew) { "NEW   " } else { "PATCH " }
  Write-Host "  $mark $path ($($sizeKb) KB heredoc, label=$label)" -ForegroundColor $(if ($isNew) { 'Green' } else { 'Yellow' })

  # Show first 3 lines of content
  $lines = ($builtEntries[$path] -split "`n" | Select-Object -First 4)
  foreach ($l in $lines) {
    Write-Host "          $l" -ForegroundColor DarkGray
  }
  Write-Host "          ..." -ForegroundColor DarkGray
  Write-Host ""
}

# Separate binary skips
if ($skipList.Count -gt 0) {
  Write-Host "── Skipped (binary / missing) ─────────────────────" -ForegroundColor DarkGray
  foreach ($s in $skipList) {
    Write-Host "  SKIP   $($s.Path)  [$($s.Reason)]" -ForegroundColor DarkGray
  }
  Write-Host ""
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 6 — Apply (if not dry-run and user confirms)
# ─────────────────────────────────────────────────────────────────────────────
if ($DryRun) {
  Write-Host "Dry-run complete. No files were modified." -ForegroundColor Cyan
  Write-Host "Run without -DryRun to apply, or -Force for unattended apply." -ForegroundColor Cyan
  exit 0
}

$proceed = $Force
if (-not $proceed) {
  Write-Host ""
  $ans = Read-Host "Apply $($builtEntries.Count) patch(es) to generate-file-structure.php? [y/N]"
  $proceed = ($ans -eq 'y' -or $ans -eq 'Y')
}

if (-not $proceed) {
  Write-Host "Aborted." -ForegroundColor Yellow
  exit 0
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 7 — Backup
# ─────────────────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "[7/9] Creating backup..." -ForegroundColor Yellow
$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDir = Join-Path $repoRoot 'audit\php-backups'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$backupPath = Join-Path $backupDir "generate-file-structure.$ts.bak"
Copy-Item $genPath $backupPath -Force
Write-Host "      Backed up to: audit\php-backups\generate-file-structure.$ts.bak" -ForegroundColor DarkGray

# ─────────────────────────────────────────────────────────────────────────────
# Step 8 — Patch PHP source
# ─────────────────────────────────────────────────────────────────────────────
Write-Host "[8/9] Patching generate-file-structure.php..." -ForegroundColor Yellow

$sanitizedSource = $phpSource
$patchCount      = 0
$insertCount     = 0
$patchErrors     = New-Object System.Collections.Generic.List[string]

foreach ($path in $newEntriesSorted) {
  $newEntry = $builtEntries[$path]
  $found    = Find-PhpHeredocEntry -Source $sanitizedSource -Path $path -SearchFrom $arrayStart -SearchTo $arrayEnd

  if ($found[0] -ge 0) {
    # Replace existing entry
    $oldEntry = $sanitizedSource.Substring($found[0], $found[1] - $found[0])
    $sanitizedSource = $sanitizedSource.Substring(0, $found[0]) + "`n" + $newEntry + "`n" + $sanitizedSource.Substring($found[1])
    # Adjust boundaries (the array section has shifted)
    $lenDiff = (1 + $newEntry.Length + 1) - $oldEntry.Length
    $arrayEnd += $lenDiff
    $patchCount++
    Write-Host "      PATCH   $path" -ForegroundColor Yellow
  } else {
    # Insert new entry — find the right alphabetical position
    $sortedExisting = New-Object System.Collections.Generic.List[string]
    $scanLen = $arrayEnd - $arrayStart
    $scan    = $sanitizedSource.Substring($arrayStart, $scanLen)
    $matches = [regex]::Matches($scan, "'([^']+)' => <<<'[^']+'\s*")
    foreach ($m in $matches) {
      $existingPath = $m.Groups[1].Value
      if ($existingPath -ne $path) {
        $null = $sortedExisting.Add($existingPath)
      }
    }

    # Find insertion point: after the last key that is < path alphabetically
    $insertAfter = -1
    $sortedExistingSorted = $sortedExisting | Sort-Object
    for ($si = 0; $si -lt $sortedExistingSorted.Count; $si++) {
      if ($sortedExistingSorted[$si] -lt $path) {
        $insertAfter = $si
      }
    }

    # Find the position by locating the heredoc entry for insertAfter key
    $insertPos = $SearchFrom = $arrayStart
    if ($insertAfter -ge 0) {
      $afterKey = $sortedExistingSorted[$insertAfter]
      $oldFound = Find-PhpHeredocEntry -Source $sanitizedSource -Path $afterKey -SearchFrom $arrayStart -SearchTo $arrayEnd
      if ($oldFound[0] -ge 0) {
        $insertPos = $oldFound[1]
      }
    } else {
      # Insert at start of array (after the opening `[`)
      $insertPos = $arrayStart
    }

    $sanitizedSource = $sanitizedSource.Substring(0, $insertPos) + "`n" + $newEntry + $sanitizedSource.Substring($insertPos)
    $lenDiff = 1 + $newEntry.Length
    $arrayEnd += $lenDiff
    $insertCount++
    Write-Host "      INSERT  $path" -ForegroundColor Green
  }
}

Write-Host "      Patched: $patchCount | Inserted: $insertCount | Total: $($patchCount + $insertCount)" -ForegroundColor White

# ─────────────────────────────────────────────────────────────────────────────
# Step 9 — Write patched source
# ─────────────────────────────────────────────────────────────────────────────
Write-Host "[9/9] Writing generate-file-structure.php..." -ForegroundColor Yellow
Set-Content -Path $genPath -Value $sanitizedSource -Encoding utf8
Write-Host "      Written. File size: $([Math]::Round($sanitizedSource.Length / 1KB, 0)) KB" -ForegroundColor DarkGray

# ─────────────────────────────────────────────────────────────────────────────
# Step 10 — Validate: PHP syntax check
# ─────────────────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "──── Validation ────────────────────────────────────" -ForegroundColor Cyan
$phpOk = $false
try {
  $err = $null
  $output = php -l $genPath 2>&1
  if ($LASTEXITCODE -eq 0) {
    Write-Host "  [OK] PHP syntax valid" -ForegroundColor Green
    $phpOk = $true
  } else {
    Write-Host "  [FAIL] PHP syntax errors:" -ForegroundColor Red
    $output | ForEach-Object { Write-Host "       $_" -ForegroundColor Red }
  }
} catch {
  Write-Warning "  PHP syntax check skipped (php not in PATH?)"
  $phpOk = $null
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 11 — Validate: template dump test
# ─────────────────────────────────────────────────────────────────────────────
$dumpOk = $false
if ($phpOk -ne $false) {
  try {
    Remove-Item (Join-Path $repoRoot 'audit\sync-verify') -Recurse -Force -ErrorAction SilentlyContinue
    $out = php (Join-Path $repoRoot 'generate-file-structure.php') --dump-templates (Join-Path $repoRoot 'audit\sync-verify') 2>&1
    if ($LASTEXITCODE -eq 0) {
      Write-Host "  [OK] Template dump succeeded" -ForegroundColor Green
      $dumpOk = $true
    } else {
      Write-Host "  [FAIL] Template dump failed:" -ForegroundColor Red
      $out | ForEach-Object { Write-Host "       $_" -ForegroundColor Red }
    }
  } catch {
    Write-Warning "  Template dump skipped"
  }
}

# ─────────────────────────────────────────────────────────────────────────────
# Step 12 — Validate: re-audit
# ─────────────────────────────────────────────────────────────────────────────
$postAudit = ""
if ($dumpOk) {
  Write-Host "  Running post-sync audit..." -ForegroundColor Yellow
  # Quick re-check: compare scaffold templates against test/
  $postDumpDir = Join-Path $repoRoot 'audit\sync-verify'
  $postDiffCount = 0
  foreach ($rel in $syncList | ForEach-Object { $_.RelativePath }) {
    $dumpPath = Join-Path $postDumpDir ($rel -replace '/', '\')
    $testPath = Join-Path $testDir  ($rel -replace '/', '\')
    if ((Test-Path $dumpPath) -and (Test-Path $testPath)) {
      $dump = Get-Content $dumpPath -Raw
      $test = Get-Content $testPath -Raw
      if ($dump -ne $test) {
        $postDiffCount++
      }
    }
  }

  $postAudit = "Post-sync content diffs remaining: $postDiffCount (from $($syncList.Count) synced files)"
  if ($postDiffCount -eq 0) {
    Write-Host "  [OK] All synced files now match between scaffold and test/" -ForegroundColor Green
  } else {
    Write-Host "  [WARN] $postAudit" -ForegroundColor Yellow
    Write-Host "         This may be due to project-variable substitution (APP_NAME, {{PROJECT_TITLE}}, etc.)" -ForegroundColor DarkGray
  }
}

# ─────────────────────────────────────────────────────────────────────────────
# Summary
# ─────────────────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "──── Summary ───────────────────────────────────────" -ForegroundColor Cyan
Write-Host "  Backup:       audit\php-backups\generate-file-structure.$ts.bak"
Write-Host "  Patched:      $patchCount entry(es)"
Write-Host "  Inserted:     $insertCount entry(es)"
Write-Host "  Skipped:      $($skipList.Count) (binary / not found):"
foreach ($s in $skipList) {
  Write-Host "                - $($s.Path) [$($s.Reason)]"
}
Write-Host "  Pre-audit:   $summary"
Write-Host "  Post-audit:  $postAudit"
Write-Host "  PHP syntax:  $(if ($phpOk) { 'VALID' } elseif ($phpOk -eq $null) { 'NOT CHECKED' } else { 'ERRORS' })"
Write-Host "  Dump test:   $(if ($dumpOk) { 'OK' } elseif ($dumpOk -eq $null) { 'SKIPPED' } else { 'FAILED' })"
Write-Host ""

if ($postDiffCount -eq 0 -and $patchErrors.Count -eq 0) {
  Write-Host "Sync complete. All text files in sync. To revert, restore from backup." -ForegroundColor Green
} else {
  if ($patchErrors.Count -gt 0) {
    Write-Host "WARNING: $($patchErrors.Count) file(s) could not be patched:" -ForegroundColor Yellow
    $patchErrors | ForEach-Object { Write-Host "  - $_" -ForegroundColor Yellow }
    Write-Host "Check generate-file-structure.php manually for these files." -ForegroundColor Yellow
  }
}

Write-Host ""