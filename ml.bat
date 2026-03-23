@echo off
setlocal EnableDelayedExpansion

set "ML_SCRIPT=%~dp0generate-file-structure.php"
set "ML_VERSION=1.0.26"
set "PHP_EXE=php"
if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"

if /I "%~1"=="--v" goto :show_version
if /I "%~1"=="--h" if "%~2"=="" goto :show_help
if /I "%~1"=="--h" goto :prepare_help_args
if /I "%~1"=="--d" goto :cmd_download_installer

rem If the first argument is a long flag (starts with --) but not a recognized global flag, show help
set "ARG1=%~1"
if not "%ARG1%"=="" (
        if "!ARG1:~0,2!"=="--" (
                if /I not "%ARG1%"=="--v" if /I not "%ARG1%"=="--h" if /I not "%ARG1%"=="--d" if /I not "%ARG1%"=="--c" goto :show_help
        )
)

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
if /I "%~1"=="serve" goto :cmd_serve
if /I "%~1"=="nav" goto :cmd_nav

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
echo   --a    Account creation (use with `ml create --a`)
echo.
echo Commands:
echo   test userdb        Run remote DB connection test
echo   add userdb         Import userdb SQL (migration/userdb)
echo   serve              Open current project in browser (ml serve)
echo   nav                Interactive navigation for htdocs projects
echo   create --a         Create interactive account (add user)
echo   update             Update ML CLI from remote
echo   --d                Download remote installer
echo   --c                Check remote ML CLI version
echo.
echo To get help for a specific command:
echo   ml --h create
echo   ml --h test userdb
echo   ml --h --c
echo   ml --h create --a
echo   ml --h --d
echo   ml --h serve
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
if /I "%CMD%"=="create" if /I "%SUB%"=="--a" goto :help_create_account
if /I "%CMD%"=="create" goto :help_create
if /I "%CMD%"=="test" goto :help_test
if /I "%CMD%"=="add" goto :help_add
if /I "%CMD%"=="nav" goto :help_nav

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

:help_serve
echo.
echo HELP: Serve project
echo Usage: ml serve [project_name]
echo Description: Remote-only helper. Fetches and runs the GitHub-hosted
echo   ml-serve.php which prints and opens the project URL at
echo   http://localhost/<project_name>. No local fallback if fetch fails.
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

:help_nav
echo.
echo HELP: Navigation helper
echo Usage: ml nav [--new] [--<project_name>] [--remote]
echo Description: Interactive helper to change directories to projects under C:\xampp\htdocs.
echo   Use --new to go to C:\xampp\htdocs. Use --remote or set ML_REMOTE=1 to avoid opening
echo   VSCode from the remote environment.
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

"!PHP_EXE!" -d display_errors=0 "!TMP_FILE!"
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
echo Checking remote ML CLI version from GitHub API ...

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

"%PHP_EXE%" -d display_errors=0 "!TMP_FILE!" "%PROJECT%"
set "RC=%ERRORLEVEL%"
del /f /q "!TMP_FILE!" >nul 2>&1
exit /b %RC%

:cmd_nav
set "RAW_URL=https://raw.githubusercontent.com/ZheyUse/mlhuillier/main/ml-nav.php"
set "CACHE_BUST=%RANDOM%%RANDOM%%RANDOM%"
set "RAW_URL=!RAW_URL!?t=!CACHE_BUST!"
set "TMP_FILE=%TEMP%\ml-nav.php"
set "TMP_OUT=%TEMP%\ml-nav.out"
call :strip_query "!RAW_URL!"
rem URL hidden from output
echo Executing navigation helper...
echo.

rem If running inside PowerShell, ensure a shell wrapper is present in the user's profile
set "MLBAT=%~dp0ml.bat"
if defined PSModulePath (
        set "TMP_PS=%TEMP%\ml_profile_check.ps1"
        >"%TMP_PS%" echo $p = $PROFILE
        >>"%TMP_PS%" echo if(-not (Test-Path $p)){ New-Item -ItemType File -Force -Path $p | Out-Null }
        >>"%TMP_PS%" echo $c = ''
        >>"%TMP_PS%" echo try{ $c = Get-Content $p -Raw } catch {}
        >>"%TMP_PS%" echo if($c -notmatch 'function\s+ml\s*\{') {
        >>"%TMP_PS%" echo $func = 'function ml { param([Parameter(ValueFromRemainingArguments=$true)] $Args) $out = & ''%MLBAT%'' @Args 2>&1; foreach ($line in $out) { if ($line -match ''^CD_TO:\s*(.+)$'') { Set-Location $Matches[1]; return } } $out | ForEach-Object { Write-Output $_ } }'
        >>"%TMP_PS%" echo Add-Content -Path $p -Value $func
        >>"%TMP_PS%" echo Write-Output 'ML wrapper added to profile: ' + $p
        >>"%TMP_PS%" echo }
        powershell -NoProfile -ExecutionPolicy Bypass -File "%TMP_PS%"
        del /f /q "%TMP_PS%" >nul 2>&1
)

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
                cd /d "!CD_TO!"
                echo Now in !CD_TO!
        ) else (
                echo Target folder not found: !CD_TO!
        )
)

call :maybe_show_update_notice
del /f /q "!TMP_FILE!" "!TMP_OUT!" >nul 2>&1
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
