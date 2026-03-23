@echo off
setlocal EnableExtensions DisableDelayedExpansion

set "BAT=%~dp0ml.bat"
if not exist "%BAT%" set "BAT=C:\ML CLI\Tools\ml.bat"
if not exist "%BAT%" (
    echo ML CLI is not installed. Expected ml.bat in "%~dp0" or "C:\ML CLI\Tools".
    exit /b 2
)

if /I not "%~1"=="nav" (
    call "%BAT%" %*
    set "ML_EXIT=%ERRORLEVEL%"
    endlocal & exit /b %ML_EXIT%
)

if /I "%~2"=="--new" (
    call "%BAT%" %*
    set "ML_EXIT=%ERRORLEVEL%"
    for %%D in ("C:\xampp\htdocs") do endlocal & cd /d "%%~fD" >nul 2>&1 & exit /b %ML_EXIT%
)

set "NAV_ARG=%~2"
if not "%NAV_ARG%"=="" (
    if "%NAV_ARG:~0,2%"=="--" (
        set "PROJECT_NAME=%NAV_ARG:~2%"
        if defined PROJECT_NAME (
            set "PROJECT_PATH=C:\xampp\htdocs\%PROJECT_NAME%"
            call "%BAT%" %*
            set "ML_EXIT=%ERRORLEVEL%"
            if exist "%PROJECT_PATH%" (
                for %%D in ("%PROJECT_PATH%") do endlocal & cd /d "%%~fD" >nul 2>&1 & exit /b %ML_EXIT%
            )
            endlocal & exit /b %ML_EXIT%
        )
    )
)

set "OUTFILE=%TEMP%\ml_out_%RANDOM%.txt"
call "%BAT%" %* > "%OUTFILE%" 2>&1
set "ML_EXIT=%ERRORLEVEL%"

set "CD_TO="
for /f "tokens=1,2,* delims= " %%A in ('findstr /B /C:"Now in " "%OUTFILE%"') do set "CD_TO=%%C"

type "%OUTFILE%"
del /f /q "%OUTFILE%" >nul 2>&1

if defined CD_TO (
    for %%D in ("%CD_TO%") do endlocal & cd /d "%%~fD" >nul 2>&1 & exit /b %ML_EXIT%
)

endlocal & exit /b %ML_EXIT%
