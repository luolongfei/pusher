@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../hanson/vbot/bin/vbot
php "%BIN_TARGET%" %*
