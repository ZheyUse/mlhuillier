@echo off
setlocal EnableExtensions DisableDelayedExpansion

set "BAT=%~dp0ml.bat"
if not exist "%BAT%" set "BAT=C:\ML CLI\Tools\ml.bat"
if not exist "%BAT%" (
    echo ML CLI is not installed. Expected ml.bat in "%~dp0" or "C:\ML CLI\Tools".
    exit /b 2
)

set "OUTFILE=%TEMP%\ml_out_%RANDOM%.txt"
call "%BAT%" %* > "%OUTFILE%" 2>&1
set "ML_EXIT=%ERRORLEVEL%"

for /f "tokens=1* delims=:" %%A in ("%OUTFILE%") do (
    if /I "%%A"=="CD_TO" set "CD_TO=%%B"
)

if defined CD_TO (
    if "%CD_TO:~0,1%"==" " set "CD_TO=%CD_TO:~1%"
    cd /d "%CD_TO%"
)

type "%OUTFILE%"
del /f /q "%OUTFILE%"
exit /b %ML_EXIT%
