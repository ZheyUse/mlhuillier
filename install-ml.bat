@echo off
setlocal EnableExtensions

set "TARGET_DIR=C:\ML CLI\Tools"
set "SOURCE_DIR=%~dp0"
set "MODE=INSTALL"
if /I "%~1"=="--update" set "MODE=UPDATE"

rem Determine CLI version from local VERSION file if present (fallback 1.0.3)
set "CLI_VERSION=1.0.33"
if exist "%SOURCE_DIR%VERSION" (
  for /f "usebackq delims=" %%v in ("%SOURCE_DIR%VERSION") do set "CLI_VERSION=%%v"
)

echo.
echo +--------------------------------------+
echo ^|            ML CLI INSTALL            ^|
echo +--------------------------------------+
echo Version : %CLI_VERSION%
echo Mode    : %MODE%
echo Target  : %TARGET_DIR%
echo.


rem Download required CLI files from the GitHub repo if not bundling locally.
set "RAW_BASE=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main"


rem If local assets are missing, we'll download them from the repository instead
rem (don't fail the installer just because source assets aren't present).

rem Progress state
set "TOTAL_STEPS=5"
set /a CURRENT_STEP=0
call :show_progress %CURRENT_STEP% %TOTAL_STEPS%
echo.

echo [1/5] Installing core files...
if not exist "%TARGET_DIR%" (
  mkdir "%TARGET_DIR%" 2>nul
  if errorlevel 1 (
    echo [X] Failed to create %TARGET_DIR%
    echo   Try running this installer as Administrator.
    exit /b 1
  )
)
echo [OK] Directory ready
echo Downloading core files...

rem Step 1: download generator stub and CLI batch (GitHub-only)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/generate-file-remote.php?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\generate-file-structure.php'); (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/ml.bat?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\ml.bat'); exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [X] Failed to download core files
  exit /b 1
)
echo [OK] Download complete
rem Ensure installed ml.bat reports the desired CLI version regardless of remote copy
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (Get-Content '%TARGET_DIR%\\ml.bat') -replace 'set \"ML_VERSION=.*\"','set \"ML_VERSION=%CLI_VERSION%\"' | Set-Content -Encoding ASCII '%TARGET_DIR%\\ml.bat'; exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [!] Could not enforce ML_VERSION in installed ml.bat
) else (
  echo [OK] CLI version synchronized
)

rem Write the VERSION file into the installed target so uninstall/readers can detect it
echo %CLI_VERSION%> "%TARGET_DIR%\VERSION"
if errorlevel 1 (
  echo [!] Failed to write VERSION file
) else (
  echo [OK] VERSION file written
)

rem Also write a human-readable version.txt with source and timestamp for users
(
  echo ML CLI Installer
  echo Version: %CLI_VERSION%
  echo Source: %RAW_BASE%
  echo InstalledAt: %DATE% %TIME%
)> "%TARGET_DIR%\version.txt"
if errorlevel 1 (
  echo [!] Failed to write version.txt
) else (
  echo [OK] version.txt written
)

set /a CURRENT_STEP+=1
call :show_progress %CURRENT_STEP% %TOTAL_STEPS%
echo.

echo [2/5] Installing uninstaller...
echo Downloading uninstaller...

rem Step 2: download uninstaller (GitHub-only)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/uninstall-ml.bat?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\uninstall-ml.bat'); exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [X] Failed to download uninstall-ml.bat
  exit /b 1
)
echo [OK] Download complete

set /a CURRENT_STEP+=1
call :show_progress %CURRENT_STEP% %TOTAL_STEPS%
echo.

echo [3/5] Installing wrappers...
echo Downloading wrapper files...

rem Step: download wrappers and installer helper into the target folder
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/ml.cmd?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\ml.cmd'); (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/install-wrappers-auto.ps1?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\install-wrappers-auto.ps1'); if(Test-Path '%TARGET_DIR%\ml.ps1'){ Remove-Item -Force '%TARGET_DIR%\ml.ps1' }; exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [!] Failed to download one or more wrapper files (continuing)
) else (
  echo [OK] Wrapper download complete
)
if exist "%TARGET_DIR%\install-wrappers-auto.ps1" (
  echo [OK] Wrapper helper detected
) else (
  echo [!] Wrapper helper missing; profile injection may be skipped
)

set /a CURRENT_STEP+=1
call :show_progress %CURRENT_STEP% %TOTAL_STEPS%
echo.

