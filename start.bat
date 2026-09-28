@echo off
title MySQL Web Client
cd /d "%~dp0"

docker info >nul 2>&1
if errorlevel 1 (
    echo.
    echo  Docker Desktop is not running.
    echo  Please open Docker Desktop, wait until it says "Engine running", then run this again.
    echo.
    pause
    exit /b 1
)

if not exist ".env" (
    copy ".env.example" ".env" >nul
    echo.
    echo  A settings file ".env" was created. Notepad will open it now.
    echo  Enter your MySQL username and password, SAVE the file, close Notepad,
    echo  and this window will continue.
    echo.
    notepad ".env"
)

echo.
echo  Starting MySQL Web Client (the first start can take a few minutes)...
echo.
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo  Something went wrong. See the messages above.
    pause
    exit /b 1
)

set APP_PORT=8080
for /f "tokens=1,* delims==" %%a in ('findstr /b "APP_PORT=" ".env"') do set APP_PORT=%%b

echo.
echo  Started. Opening http://localhost:%APP_PORT% in your browser...
timeout /t 5 >nul
start "" "http://localhost:%APP_PORT%"
echo.
pause
