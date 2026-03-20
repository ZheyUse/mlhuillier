@echo off
setlocal EnableExtensions

set "TARGET_DIR=C:\ML CLI\Tools"
set "UNINSTALL_VERSION=2026.03.19.5"

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

echo Uninstalling ML CLI...
echo Version: %UNINSTALL_VERSION%
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

if not exist "%TARGET_DIR%" (
  echo [SUCCESS] Uninstall complete. Directory already removed.
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
