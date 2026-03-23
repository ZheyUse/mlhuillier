@echo off

set "OUTFILE=%TEMP%\ml_out_%RANDOM%.txt"
"%~dp0ml.bat" %* > "%OUTFILE%" 2>&1

for /f "usebackq tokens=1* delims=:" %%A in ("%OUTFILE%") do (
    if /I "%%A"=="CD_TO" set "CD=%%B"
)

if defined CD (
    if "%CD:~0,1%"==" " set "CD=%CD:~1%"
    cd /d "%CD%"
)

type "%OUTFILE%"
del /f /q "%OUTFILE%"
