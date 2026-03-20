@echo off
setlocal EnableDelayedExpansion

set "ML_SCRIPT=%~dp0generate-file-structure.php"
set "ML_VERSION=1.0.7"
set "PHP_EXE=php"
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

if /I "%~1"=="--v" goto :show_version
if /I "%~1"=="--h" if "%~2"=="" goto :show_help
if /I "%~1"=="--h" goto :prepare_help_args

echo.
echo ==============================
echo ML CLI - M LHUILLIER FILE GENERATOR
echo https://github.com/ZheyUse
echo ==============================
echo.

if /I "%~1"=="test" if /I "%~2"=="userdb" goto :cmd_test_userdb
if /I "%~1"=="add" if /I "%~2"=="userdb" goto :cmd_add_userdb
if /I "%~1"=="create" if /I "%~2"=="--a" goto :cmd_create_account
if /I "%~1"=="--c" goto :cmd_check_version
if /I "%~1"=="update" goto :cmd_update

goto :cmd_generate

:show_version
echo.
echo ==============================
echo ML CLI - M LHUILLIER FILE GENERATOR
echo https://github.com/ZheyUse
echo ==============================
echo.
echo ML CLI version %ML_VERSION%
exit /b 0

:show_help
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
echo   --c    Check for newer version
echo.
echo Commands:
echo   test userdb        Run remote DB connection test
echo   add userdb         Import userdb SQL (migration/userdb)
echo   create --a         Create interactive account (add user)
echo   update             Update ML CLI from remote
echo   --c                Check remote ML CLI version
echo.
echo To get help for a specific command:
echo   ml --h create
echo   ml --h test userdb
echo   ml --h --c
exit /b 0

:prepare_help_args
set "ARG1=%~2"
set "ARG2=%~3"
set "ARG3=%~4"
if /I "%ARG1%"=="ml" goto :help_shift
set "CMD=%ARG1%"
set "SUB=%ARG2%"
goto :show_help_command

:help_shift
set "CMD=%ARG2%"
set "SUB=%ARG3%"
goto :show_help_command



:show_help_command
rem CMD and SUB are pre-populated by the caller
if not defined CMD goto :show_help

if /I "%CMD%"=="--c" goto :help_check_version
if /I "%CMD%"=="update" goto :help_update
if /I "%CMD%"=="create" if /I "%SUB%"=="--a" goto :help_create_account
if /I "%CMD%"=="create" goto :help_create
if /I "%CMD%"=="test" goto :help_test
if /I "%CMD%"=="add" goto :help_add

echo No help available for '%CMD%'.
exit /b 2

:help_check_version
echo.
echo HELP: Check remote version
echo Usage: ml --c
echo Description: Fetches remote VERSION and compares with local ML CLI version.
exit /b 0

:help_update
echo.
echo HELP: Update ML CLI
echo Usage: ml update
echo Description: Downloads and runs the remote updater to replace installed CLI files.
exit /b 0

:help_create
echo.
echo HELP: Create project
echo Usage: ml create ^<project_name^>
echo Description: Generates project scaffold using generator script.
exit /b 0

:help_create_account
echo.
echo HELP: Create account (interactive)
echo Usage: ml create --a
echo Description: Downloads and runs the remote `account-insert.php` script which
echo   interactively prompts for ID, first/last name and role, then inserts a user
echo   into the `users` table and an `active` entry into `userlogs`.
exit /b 0

:help_test
if /I "%SUB%"=="userdb" goto :help_test_userdb
echo.
echo HELP: Test commands
echo Usage: ml test userdb
echo Description: Run specific tests; use ml --h test userdb for DB test help.
exit /b 0

:help_test_userdb
echo.
echo HELP: Test userdb
echo Usage: ml test userdb
echo Description: Downloads and runs a remote PHP script that checks userdb connection and schema.
exit /b 0

:help_add
if /I "%SUB%"=="userdb" goto :help_add_userdb
echo.
echo HELP: Add commands
echo Usage: ml add userdb
echo Description: Imports the userdb SQL dump into your server (downloads if not present locally).
exit /b 0

:help_add_userdb
echo.
echo HELP: Add userdb
echo Usage: ml add userdb
echo Description: Downloads/imports migration/userdb SQL files to create the userdb schema and tables.
exit /b 0

:cmd_test_userdb
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-con-test.php"
set "TMP_FILE=%TEMP%\userdb-con-test.php"
echo Running remote userdb connection test from !RAW_URL! ...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote test script
        exit /b 2
)

"!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
set "RC=%ERRORLEVEL%"
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_add_userdb
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-import.php"
set "TMP_FILE=%TEMP%\userdb-import.php"
echo Running remote userdb import from !RAW_URL! ...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote import script
        exit /b 2
)

"!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
set "RC=%ERRORLEVEL%"
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_check_version
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/VERSION"
set "TMP_FILE=%TEMP%\ml_remote_version.txt"
set "REMOTE_VER="
echo Checking remote ML CLI version from !RAW_URL! ...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote VERSION
        exit /b 2
)

set /p REMOTE_VER=<"!TMP_FILE!"
del /f /q "!TMP_FILE!" >nul 2>&1

if not defined REMOTE_VER (
        echo Unable to determine remote version.
        exit /b 2
)

if "%REMOTE_VER%"=="%ML_VERSION%" (
        echo.
        echo Version is up to date.
        echo Current Version: %ML_VERSION%
        exit /b 0
)

echo.
echo New version is available.
echo version: %REMOTE_VER%
echo.
echo to update to the latest version
echo Use: ml update
exit /b 0

:cmd_update
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/ml-update.php"
set "TMP_FILE=%TEMP%\ml-update.php"
set "LOCAL_UPDATER=%~dp0ml-update.php"
echo Updating ML CLI from !RAW_URL! ...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)

if %ERRORLEVEL%==0 (
        "!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
        set "RC=%ERRORLEVEL%"
        del /f /q "!TMP_FILE!" >nul 2>&1
        if "%RC%"=="0" exit /b 0
        if exist "!LOCAL_UPDATER!" (
                echo Remote updater returned an error, trying local updater...
                "!PHP_EXE!" -d display_errors=0 "!LOCAL_UPDATER!"
                exit /b %ERRORLEVEL%
        )
        exit /b %RC%
)

if exist "!LOCAL_UPDATER!" (
        echo Remote update fetch failed, trying local updater...
        "!PHP_EXE!" -d display_errors=0 "!LOCAL_UPDATER!"
        exit /b %ERRORLEVEL%
)

echo Failed to fetch remote updater and no local updater was found.
exit /b 2

:cmd_create_account
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/account-insert.php"
set "TMP_FILE=%TEMP%\account-insert.php"
echo Running remote account creation from !RAW_URL! ...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote account script
        exit /b 2
)

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!"
set "RC=%ERRORLEVEL%"
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_generate
if exist "C:\xampp\php\php.exe" (
        "C:\xampp\php\php.exe" "%ML_SCRIPT%" %*
        exit /b %ERRORLEVEL%
)

php "%ML_SCRIPT%" %*
exit /b %ERRORLEVEL%
