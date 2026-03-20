@echo off
setlocal

set "ML_SCRIPT=%~dp0generate-file-structure.php"
rem CLI version
set "ML_VERSION=1.0.2"

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

:: Handle custom commands before falling back to the generator
if /I "%~1"=="test" (
        if /I "%~2"=="userdb" (
                set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-con-test.php"
                set "TMP_FILE=%TEMP%\\userdb-con-test.php"
                echo Fetching userdb connection test...
                curl -s -f -o "%TMP_FILE%" "%RAW_URL%"
                if %ERRORLEVEL% neq 0 (
                        powershell -Command "Try { (New-Object System.Net.WebClient).DownloadFile('%RAW_URL%','%TMP_FILE%'); exit 0 } Catch { exit 1 }"
                        if %ERRORLEVEL% neq 0 (
                                echo Failed to download test script from %RAW_URL%
                                exit /b 2
                        )
                )
                if exist "C:\xampp\php\php.exe" (
                        "C:\xampp\php\php.exe" "%TMP_FILE%"
                        set "RC=%ERRORLEVEL%"
                        del /f /q "%TMP_FILE%" >nul 2>&1
                        exit /b %RC%
                )
                php "%TMP_FILE%"
                set "RC=%ERRORLEVEL%"
                del /f /q "%TMP_FILE%" >nul 2>&1
                exit /b %RC%
        )
)

php "%ML_SCRIPT%" %*
exit /b %ERRORLEVEL%
