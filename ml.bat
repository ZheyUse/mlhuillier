@echo off
setlocal EnableDelayedExpansion

set "ML_SCRIPT=%~dp0generate-file-structure.php"
set "ML_VERSION=1.0.48"
set "PHP_EXE=php"
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

rem In CMD, ml.bat can be resolved before ml.cmd. For `ml nav`, bounce to ml.cmd
rem once so directory changes are applied by the wrapper expected for navigation.
if /I "%~1"=="nav" if not defined ML_NAV_BRIDGE if exist "%~dp0ml.cmd" (
        set "ML_NAV_BRIDGE=1"
        call "%~dp0ml.cmd" %*
        exit /b %ERRORLEVEL%
)

if /I "%~1"=="--v" goto :show_version
if /I "%~1"=="--h" if "%~2"=="" goto :show_help
if /I "%~1"=="--h" goto :prepare_help_args
if /I "%~1"=="--d" goto :cmd_download_installer
if /I "%~1"=="doc" goto :cmd_docs
if /I "%~1"=="docs" goto :cmd_docs
if /I "%~1"=="--b" goto :cmd_backup

echo.
echo ==============================
echo ML CLI - M LHUILLIER FILE GENERATOR
echo https://github.com/ZheyUse
echo ==============================
echo.

if /I "%~1"=="test" if "%~2"=="" goto :cmd_test_list
if /I "%~1"=="test" if /I "%~2"=="userdb" goto :cmd_test_userdb
if /I "%~1"=="test" goto :cmd_test_db
if /I "%~1"=="add" if /I "%~2"=="userdb" goto :cmd_add_userdb
if /I "%~1"=="create" if /I "%~2"=="--a" goto :cmd_create_account
if /I "%~1"=="create" if /I "%~2"=="--config" goto :cmd_create_config
if /I "%~1"=="create" if /I "%~2"=="--pbac" goto :cmd_create_pbac
if /I "%~1"=="create" if /I "%~2"=="--rbac" goto :cmd_create_rbac
if /I "%~1"=="create" if "%~2"=="" goto :cmd_create_list
if /I "%~1"=="--c" goto :cmd_check_version
if /I "%~1"=="update" goto :cmd_update
if /I "%~1"=="nav" goto :cmd_nav
if /I "%~1"=="clone" if /I "%~2"=="local" goto :cmd_clone_local
if /I "%~1"=="serve" goto :cmd_serve

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
echo   --d    Download remote installer
echo   --b    Backup schemas (use ml --b [schema])
echo   --a    Account creation (use with `ml create --a`)
echo.
echo Commands:
echo   test <database>     Run DB connection test for a specified database (e.g., userdb, gledb)
echo   add userdb         Import userdb SQL (migration/userdb)
echo   nav                Navigate or open a project (ml nav)
echo   serve              Open current project in browser (ml serve)
echo   doc                Open online documentation (GitHub Pages)
echo   create --a         Create interactive account (add user)
echo   create --config    Create DB config for backups
echo   create --pbac      Create PBAC table in userdb
echo   create --rbac      Create RBAC table in userdb
echo   update             Update ML CLI from remote
echo   --d                Download remote installer
echo   --c                Check remote ML CLI version
echo.
echo To get help for a specific command:
echo   ml --h create
echo   ml --h test userdb
echo   ml --h --c
echo   ml --h create --a
echo   ml --h create --config
echo   ml --h --d
echo   ml --h serve
echo   ml --h nav
echo   ml --h add userdb
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
if /I "%CMD%"=="--d" goto :help_download_installer
if /I "%CMD%"=="doc" goto :help_docs
if /I "%CMD%"=="docs" goto :help_docs
if /I "%CMD%"=="nav" goto :help_nav
if /I "%CMD%"=="serve" goto :help_serve
if /I "%CMD%"=="dev" goto :help_dev
if /I "%CMD%"=="--b" goto :help_backup
if /I "%CMD%"=="create" if /I "%SUB%"=="--a" goto :help_create_account
if /I "%CMD%"=="create" if /I "%SUB%"=="--config" goto :help_create_config
if /I "%CMD%"=="create" if /I "%SUB%"=="--pbac" goto :help_create_pbac
if /I "%CMD%"=="create" if /I "%SUB%"=="--rbac" goto :help_create_rbac
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

:help_download_installer
echo.
echo HELP: Download installer
echo Usage: ml --d
echo Description: Downloads the remote installer downloader script and runs it to fetch the full installer.
exit /b 0

