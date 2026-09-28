@echo off
title MySQL Web Client
cd /d "%~dp0"
docker compose down
echo.
echo  MySQL Web Client stopped.
pause
