@echo off
setlocal EnableExtensions DisableDelayedExpansion

set "BAT=%~dp0ml.bat"
if not exist "%BAT%" set "BAT=C:\ML CLI\Tools\ml.bat"
if not exist "%BAT%" (
    echo ML CLI is not installed. Expected ml.bat in "%~dp0" or "C:\ML CLI\Tools".
    exit /b 2
)

call "%BAT%" %*
exit /b %ERRORLEVEL%