:help_docs
echo.
echo HELP: Documentation
echo Usage: ml doc
echo Description: Opens the project documentation in your default browser.
echo   By default this opens the hosted docs at:
echo   https://zheyuse.github.io/mlhuillier/documentation/
echo   If you have installed the CLI locally the installer also places a
echo   copy of the documentation under C:\ML CLI\Tools\documentation\
exit /b 0

:help_create
echo.
echo HELP: Create project
echo Usage: ml create ^<project_name^>
echo Description: Generates project scaffold using generator script.
echo.
echo Subcommands:
echo   --a         Create interactive account (add user)
echo   --config    Create DB config for backups
echo   --pbac      Create PBAC table in userdb
echo   --rbac      Create RBAC table in userdb
exit /b 0

:cmd_create_list
echo Missing arguments for ml create
echo below are the list of create commands you can use
echo.
echo Create List:
echo   create ^<project_name^>   Generate project scaffold (use: ml create myproject)
echo   create --a         Create interactive account (add user)
echo   create --config    Create DB config for backups
echo   create --pbac      Create PBAC table in userdb
echo   create --rbac      Create RBAC table in userdb
exit /b 2

:cmd_test_list
echo Missing arguments for ml test
echo below are the list of test commands you can use
echo.
echo Test List:
echo   test ^<database^>    Run DB connection test for a specified database (e.g., userdb, gledb)
echo   test userdb         Run the default userdb connectivity and schema checks
exit /b 2

:help_create_account
echo.
echo HELP: Create account (interactive)
echo Usage: ml create --a
echo Description: Downloads and runs the remote `account-insert.php` script which
echo   interactively prompts for ID, first/last name and role, then inserts a user
echo   into the `users` table and an `active` entry into `userlogs`.
exit /b 0

:help_create_config
echo.
echo HELP: Create DB config
echo Usage: ml create --config
echo Description: Interactive helper that creates the DB config used by 'ml --b'.
echo   Writes a JSON config to C:\ML CLI\Tools\mlcli-config.json.
exit /b 0

:help_create_pbac
echo.
echo HELP: Create PBAC table
echo Usage: ml create --pbac [project_name]
echo Description: Creates a Permission Based Access Control table in 'userdb' for ^<project_name^>.
exit /b 0

:help_create_rbac
echo.
echo HELP: Create RBAC table
echo Usage: ml create --rbac [project_name]
echo Description: Creates a Role Based Access Control table in 'userdb' for ^<project_name^>.
exit /b 0

:help_backup
echo.
echo HELP: Backup schemas
echo Usage: ml --b [schema]
echo Description: Lists available schemas on the configured DB server and creates SQL dumps.
echo   If no schema provided, you'll be prompted. Use 'ml create --config' to set DB connection.
exit /b 0

:help_test
if /I "%SUB%"=="userdb" goto :help_test_userdb
echo.
echo HELP: Test commands
echo Usage: ml test ^<database^>
echo Description: Run connectivity tests for a chosen database.
echo.
echo Examples:
echo   ml test userdb
echo   ml test gledb
exit /b 0

:help_test_userdb
echo.
echo HELP: Test userdb
echo Usage: ml test <database>
echo Description: Downloads and runs a remote PHP script that checks the specified database connection and schema (default example: userdb).
exit /b 0

:help_serve
echo.
echo HELP: Serve project
echo Usage: ml serve [project_name]
echo Description: Remote-only helper. Fetches and runs the GitHub-hosted
echo   ml-serve.php which prints and opens the project URL at
echo   http://localhost/<project_name>. No local fallback if fetch fails.
exit /b 0

:help_nav
echo.
echo HELP: Nav
echo Usage: ml nav
echo.
echo Arguments:
echo   1. ml nav --projectname
echo      Navigates to your created project folder under C:\xampp\htdocs and prompts to open
echo      the project in VSCode (Y/N).
echo.
echo   2. ml nav --projectname --remote
echo      Runs the remote ml-nav.php helper to perform remote-assisted navigation or actions.
echo.
echo Notes:
echo   - Running ml nav with no arguments changes directory to C:\xampp\htdocs and exits.
echo   - Set ML_REMOTE=1 to disable editor launches and prompts (useful for CI).
exit /b 0

