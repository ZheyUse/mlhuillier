@echo off
rem Convenience shim to run the PowerShell installer for ml wrappers
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-wrappers.ps1"
