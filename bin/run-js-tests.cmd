@echo off
setlocal
cd /d "%~dp0\.."
set "PROJECT_ROOT=%CD%"

where node >nul 2>&1
if %errorlevel% neq 0 (
    echo Node.js is not installed.
    echo Download and install from https://nodejs.org
    exit /b 1
)

if not exist "%PROJECT_ROOT%\package.json" (
    echo No package.json found. Run this to create one:
    echo   cd %PROJECT_ROOT% && npm init -y
    echo Then run the test setup again.
    exit /b 1
)

if not exist "%PROJECT_ROOT%\node_modules\jsdom" (
    echo Test dependency 'jsdom' is not installed. Run this from the project root:
    echo   cd %PROJECT_ROOT% && npm install
    echo Then try again.
    exit /b 1
)

node "%PROJECT_ROOT%\bin\run-js-tests.js"