:help_dev
echo.
echo HELP: Developer commands
echo Usage: ml --h dev
echo.
echo Dev-only commands (not shown in standard help):
echo   clone local [destination]  Copy local ML CLI files to C:\ML CLI\Tools for testing
echo                             If no destination is provided, default is C:\ML CLI\Tools
echo.
echo Environment flags useful for development/testing:
echo   ML_DEV=1    Force use of the local developer copy
echo   ML_REPO=1   Indicate commands are running from the repository workspace
echo.
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
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\userdb-con-test.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing test connection to userdb...

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

"!PHP_EXE!" -d display_errors=0 "!TMP_FILE!" "%~2"
        set "RC=%ERRORLEVEL%"
        call :maybe_show_update_notice
        del /f /q "!TMP_FILE!" >nul 2>&1
        exit /b %RC%

:cmd_test_db
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-con-test.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\userdb-con-test.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing test connection to %~2...

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

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" "%~2"
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_create_config
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/db-config/db-config.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-db-config.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Creating ML CLI DB config...
echo.

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote config script
        exit /b 2
)

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!"
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%
:cmd_create_pbac
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/pbac/ml-pbac.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-pbac.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing create PBAC helper...
echo.

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote pbac script
        exit /b 2
)

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" %~3
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_create_rbac
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/rbac/ml-rbac.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-rbac.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing create RBAC helper...
echo.

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote rbac script
        exit /b 2
)

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" %~3
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_backup
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/backup-cli/backup-db.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-backup-db.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing backup helper...
echo.

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote backup script
        exit /b 2
)

if "%~2"=="" (
        "%PHP_EXE%" -d display_errors=0 "!TMP_FILE!"
) else (
        "%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" "%~2"
)
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_add_userdb
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/userdb-import.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\userdb-import.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing userdb import...

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
        call :maybe_show_update_notice
        del /f /q "!TMP_FILE!" >nul 2>&1
        exit /b %RC%

:cmd_check_version
set "TMP_FILE=%TEMP%\ml_remote_version.txt"
set "REMOTE_VER="
echo Checking remote ML CLI version from GitHub API (fallback raw) ...

call :fetch_remote_version "!TMP_FILE!"
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote VERSION from both API and raw endpoint.
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
set "TMP_VER=%TEMP%\ml_remote_version.txt"
set "REMOTE_VER="
set "UPDATER_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/ml-update.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "UPDATER_URL=!UPDATER_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-update.php"
set "LOCAL_UPDATER=%~dp0ml-update.php"

echo Checking remote ML CLI version from GitHub API...

call :fetch_remote_version "!TMP_VER!"
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch remote VERSION, proceeding with update...
) else (
        set /p REMOTE_VER=<"!TMP_VER!"
        if "%REMOTE_VER%"=="%ML_VERSION%" (
                del /f /q "!TMP_VER!" >nul 2>&1
                echo.
                echo Your ML CLI is up to date.
                echo Current Version: %ML_VERSION%
                exit /b 0
        ) else (
                del /f /q "!TMP_VER!" >nul 2>&1
        )
)

call :strip_query "!UPDATER_URL!"
rem URL hidden from output
echo Updating ML CLI...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!UPDATER_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!UPDATER_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)

if %ERRORLEVEL%==0 (
        "!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
        set "RC=%ERRORLEVEL%"
        del /f /q "!TMP_FILE!" >nul 2>&1
        if "!RC!"=="0" exit /b 0
        if exist "!LOCAL_UPDATER!" (
                echo Remote updater returned an error, trying local updater...
                "!PHP_EXE!" -d display_errors=0 "!LOCAL_UPDATER!"
                exit /b %ERRORLEVEL%
        )
        exit /b !RC!
)

if exist "!LOCAL_UPDATER!" (
        echo Remote update fetch failed, trying local updater...
        "!PHP_EXE!" -d display_errors=0 "!LOCAL_UPDATER!"
        exit /b %ERRORLEVEL%
)

echo Failed to fetch remote updater and no local updater was found.
exit /b 2

:fetch_remote_version
set "OUT_FILE=%~1"
set "API_URL=https://api.github.com/repos/ZheyUse/mlhuillier/contents/VERSION?ref=main"
set "FETCH_RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/VERSION?t=%RANDOM%%RANDOM%%RANDOM%"

