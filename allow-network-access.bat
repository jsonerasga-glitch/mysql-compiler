@echo off
title MySQL Web Client - allow phones and other PCs
cd /d "%~dp0"

rem Windows Firewall changes need administrator rights: re-launch elevated.
net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

set APP_PORT=8080
if exist ".env" for /f "tokens=1,* delims==" %%a in ('findstr /b "APP_PORT=" ".env"') do set APP_PORT=%%b

netsh advfirewall firewall delete rule name="MySQL Web Client" >nul 2>&1
netsh advfirewall firewall add rule name="MySQL Web Client" dir=in action=allow protocol=TCP localport=%APP_PORT% profile=any >nul
if errorlevel 1 (
    echo.
    echo  Could not add the firewall rule.
    pause
    exit /b 1
)

echo.
echo  Done. Other devices on the same Wi-Fi/network can now open the app at:
echo.
powershell -NoProfile -Command "Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway } | ForEach-Object { '    http://' + $_.IPv4Address.IPAddress + ':%APP_PORT%' }"
echo.
pause
