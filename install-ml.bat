@echo off
setlocal EnableExtensions

set "TARGET_DIR=C:\ML CLI\Tools"
set "SOURCE_DIR=%~dp0"

rem Determine CLI version from local VERSION file if present (fallback 1.0.3)
set "CLI_VERSION=1.0.32"
if exist "%SOURCE_DIR%VERSION" (
  for /f "usebackq delims=" %%v in ("%SOURCE_DIR%VERSION") do set "CLI_VERSION=%%v"
)

echo [INFO] Installing ML CLI v.%CLI_VERSION%...
echo [INFO] Target: %TARGET_DIR%

rem Console-friendly intro before installation (ASCII fallback)
echo.
echo ==============================
echo ML CLI Installer
echo https://github.com/ZheyUse
echo ==============================
echo.


rem Download required CLI files from the GitHub repo if not bundling locally.
set "RAW_BASE=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main"


rem If local assets are missing, we'll download them from the repository instead
rem (don't fail the installer just because source assets aren't present).

if not exist "%TARGET_DIR%" (
  mkdir "%TARGET_DIR%" 2>nul
  if errorlevel 1 (
    echo [ERROR] Failed to create %TARGET_DIR%
    echo Try running this installer as Administrator.
    exit /b 1
  )
  echo [INFO] Created %TARGET_DIR%
) else (
  echo [INFO] Directory already exists: %TARGET_DIR%
)

rem Progress state
set "TOTAL=5"
set /a PROGRESS=0

echo [INFO] Installing necessary files...
echo Progress: %PROGRESS%/%TOTAL%

rem Step 1: download generator stub and CLI batch (GitHub-only)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/generate-file-remote.php?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\generate-file-structure.php'); (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/ml.bat?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\ml.bat'); exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [ERROR] Failed to download necessary files
  exit /b 1
)
rem Ensure installed ml.bat reports the desired CLI version regardless of remote copy
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (Get-Content '%TARGET_DIR%\\ml.bat') -replace 'set \"ML_VERSION=.*\"','set \"ML_VERSION=%CLI_VERSION%\"' | Set-Content -Encoding ASCII '%TARGET_DIR%\\ml.bat'; exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [WARN] Unable to enforce ML_VERSION in installed ml.bat
) else (
  echo [INFO] Set ML CLI version to %CLI_VERSION% in installed ml.bat
)

rem Write the VERSION file into the installed target so uninstall/readers can detect it
echo %CLI_VERSION%> "%TARGET_DIR%\VERSION"
if errorlevel 1 (
  echo [WARN] Failed to write %TARGET_DIR%\VERSION
) else (
  echo [INFO] Wrote %TARGET_DIR%\VERSION (%CLI_VERSION%)
)

rem Also write a human-readable version.txt with source and timestamp for users
(
  echo ML CLI Installer
  echo Version: %CLI_VERSION%
  echo Source: %RAW_BASE%
  echo InstalledAt: %DATE% %TIME%
)> "%TARGET_DIR%\version.txt"
if errorlevel 1 (
  echo [WARN] Failed to write %TARGET_DIR%\version.txt
) else (
  echo [INFO] Wrote %TARGET_DIR%\version.txt
)

set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo [INFO] Installing uninstaller...

rem Step 2: download uninstaller (GitHub-only)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/uninstall-ml.bat?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\uninstall-ml.bat'); exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [ERROR] Failed to download uninstall-ml.bat
  exit /b 1
)

set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo [INFO] Installing shell wrappers (ml.cmd) and helper installer...

rem Step: download wrappers and installer helper into the target folder
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/ml.cmd?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\ml.cmd'); (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/install-wrappers-auto.ps1?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\install-wrappers-auto.ps1'); if(Test-Path '%TARGET_DIR%\ml.ps1'){ Remove-Item -Force '%TARGET_DIR%\ml.ps1' }; exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [WARN] Failed to download one or more wrapper files (continuing)
) else (
  echo [INFO] Installed wrappers and helper into %TARGET_DIR%
)
if exist "%TARGET_DIR%\install-wrappers-auto.ps1" (
  echo [INFO] Wrapper helper present: %TARGET_DIR%\install-wrappers-auto.ps1
) else (
  echo [INFO] Wrapper helper missing after download step.
)

