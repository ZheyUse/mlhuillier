ML CLI wrappers
----------------

Files added:

- ml.cmd — cmd.exe wrapper. Runs ml.bat, reads CD_TO: and performs cd /d in the running cmd session.
- ml.ps1 — PowerShell wrapper. Runs ml.bat, reads CD_TO: and runs Set-Location in the running PowerShell session.

Usage:

1. Files are in the repo root. Add the repo folder to PATH or copy ml.cmd to a folder on PATH.
2. For PowerShell, either dot-source ml.ps1 in your profile or call it directly.

Examples:

In cmd.exe:
  ml.cmd nav --new

In PowerShell (one-off):
  & .\ml.ps1 nav --new

To persist PowerShell behavior, add a line to your $PROFILE to dot-source ml.ps1.

Push these files to your remote repository so other environments can use the wrappers.
