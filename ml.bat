@echo off
setlocal

set "ML_SCRIPT=%~dp0generate-file-structure.php"
rem CLI version
set "ML_VERSION=1.0.0"

rem Handle top-level flags before invoking the PHP generator
if /I "%~1"=="--v" (
      echo.
echo ==============================
echo ML CLI - M LHUILLIER FILE GENERATOR
echo https://github.com/ZheyUse
echo ==============================
echo.
        echo ML CLI version %ML_VERSION%
        exit /b 0
)
if /I "%~1"=="--h" (
          echo.
echo ==============================
echo ML CLI - M LHUILLIER FILE GENERATOR
echo https://github.com/ZheyUse
echo ==============================
echo.
        echo Usage: ml create ^<project_name^>
        echo.
        echo Flags:
        echo   --h    Show this help
        echo   --v    Show version
        exit /b 0
)

rem Show short ASCII intro when the CLI is invoked interactively
if /I not "%~1"=="--v" if /I not "%~1"=="--h" (
   echo.
echo ==============================
echo ML CLI - M LHUILLIER FILE GENERATOR
echo https://github.com/ZheyUse
echo ==============================
echo.
)

if exist "C:\xampp\php\php.exe" (
        "C:\xampp\php\php.exe" "%ML_SCRIPT%" %*
        exit /b %ERRORLEVEL%
)

php "%ML_SCRIPT%" %*
exit /b %ERRORLEVEL%