powershell -NoProfile -Command "Try { $h=@{'User-Agent'='ml-cli'}; $j=Invoke-RestMethod -Headers $h -Uri '%API_URL%'; $v=[Text.Encoding]::UTF8.GetString([Convert]::FromBase64String(($j.content -replace '\s',''))).Trim(); if([string]::IsNullOrWhiteSpace($v)){ exit 3 }; Set-Content -Path '%OUT_FILE%' -Value $v -Encoding ASCII -NoNewline; exit 0 } Catch { exit 2 }"
if %ERRORLEVEL%==0 exit /b 0

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "%OUT_FILE%" "%FETCH_RAW_URL%"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('%FETCH_RAW_URL%','%OUT_FILE%'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 exit /b 2

exit /b 0

:: Strip query string from a URL and store in DISPLAY_URL
:strip_query
set "IN=%~1"
for /f "delims=?" %%A in ("%IN%") do set "DISPLAY_URL=%%A"
exit /b 0

:: Check remote VERSION and print a short update notice if newer
:maybe_show_update_notice
set "TMP_VER=%TEMP%\ml_remote_version.txt"
call :fetch_remote_version "!TMP_VER!" >nul 2>&1
if %ERRORLEVEL% neq 0 (
        del /f /q "!TMP_VER!" >nul 2>&1
        exit /b 0
)
set /p REMOTE_VER=<"!TMP_VER!"
del /f /q "!TMP_VER!" >nul 2>&1
if defined REMOTE_VER if not "%REMOTE_VER%"=="%ML_VERSION%" (
        echo.
        echo New Version is available!!!
        echo Version: %REMOTE_VER%
        echo run: ml update  - to update to the latest version
        echo.
)
exit /b 0

:cmd_create_account
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/account-insert.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\account-insert.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing account creation...
echo.

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
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_serve
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/ml-serve.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-serve.php"
rem URL hidden from output
echo Executing serve helper...
echo.

rem --- Ensure local Apache is running; attempt to start XAMPP Apache or Apache service if not ---
echo Checking for local Apache process...
rem Check for typical Apache process names first
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | findstr /I "httpd.exe" >nul
if %ERRORLEVEL%==0 goto :apache_up
tasklist /FI "IMAGENAME eq apache.exe" 2>NUL | findstr /I "apache.exe" >nul
if %ERRORLEVEL%==0 goto :apache_up

goto :try_start_apache

:try_start_apache
echo Apache not running; attempting to start XAMPP Apache or Apache service...
rem Prefer interactive XAMPP control, service install/start, or falling back to httpd binary
set "XAMPP_ROOT=C:\xampp"
set "XAMPP_CONTROL=%XAMPP_ROOT%\xampp-control.exe"
set "XAMPP_HTTPD=%XAMPP_ROOT%\apache\bin\httpd.exe"
set "XAMPP_START=%XAMPP_ROOT%\xampp_start.exe"
set "TMP_INSTALL=%TEMP%\ml_install_apache_service.bat"

if exist "%XAMPP_CONTROL%" (
        echo Found XAMPP Control Panel at %XAMPP_CONTROL%.
        echo.
        echo How would you like to start Apache?
        echo  [1] Open XAMPP Control Panel (manual Start)
        echo  [2] Install Apache as Windows service and start (requires admin)
        echo  [3] Start Apache directly (no service, may not reflect in Control Panel)
        set /p "CHOICE=Select 1/2/3 (default 1): "
        if "%CHOICE%"=="" set "CHOICE=1"
        if "%CHOICE%"=="1" (
                echo Launching XAMPP Control Panel...
                start "" "%XAMPP_CONTROL%"
                goto wait_apache
        ) else if "%CHOICE%"=="2" (
                if not exist "%XAMPP_HTTPD%" (
                        echo Apache binary not found at %XAMPP_HTTPD%. Cannot install service.
                        goto try_start_fallback
                )
                echo Preparing elevated installer to create Apache service...
                > "%TMP_INSTALL%" echo @echo off
                >> "%TMP_INSTALL%" echo "%XAMPP_HTTPD%" -k install -n "Apache2.4"
                >> "%TMP_INSTALL%" echo sc start Apache2.4
                echo Requesting elevation to install Apache service...
                powershell -NoProfile -Command "Start-Process -FilePath '%TMP_INSTALL%' -Verb RunAs"
                echo Installer launched. Waiting a moment for service to start...
                ping -n 4 127.0.0.1 >nul
                goto wait_apache
        ) else (
                echo Starting Apache binary directly...
                start "" /B "%XAMPP_HTTPD%" -k start
                goto wait_apache
        )
) else (
        goto try_start_fallback
)

:try_start_fallback
if exist "%XAMPP_HTTPD%" (
        echo Starting XAMPP Apache binary...
        start "" /B "%XAMPP_HTTPD%" -k start
        goto wait_apache
) else if exist "%XAMPP_START%" (
        echo Running XAMPP start helper...
        start "" /B "%XAMPP_START%"
        goto wait_apache
) else (
        rem attempt to start common Apache services
        sc query Apache2.4 >nul 2>&1
        if %ERRORLEVEL%==0 (
                echo Starting Apache2.4 service...
                sc start Apache2.4
        )
        sc query Apache2 >nul 2>&1
        if %ERRORLEVEL%==0 (
                echo Starting Apache2 service...
                sc start Apache2
        )
)

rem wait up to 15 seconds for apache process to appear
set "tries=0"
:wait_apache
set /a tries+=1
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | findstr /I "httpd.exe" >nul
if %ERRORLEVEL%==0 goto :apache_up
tasklist /FI "IMAGENAME eq apache.exe" 2>NUL | findstr /I "apache.exe" >nul
if %ERRORLEVEL%==0 goto :apache_up
if %tries% GEQ 15 (
        echo Failed to start Apache within timeout. Please start XAMPP Apache manually and retry.
        exit /b 2
)
timeout /t 1 >nul
goto :wait_apache

:apache_up
echo Apache process detected and running.

:download_serve_script

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch serve helper script
        exit /b 2
)

