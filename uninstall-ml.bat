@echo off
setlocal EnableExtensions

set "TARGET_DIR=C:\ML CLI\Tools"
set "USER_BIN=%USERPROFILE%\bin"
set "USER_ML_CMD=%USER_BIN%\ml.cmd"
set "USER_ML_BAT=%USER_BIN%\ml.bat"
set "USER_ML_PS1=%USER_BIN%\ml.ps1"
set "USER_WRAPPER_HELPER=%USER_BIN%\install-wrappers-auto.ps1"
set "UNINSTALL_VERSION=1.0.36"

rem Determine installed CLI version from installed VERSION file if present
set "CLI_VERSION=%UNINSTALL_VERSION%"
if exist "%TARGET_DIR%\VERSION" (
  for /f "usebackq delims=" %%v in ("%TARGET_DIR%\VERSION") do set "CLI_VERSION=%%v"
)

rem If script is launched from the install folder, re-run from TEMP first.
if /I "%~1" NEQ "--from-temp" if /I "%~dp0"=="%TARGET_DIR%\" goto :RUN_FROM_TEMP

goto :RUN_UNINSTALL

:RUN_FROM_TEMP
set "TMP_RUNNER=%TEMP%\ml_uninstall_runner_%RANDOM%%RANDOM%.bat"
copy /Y "%~f0" "%TMP_RUNNER%" >nul
if errorlevel 1 (
  echo [FAIL] Could not create temp runner.
  exit /b 1
)
cd /d "%TEMP%" >nul 2>&1
start "" /B cmd /c ""%TMP_RUNNER%" --from-temp & del /q "%TMP_RUNNER%""
exit /b 0

:RUN_UNINSTALL
cd /d "%TEMP%" >nul 2>&1

echo Uninstalling ML CLI v.%CLI_VERSION%...
echo Target: %TARGET_DIR%

powershell -NoProfile -ExecutionPolicy Bypass -Command "$target='C:\ML CLI\Tools'; $userPath=[Environment]::GetEnvironmentVariable('Path','User'); if(-not $userPath){ Write-Output 'PATH_EMPTY'; exit 0 }; $parts=$userPath -split ';' | Where-Object { $_ -and $_.Trim() -ne '' }; $filtered=@(); foreach($p in $parts){ if($p.TrimEnd('\\') -ine $target.TrimEnd('\\')){ $filtered += $p } }; if($filtered.Count -ne $parts.Count){ [Environment]::SetEnvironmentVariable('Path',($filtered -join ';'),'User'); Write-Output 'PATH_REMOVED'; } else { Write-Output 'PATH_NOT_FOUND'; }" > "%TEMP%\ml_uninstall_path_result.txt"

set "PATH_RESULT="
set /p PATH_RESULT=<"%TEMP%\ml_uninstall_path_result.txt"
del "%TEMP%\ml_uninstall_path_result.txt" >nul 2>&1

if /I "%PATH_RESULT%"=="PATH_REMOVED" (
  echo Removed C:\ML CLI\Tools from User PATH.
) else if /I "%PATH_RESULT%"=="PATH_NOT_FOUND" (
  echo C:\ML CLI\Tools was not found in User PATH.
) else (
  echo User PATH is empty or unchanged.
)

echo Cleaning user wrappers in %USER_BIN%...
for %%F in ("%USER_ML_CMD%" "%USER_ML_BAT%" "%USER_ML_PS1%" "%USER_WRAPPER_HELPER%") do (
  if exist "%%~F" (
    del /f /q "%%~F" >nul 2>&1
    if exist "%%~F" (
      echo [WARN] Could not remove %%~F
    ) else (
      echo Removed %%~F
    )
  ) else (
    echo %%~F not found.
  )
)

echo Cleaning PowerShell profile shim (function ml) if present...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$profiles=@($PROFILE.CurrentUserAllHosts, (Join-Path $HOME 'Documents\WindowsPowerShell\Microsoft.PowerShell_profile.ps1'), (Join-Path $HOME 'Documents\PowerShell\Microsoft.PowerShell_profile.ps1')) | Select-Object -Unique; foreach($pf in $profiles){ if(-not $pf){ continue }; if(-not (Test-Path -LiteralPath $pf)){ Write-Output ('PROFILE_NOT_FOUND:' + $pf); continue }; $raw=Get-Content -LiteralPath $pf -Raw -ErrorAction SilentlyContinue; if($null -eq $raw){ $raw='' }; $updated=[Regex]::Replace($raw,'(?s)function\s+ml\s*\{.*?ml wrapper not found.*?\r?\n\}',''); if($updated -ne $raw){ Set-Content -LiteralPath $pf -Value $updated -Encoding UTF8; Write-Output ('PROFILE_ML_REMOVED:' + $pf) } else { Write-Output ('PROFILE_ML_NOT_FOUND:' + $pf) } }" > "%TEMP%\ml_uninstall_profile_result.txt"
for /f "usebackq delims=" %%L in ("%TEMP%\ml_uninstall_profile_result.txt") do echo %%L
del "%TEMP%\ml_uninstall_profile_result.txt" >nul 2>&1

if not exist "%TARGET_DIR%" (
  echo [SUCCESS] Uninstall complete. Directory already removed.
  echo Open a new terminal so PATH/profile updates are reflected.
  exit /b 0
)

echo Removing %TARGET_DIR%...
attrib -R "%TARGET_DIR%\*" /S /D >nul 2>&1

for /L %%N in (1,1,8) do (
  powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ Remove-Item -LiteralPath 'C:\ML CLI\Tools' -Recurse -Force -ErrorAction Stop; exit 0 } catch { exit 2 }" >nul 2>&1
  if not exist "%TARGET_DIR%" goto :REMOVED
  timeout /t 1 /nobreak >nul
)

echo [FAIL] Could not remove %TARGET_DIR%.
echo Close terminals/editors that may be using files in that folder and run uninstaller again.
echo Remaining files:
dir "%TARGET_DIR%" /A /B 2>nul
exit /b 1

:REMOVED
echo Removed %TARGET_DIR%
echo [SUCCESS] Uninstall complete.
echo Open a new terminal so PATH updates are reflected.
exit /b 0
