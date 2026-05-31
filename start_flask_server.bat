@echo off
REM Start Flask AI Prediction Server
REM This script automatically runs the Flask API on port 5000

echo.
echo ========================================
echo Flask AI Prediction Server Startup
echo ========================================
echo.

REM Change to the python directory
cd /d "%~dp0"

REM Run the Flask app
echo Starting Flask server on http://127.0.0.1:5000
echo Press CTRL+C to stop the server
echo.

"%~dp0venv\Scripts\python.exe" app.py

REM If Flask crashes or closes, show a message
if %errorlevel% neq 0 (
    echo.
    echo ERROR: Flask server failed to start
    echo Check that all dependencies are installed: pip install -r requirements.txt
    pause
)
