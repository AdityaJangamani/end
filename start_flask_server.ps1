$ScriptPath = Split-Path -Parent -Path $MyInvocation.MyCommand.Definition

Write-Host ""
Write-Host "========================================"
Write-Host "Flask AI Prediction Server Startup"
Write-Host "========================================"
Write-Host ""

Set-Location $ScriptPath

Write-Host "Starting Flask server on http://127.0.0.1:5000"
Write-Host "Press CTRL+C to stop the server"
Write-Host ""

& "$ScriptPath\venv\Scripts\python.exe" app.py

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "ERROR: Flask server failed to start"
    Write-Host "Check that all dependencies are installed: pip install -r requirements.txt"
    Read-Host "Press Enter to exit"
}
