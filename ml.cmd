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

set "CD_TO="
for /f "tokens=1,2,* delims= " %%A in ('findstr /B /C:"Now in " "%OUTFILE%"') do set "CD_TO=%%C"

type "%OUTFILE%"
del /f /q "%OUTFILE%" >nul 2>&1

if defined CD_TO (
    for %%D in ("%CD_TO%") do endlocal & cd /d "%%~fD" >nul 2>&1 & exit /b %ML_EXIT%
)

endlocal & exit /b %ML_EXIT%
