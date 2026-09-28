@echo off
title MySQL Web Client - logs
cd /d "%~dp0"
docker compose logs --tail 100 sql-client
pause
