$ErrorActionPreference = 'Stop'
$env:PHP_CLI_SERVER_WORKERS = '1'
Set-Location -LiteralPath (Join-Path $PSScriptRoot 'backend')
Write-Host 'PAWADOPT mobile backend is starting on port 8000.' -ForegroundColor Green
Write-Host 'Connect your phone and this computer to the same Wi-Fi.'
Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway } | ForEach-Object {
    foreach ($ipAddress in $_.IPv4Address.IPAddress) { Write-Host ('Phone API address: http://' + $ipAddress + ':8000/api') }
}
Write-Host 'Keep this terminal open. Press Ctrl+C to stop.'
php artisan serve --host=0.0.0.0 --port=8000 --no-reload