set "ARGS="
rem Determine project name (prefer explicit arg, otherwise use current directory)
set "PROJECT="
if not "%~2"=="" (
        set "PROJECT=%~2"
) else (
        for %%D in ("%CD%") do set "PROJECT=%%~nxD"
)

if not defined PROJECT (
        echo Unable to determine project name. Use: ml serve <project_name> or run inside project folder.
        del /f /q "!TMP_FILE!" >nul 2>&1
        exit /b 2
)

rem Sanitize project path for the serve helper so the remote script receives
rem a web-relative path (e.g. "leah/public/"), not an absolute Windows path.
set "SERVE_PROJECT=!PROJECT!"
if not defined SERVE_PROJECT set "SERVE_PROJECT="
rem Normalize to forward slashes
set "SERVE_PROJECT=!SERVE_PROJECT:\=/!"

rem If path contains xampp/htdocs or htdocs, strip everything up to that folder
echo !SERVE_PROJECT! | findstr /I "xampp/htdocs/" >nul
if not errorlevel 1 (
        set "SERVE_PROJECT=!SERVE_PROJECT:*xampp/htdocs/=!"
) else (
        echo !SERVE_PROJECT! | findstr /I "htdocs/" >nul
        if not errorlevel 1 set "SERVE_PROJECT=!SERVE_PROJECT:*htdocs/=!"
)

rem Remove leading drive letter if present (e.g. C:/... -> remove C:)
if not "!SERVE_PROJECT:~1,1!"=="" (
        if "!SERVE_PROJECT:~1,1!"==":" set "SERVE_PROJECT=!SERVE_PROJECT:~2!"
)

:serve_strip_leading
if "!SERVE_PROJECT:~0,1!"=="/" (
        set "SERVE_PROJECT=!SERVE_PROJECT:~1!"
        goto serve_strip_leading
)

rem If user passed an empty project, fall back to current folder name
if "!SERVE_PROJECT!"=="" (
        for %%D in ("%CD%") do set "SERVE_PROJECT=%%~nxD"
)
rem Remove trailing slashes
:serve_strip_trailing
if "!SERVE_PROJECT:~-1!"=="/" (
        set "SERVE_PROJECT=!SERVE_PROJECT:~0,-1!"
        goto serve_strip_trailing
)

rem Remove any '/public' segments so we pass the project root (e.g., 'leah')
set "SERVE_PROJECT=!SERVE_PROJECT:/public=!"

rem Use only the top-level folder (project name) for the URL
for /f "tokens=1 delims=/" %%P in ("!SERVE_PROJECT!") do set "SERVE_PROJECT=%%P"

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" "!SERVE_PROJECT!"
set "RC=%ERRORLEVEL%"
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%
:cmd_nav
set "HTDOCS_DIR=C:\xampp\htdocs"
if "%~2"=="" (
        for %%D in ("%HTDOCS_DIR%") do cd /d "%%~fD" & echo Now in %%~fD & exit /b 0
)

