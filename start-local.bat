@echo off
echo ========================================
echo Highway GBBS - Local Development Setup
echo ========================================
echo.
echo This script will help you start the frontend development server.
echo Make sure WAMP is running before starting!
echo.
echo Step 1: Ensure WAMP is running (Apache and MySQL should be green)
echo Step 2: Database should be set up (run setup-database.sql in phpMyAdmin)
echo Step 3: Starting frontend development server...
echo.
cd app
npm run dev
pause

