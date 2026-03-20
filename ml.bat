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
                rem Remote-run the test script from GitHub and offer to add the DB if missing
                set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-con-test.php"
                echo Running remote userdb connection test from !RAW_URL! ...

                rem determine php executable
                set "PHP_EXE=php"
                if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

                rem download to a temp file (ephemeral) then run with PHP, capture output
                set "TMP_FILE=%TEMP%\\userdb-con-test.php"
                set "TMP_OUT=%TEMP%\\ml_userdb_test_out.txt"
                where curl >nul 2>&1
                if %ERRORLEVEL%==0 (
                        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
                        if %ERRORLEVEL% neq 0 (
                                echo Failed to fetch remote test script
                                exit /b 2
                        )
                ) else (
                        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
                        if %ERRORLEVEL% neq 0 (
                                echo Failed to fetch remote test script
                                exit /b 2
                        )
                )

                "!PHP_EXE!" -d display_errors=0 "!TMP_FILE!" > "!TMP_OUT!" 2>&1
                set "RC=%ERRORLEVEL%"
                type "!TMP_OUT!"

                rem If the output indicates the DB is missing, offer to run add userdb
                findstr /C:"Database does not exist" "!TMP_OUT!" >nul 2>&1
                if %ERRORLEVEL%==0 (
                        set /p _choice=Do you want to add userdb in your server? (y/n): 
                        if /I "%%_choice%%"=="y" (
                                call "%~dp0ml.bat" add userdb
                                set "RC=%ERRORLEVEL%"
                        )
                )

                del /f /q "!TMP_FILE!" >nul 2>&1
                del /f /q "!TMP_OUT!" >nul 2>&1
                exit /b %RC%
        )
)

if /I "%~1"=="add" (
        if /I "%~2"=="userdb" (
                rem Stream remote import script from GitHub into PHP (no file saved)
                set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-import.php"
                echo Running remote userdb import from !RAW_URL! ...

                rem determine php executable
                set "PHP_EXE=php"
                if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

                where curl >nul 2>&1
                if %ERRORLEVEL%==0 (
                        rem curl available: stream directly into php
                        curl -s -f "!RAW_URL!" | "!PHP_EXE!" -d display_errors=0 -
                        set "RC=%ERRORLEVEL%"
                        exit /b %RC%
                ) else (
                        rem No curl: use PowerShell to fetch string and pipe to php without writing file
                        powershell -NoProfile -Command "Try { $s=(New-Object Net.WebClient).DownloadString('!RAW_URL!'); if($s -eq $null){ exit 2 } ; $s | & '!PHP_EXE!' -d display_errors=0 - ; exit $LASTEXITCODE } Catch { exit 2 }"
                        set "RC=%ERRORLEVEL%"
                        exit /b %RC%
                )
        )
)

if exist "C:\xampp\php\php.exe" (
        "C:\xampp\php\php.exe" "%ML_SCRIPT%" %*
        exit /b %ERRORLEVEL%
)

php "%ML_SCRIPT%" %*
exit /b %ERRORLEVEL%