set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo [INFO] Injecting PowerShell profile function...
set "PROFILE_INJECT_STATUS=SKIPPED_HELPER_MISSING"
if exist "%TARGET_DIR%\install-wrappers-auto.ps1" (
  echo [INFO] Running install-wrappers-auto.ps1 (COPIED_/SKIPPED_ lines are informational)
  powershell -NoProfile -ExecutionPolicy Bypass -File "%TARGET_DIR%\install-wrappers-auto.ps1"
  if errorlevel 1 (
    echo [WARN] Failed to inject/update PowerShell profile function (continuing)
    set "PROFILE_INJECT_STATUS=FAILED"
  ) else (
    echo [INFO] PowerShell profile function injected/verified.
    set "PROFILE_INJECT_STATUS=SUCCESS"
  )
)
echo [INFO] Profile injection status: %PROFILE_INJECT_STATUS%

echo [INFO] Ensuring ml.cmd is installed in %%USERPROFILE%%\bin...
if not exist "%USERPROFILE%\bin" mkdir "%USERPROFILE%\bin" >nul 2>&1
if exist "%TARGET_DIR%\ml.cmd" (
  copy /Y "%TARGET_DIR%\ml.cmd" "%USERPROFILE%\bin\ml.cmd" >nul
  if errorlevel 1 (
    echo [WARN] Could not copy ml.cmd to %%USERPROFILE%%\bin
  ) else (
    echo [INFO] Installed ml.cmd to %%USERPROFILE%%\bin
  )
) else (
  echo [WARN] %TARGET_DIR%\ml.cmd not found; cannot copy to %%USERPROFILE%%\bin
)

set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo [INFO] Adding ML CLI to env path...

rem Step 3 will add the target to the user PATH below

powershell -NoProfile -ExecutionPolicy Bypass -Command "$target='C:\ML CLI\Tools'; $userPath=[Environment]::GetEnvironmentVariable('Path','User'); $parts=@(); if($userPath){$parts=$userPath -split ';' | Where-Object { $_ -and $_.Trim() -ne '' }}; $exists=$false; foreach($p in $parts){ if($p.TrimEnd('\\') -ieq $target.TrimEnd('\\')){ $exists=$true; break } }; if(-not $exists){ $newPath=(($parts + $target) | Select-Object -Unique) -join ';'; [Environment]::SetEnvironmentVariable('Path',$newPath,'User'); Write-Output 'PATH_ADDED'; } else { Write-Output 'PATH_EXISTS'; }" > "%TEMP%\ml_path_result.txt"

set "PATH_RESULT="
set /p PATH_RESULT=<"%TEMP%\ml_path_result.txt"
del "%TEMP%\ml_path_result.txt" >nul 2>&1

if /I "%PATH_RESULT%"=="PATH_ADDED" (
  echo [INFO] Added C:\ML CLI\Tools to User PATH.
) else if /I "%PATH_RESULT%"=="PATH_EXISTS" (
  echo [INFO] C:\ML CLI\Tools already exists in User PATH.
) else (
  echo [WARN] Could not confirm PATH update result. Raw value: %PATH_RESULT%
)

set "PATH=%PATH%;C:\ML CLI\Tools"

rem Finalize progress
set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo.
echo [INFO] Installation complete.
echo [INFO] You can now run: ml create banking-system
echo [INFO] If command is not recognized in this window, open a new terminal.


rem Write the "Made By" ASCII art into the installed CLI folder (safe batch write)
(
  echo ┏┳┓┏━┓╺┳┓┏━╸   ┏┓ ╻ ╻
  echo ┃┃┃┣━┫ ┃┃┣╸    ┣┻┓┗┳┛
  echo ╹ ╹╹ ╹╺┻┛┗━╸   ┗━┛ ╹ 
  echo  ██████╗ ██████╗ ██████╗ ███████╗███████╗
  echo ██╔════╝██╔═══██╗██╔══██╗██╔════╝╚══███╔╝
  echo ██║     ██║   ██║██║  ██║█████╗    ███╔╝ 
  echo ██║     ██║   ██║██║  ██║██╔══╝   ███╔╝  
  echo ╚██████╗╚██████╔╝██████╔╝███████╗███████╗
  echo  ╚═════╝ ╚═════╝ ╚═════╝ ╚══════╝╚══════╝
  echo.
  echo Follow: https://github.com/ZheyUse
) > "%TARGET_DIR%\made-by.txt"

echo.
echo ==============================
echo ML CLI Installed to %TARGET_DIR%
echo https://github.com/ZheyUse
echo ==============================
echo.

exit /b 0
