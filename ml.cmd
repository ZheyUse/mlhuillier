@echo off
set "BAT=%~dp0ml.bat"
if not exist "%BAT%" set "BAT=C:\ML CLI\Tools\ml.bat"
if defined ML_DEBUG echo [ml.cmd] wrapper: %~f0
if defined ML_DEBUG echo [ml.cmd] using bat: %BAT%
if not exist "%BAT%" (
    echo ML CLI is not installed. Expected ml.bat in "%~dp0" or "C:\ML CLI\Tools".
    exit /b 2
)

if /I not "%~1"=="nav" (
    call "%BAT%" %*
    exit /b %ERRORLEVEL%
)

call "%BAT%" %*
set "ML_EXIT=%ERRORLEVEL%"
set "FINAL_CD="

if "%~2"=="" if exist "C:\xampp\htdocs" set "FINAL_CD=C:\xampp\htdocs"
if not "%~2"=="" call :resolveNavTarget "%~2"

if defined FINAL_CD (
    if defined ML_DEBUG echo [ml.cmd] final cd target: %FINAL_CD%
    cd /d "%FINAL_CD%" >nul 2>&1
)

exit /b %ML_EXIT%

:resolveNavTarget
set "PROJECT_NAME=%~1"
if "%PROJECT_NAME:~0,2%"=="--" set "PROJECT_NAME=%PROJECT_NAME:~2%"
if "%PROJECT_NAME%"=="" exit /b 0
if exist "C:\xampp\htdocs\%PROJECT_NAME%" set "FINAL_CD=C:\xampp\htdocs\%PROJECT_NAME%"
exit /b 0