set "NAV_ARG=%~2"
if not "%NAV_ARG%"=="" (
        rem If user provided a single-dash flag (likely a typo), show top-level help
        if "%NAV_ARG:~0,1%"=="-" if not "%NAV_ARG:~0,2%"=="--" (
                echo Invalid flag: %NAV_ARG%
                echo.
                call :show_help
                exit /b 2
        )

        if "%NAV_ARG:~0,2%"=="--" (
                set "PROJECT_NAME=%NAV_ARG:~2%"
                if defined PROJECT_NAME (
                        set "PROJECT_PATH=%HTDOCS_DIR%\!PROJECT_NAME!"
                        if exist "!PROJECT_PATH!" (
                                echo Now in !PROJECT_PATH!
                                if not defined ML_REMOTE (
                                        set "OPEN_IN_VSCODE="
                                        set /p OPEN_IN_VSCODE=Do you want to open !PROJECT_NAME! in VSCode? ^(Y/N^): 
                                        if /I "!OPEN_IN_VSCODE:~0,1!"=="Y" (
                                                where code >nul 2>&1
                                                if errorlevel 1 (
                                                        echo VSCode CLI ^(code^) not found in PATH.
                                                ) else (
                                                        tasklist /FI "IMAGENAME eq Code.exe" | find /I "Code.exe" >nul
                                                        if errorlevel 1 (
                                                                code "!PROJECT_PATH!" >nul 2>&1
                                                        ) else (
                                                                code --new-window "!PROJECT_PATH!" >nul 2>&1
                                                        )
                                                )
                                        )
                                )
                                for %%D in ("!PROJECT_PATH!") do cd /d "%%~fD" & exit /b 0
                        ) else (
                                echo Project not found: !PROJECT_PATH!
                                exit /b 2
                        )
                )
        )
)

set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/ml-nav.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-nav.php"
set "TMP_OUT=%TEMP%\ml-nav.out"
call :strip_query "!RAW_URL!"
rem URL hidden from output

echo Executing navigation helper...
echo.

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch navigation script
        exit /b 2
)

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" %* --remote > "!TMP_OUT!" 2>&1
set "RC=%ERRORLEVEL%"

set "CD_TO="
for /f "usebackq tokens=1* delims=:" %%A in ("!TMP_OUT!") do (
        if /I "%%A"=="CD_TO" set "CD_TO=%%B"
)

rem Trim leading space
if defined CD_TO (
        if "!CD_TO:~0,1!"==" " set "CD_TO=!CD_TO:~1!"
)

if defined CD_TO (
        if exist "!CD_TO!" (
                del /f /q "!TMP_FILE!" "!TMP_OUT!" >nul 2>&1
                for %%D in ("!CD_TO!") do cd /d "%%~fD" & echo Now in %%~fD & exit /b 0
        ) else (
                echo Target folder not found: !CD_TO!
        )
)

call :maybe_show_update_notice
del /f /q "!TMP_FILE!" "!TMP_OUT!" >nul 2>&1
exit /b %RC%

:cmd_clone_local
set "LOCAL_PHP=%~dp0ml-local.php"
if not exist "!LOCAL_PHP!" (
        echo Local installer script not found: !LOCAL_PHP!
        exit /b 2
)

echo Executing local clone installer...
"%PHP_EXE%" -d display_errors=0 "!LOCAL_PHP!" %~3 %~4 %~5 %~6 %~7 %~8 %~9
set "RC=%ERRORLEVEL%"
exit /b %RC%

:cmd_download_installer
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/download-installer.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\download-installer.php"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing installer downloader...

where curl >nul 2>&1
if %ERRORLEVEL%==0 (
        curl -s -f -o "!TMP_FILE!" "!RAW_URL!"
) else (
        powershell -NoProfile -Command "Try { (New-Object Net.WebClient).DownloadFile('!RAW_URL!','!TMP_FILE!'); exit 0 } Catch { exit 2 }"
)
if %ERRORLEVEL% neq 0 (
        echo Failed to fetch downloader script
        exit /b 2
)

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!"
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_generate
if exist "C:\xampp\php\php.exe" (
        "C:\xampp\php\php.exe" "%ML_SCRIPT%" %*
        set "RC=%ERRORLEVEL%"
        call :maybe_show_update_notice
        exit /b %RC%
)

php "%ML_SCRIPT%" %*
set "RC=%ERRORLEVEL%"
call :maybe_show_update_notice
exit /b %RC%

:cmd_docs
echo.
echo Opening ML CLI documentation (online)...
start "" "https://zheyuse.github.io/mlhuillier/documentation/"
call :maybe_show_update_notice
exit /b 0
