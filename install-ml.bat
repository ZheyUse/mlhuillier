@echo off
setlocal

set "TARGET_DIR=C:\ML CLI\Tools"
set "SOURCE_DIR=%~dp0"

rem Determine CLI version from local VERSION file if present (fallback 1.0.3)
set "CLI_VERSION=1.0.14"
if exist "%SOURCE_DIR%VERSION" (
  for /f "usebackq delims=" %%v in ("%SOURCE_DIR%VERSION") do set "CLI_VERSION=%%v"
)

echo Installing ML CLI v.%CLI_VERSION%...
echo Target: %TARGET_DIR%

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
  echo Created %TARGET_DIR%
) else (
  echo Directory already exists: %TARGET_DIR%
)

rem Progress state
set "TOTAL=3"
set /a PROGRESS=0

echo Installing Necessary Files...
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
  echo Set ML CLI version to %CLI_VERSION% in installed ml.bat
)

rem Write the VERSION file into the installed target so uninstall/readers can detect it
echo %CLI_VERSION%> "%TARGET_DIR%\VERSION"
if errorlevel 1 (
  echo [WARN] Failed to write %TARGET_DIR%\VERSION
) else (
  echo Wrote %TARGET_DIR%\VERSION (%CLI_VERSION%)
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
  echo Wrote %TARGET_DIR%\version.txt
)

set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo Installing Uninstaller...

rem Step 2: download uninstaller (GitHub-only)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{ (New-Object Net.WebClient).DownloadFile('%RAW_BASE%/uninstall-ml.bat?t=%RANDOM%%RANDOM%%RANDOM%', '%TARGET_DIR%\\uninstall-ml.bat'); exit 0 } catch { exit 2 }"
if errorlevel 1 (
  echo [ERROR] Failed to download uninstall-ml.bat
  exit /b 1
)

set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo Adding ML CLI to env path...

rem Step 3 will add the target to the user PATH below

powershell -NoProfile -ExecutionPolicy Bypass -Command "$target='C:\ML CLI\Tools'; $userPath=[Environment]::GetEnvironmentVariable('Path','User'); $parts=@(); if($userPath){$parts=$userPath -split ';' | Where-Object { $_ -and $_.Trim() -ne '' }}; $exists=$false; foreach($p in $parts){ if($p.TrimEnd('\\') -ieq $target.TrimEnd('\\')){ $exists=$true; break } }; if(-not $exists){ $newPath=(($parts + $target) | Select-Object -Unique) -join ';'; [Environment]::SetEnvironmentVariable('Path',$newPath,'User'); Write-Output 'PATH_ADDED'; } else { Write-Output 'PATH_EXISTS'; }" > "%TEMP%\ml_path_result.txt"

set "PATH_RESULT="
set /p PATH_RESULT=<"%TEMP%\ml_path_result.txt"
del "%TEMP%\ml_path_result.txt" >nul 2>&1

if /I "%PATH_RESULT%"=="PATH_ADDED" (
  echo Added C:\ML CLI\Tools to User PATH.
) else (
  echo C:\ML CLI\Tools already exists in User PATH.
)

set "PATH=%PATH%;C:\ML CLI\Tools"

rem Finalize progress
set /a PROGRESS+=1
echo Progress: %PROGRESS%/%TOTAL%

echo.
echo Installation complete.
echo You can now run: ml create banking-system
echo If command is not recognized in this window, open a new terminal.


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
