$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$frontendPath = Join-Path $projectRoot 'frontend'
$backendPath = Join-Path $projectRoot 'backend'

if (-not (Test-Path -LiteralPath (Join-Path $frontendPath 'node_modules'))) {
    throw 'Frontend dependencies are missing. Run npm.cmd install in frontend first.'
}
if (-not (Test-Path -LiteralPath (Join-Path $backendPath 'vendor/autoload.php'))) {
    throw 'Backend dependencies are missing. Run composer install in backend first.'
}

$phpExecutable = (Get-Command php -ErrorAction Stop).Source
$npmExecutable = (Get-Command npm.cmd -ErrorAction Stop).Source
$backendProcess = $null
$frontendProcess = $null
try {
    $backendProcess = Start-Process -FilePath $phpExecutable -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') -WorkingDirectory $backendPath -WindowStyle Hidden -PassThru
    $frontendProcess = Start-Process -FilePath $npmExecutable -ArgumentList @('run', 'dev') -WorkingDirectory $frontendPath -WindowStyle Hidden -PassThru
    Write-Host 'PAWADOPT is starting at http://127.0.0.1:9001' -ForegroundColor Green
    Write-Host 'Keep this terminal open. Press Ctrl+C to stop both servers.'
    while (-not $backendProcess.HasExited -and -not $frontendProcess.HasExited) { Start-Sleep -Seconds 2 }
} finally {
    foreach ($serverProcess in @($backendProcess, $frontendProcess)) {
        if ($null -ne $serverProcess -and -not $serverProcess.HasExited) {
            & taskkill.exe /PID $serverProcess.Id /T /F 2>$null | Out-Null
        }
    }
}
