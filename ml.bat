@echo off
setlocal enabledelayedexpansion

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

:: Handle custom commands before falling back to the generator
if /I "%~1"=="test" (
        if /I "%~2"=="userdb" (
                rem Remote-run the test script from GitHub by streaming to PHP stdin (no file saved)
                set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-con-test.php"
                echo Running remote userdb connection test from !RAW_URL! ...

                rem determine php executable
                set "PHP_EXE=php"
                if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

                                rem download to a temp file (ephemeral) then run with PHP, then delete
                                set "TMP_FILE=%TEMP%\\userdb-con-test.php"
                                where curl >nul 2>&1
                                if %ERRORLEVEL%==0 (
                                        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
                                        if %ERRORLEVEL% neq 0 (
                                                echo Failed to fetch remote test script
                                                exit /b 2
                                        )
                                        "!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
                                        set "RC=%ERRORLEVEL%"
                                        del /f /q "!TMP_FILE!" >nul 2>&1
                                        exit /b %RC%
                                )

                                powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
                                if %ERRORLEVEL% neq 0 (
                                        echo Failed to fetch remote test script
                                        exit /b 2
                                )
                                "!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
                                set "RC=%ERRORLEVEL%"
                                del /f /q "!TMP_FILE!" >nul 2>&1
                                exit /b %RC%
        )
)

if exist "C:\xampp\php\php.exe" (
        "C:\xampp\php\php.exe" "%ML_SCRIPT%" %*
        exit /b %ERRORLEVEL%
)

php "%ML_SCRIPT%" %*
exit /b %ERRORLEVEL%