echo [4/5] Configuring environment...
echo Configuring PowerShell profile integration...
set "PROFILE_INJECT_STATUS=SKIPPED_HELPER_MISSING"
if exist "%TARGET_DIR%\install-wrappers-auto.ps1" (
  powershell -NoProfile -ExecutionPolicy Bypass -File "%TARGET_DIR%\install-wrappers-auto.ps1" >nul 2>&1
  if errorlevel 1 (
    echo [!] Failed to inject/update PowerShell profile function
    set "PROFILE_INJECT_STATUS=FAILED"
  ) else (
    echo [OK] PowerShell profile function injected/verified
    set "PROFILE_INJECT_STATUS=SUCCESS"
  )
)
if /I "%PROFILE_INJECT_STATUS%"=="SKIPPED_HELPER_MISSING" (
  echo [!] Profile injection skipped (helper missing)
)

echo Installing shell entrypoint...
if not exist "%USERPROFILE%\bin" mkdir "%USERPROFILE%\bin" >nul 2>&1
if exist "%TARGET_DIR%\ml.cmd" (
  copy /Y "%TARGET_DIR%\ml.cmd" "%USERPROFILE%\bin\ml.cmd" >nul
  if errorlevel 1 (
    echo [!] Could not copy ml.cmd to %%USERPROFILE%%\bin
  ) else (
    echo [OK] ml.cmd installed to %%USERPROFILE%%\bin
  )
) else (
  echo [!] %TARGET_DIR%\ml.cmd not found; cannot copy to %%USERPROFILE%%\bin
)

echo Updating User PATH...

rem Step 3 will add the target to the user PATH below

powershell -NoProfile -ExecutionPolicy Bypass -Command "$target='C:\ML CLI\Tools'; $userPath=[Environment]::GetEnvironmentVariable('Path','User'); $parts=@(); if($userPath){$parts=$userPath -split ';' | Where-Object { $_ -and $_.Trim() -ne '' }}; $exists=$false; foreach($p in $parts){ if($p.TrimEnd('\\') -ieq $target.TrimEnd('\\')){ $exists=$true; break } }; if(-not $exists){ $newPath=(($parts + $target) | Select-Object -Unique) -join ';'; [Environment]::SetEnvironmentVariable('Path',$newPath,'User'); Write-Output 'PATH_ADDED'; } else { Write-Output 'PATH_EXISTS'; }" > "%TEMP%\ml_path_result.txt"

set "PATH_RESULT="
set /p PATH_RESULT=<"%TEMP%\ml_path_result.txt"
del "%TEMP%\ml_path_result.txt" >nul 2>&1

if /I "%PATH_RESULT%"=="PATH_ADDED" (
  echo [OK] Added C:\ML CLI\Tools to User PATH
) else if /I "%PATH_RESULT%"=="PATH_EXISTS" (
  echo [OK] C:\ML CLI\Tools already exists in User PATH
) else (
  echo [!] Could not confirm PATH update result. Raw value: %PATH_RESULT%
)

set "PATH=%PATH%;C:\ML CLI\Tools"

set /a CURRENT_STEP+=1
call :show_progress %CURRENT_STEP% %TOTAL_STEPS%
echo.

echo [5/5] Finalizing setup...

echo Writing metadata...


rem Write the "Made By" ASCII art into the installed CLI folder (safe batch write)
(
  echo ML CLI Installer
  echo.
  echo Installed by: ZheyUse
  echo.
  echo Follow: https://github.com/ZheyUse
) > "%TARGET_DIR%\made-by.txt"

if errorlevel 1 (
  echo [!] Could not write made-by.txt
) else (
  echo [OK] Metadata written
)

set /a CURRENT_STEP+=1
call :show_progress %CURRENT_STEP% %TOTAL_STEPS%

echo.
echo +------------------------------+
echo ^|     INSTALLATION COMPLETE    ^|
echo +------------------------------+
echo.
echo Command: ml create my-project
echo.
echo Note: Restart terminal if command is not recognized
echo.

exit /b 0

:show_progress
setlocal EnableDelayedExpansion
set /a PCT=(%~1*100)/%~2
set "BAR=.........."
if !PCT! GEQ 20 set "BAR=##........"
if !PCT! GEQ 40 set "BAR=####......"
if !PCT! GEQ 60 set "BAR=######...."
if !PCT! GEQ 80 set "BAR=########.."
if !PCT! GEQ 100 set "BAR=##########"
echo [!BAR!] !PCT!%%
endlocal
exit /b 0
