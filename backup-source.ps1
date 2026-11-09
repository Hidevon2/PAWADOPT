$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath($PSScriptRoot)
$appVersion = (Get-Content -LiteralPath (Join-Path $projectRoot 'frontend/package.json') -Raw | ConvertFrom-Json).version
$archiveName = 'PAWADOPT-v' + $appVersion + '-source-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip'
$archivePath = Join-Path $projectRoot $archiveName
$excludedDirectories = @('node_modules', 'vendor', '.quasar', 'dist', '.git', 'test-results', 'playwright-report', '.android-tools', '.gradle', 'build', '.idea')
$pendingDirectories = [System.Collections.Generic.Stack[string]]::new()
$pendingDirectories.Push($projectRoot)
$archive = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    while ($pendingDirectories.Count -gt 0) {
        $currentDirectory = $pendingDirectories.Pop()
        foreach ($item in Get-ChildItem -LiteralPath $currentDirectory -Force) {
            if ($item.PSIsContainer) {
                if ($item.Name -notin $excludedDirectories) { $pendingDirectories.Push($item.FullName) }
                continue
            }
            $relativePath = [System.IO.Path]::GetRelativePath($projectRoot, $item.FullName).Replace('\', '/')
            if ($item.Name -like '.env*' -and $item.Name -ne '.env.example') { continue }
            if ($item.Name -match '\.(zip|log|sqlite(?:-.*)?|cache|apk|aab|keystore|jks)$' -or $item.Name -eq 'local.properties') { continue }
            if ($relativePath.StartsWith('frontend/src-capacitor/www/') -or $relativePath.StartsWith('frontend/src-capacitor/android/app/src/main/assets/')) { continue }
            if ($relativePath.StartsWith('backend/bootstrap/cache/') -and $item.Extension -eq '.php') { continue }
            if ($relativePath.StartsWith('backend/storage/') -and $item.Name -ne '.gitignore') { continue }
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $item.FullName, 'PAWADOPT/' + $relativePath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    }
} finally {
    $archive.Dispose()
}
Write-Host ('Source backup saved: ' + $archivePath) -ForegroundColor Green
Write-Host 'Dependencies, local database records, and .env secrets are excluded. See README.md for setup.'
