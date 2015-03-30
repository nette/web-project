@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../nette/tester/src/tester
php "%BIN_TARGET%" %*
