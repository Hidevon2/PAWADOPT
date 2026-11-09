param(
    [string]$ApiUrl = '',
    [string]$AndroidSdk = "$env:USERPROFILE/AppData/Local/Android/Sdk",
    [string]$JavaHome = 'C:/Program Files/Android/Android Studio/jbr',
    [switch]$UseGradleMirror
)

$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$frontendPath = Join-Path $projectRoot 'frontend'
$androidPath = Join-Path $frontendPath 'src-capacitor/android'

if (-not (Test-Path -LiteralPath (Join-Path $JavaHome 'bin/java.exe'))) { throw 'Pass -JavaHome with the location of a JDK compatible with Gradle 9.1 (Java 17-25).' }
if (-not (Test-Path -LiteralPath $AndroidSdk)) { throw 'Pass -AndroidSdk with your Android SDK folder.' }
if (-not (Test-Path -LiteralPath (Join-Path $androidPath 'gradlew.bat'))) { throw 'The Capacitor Android project is missing.' }

if (-not $ApiUrl) {
    $wifiAddress = Get-NetIPConfiguration | Where-Object { $_.IPv4DefaultGateway } | ForEach-Object { $_.IPv4Address.IPAddress } | Select-Object -First 1
    if ($wifiAddress) { $ApiUrl = 'http://' + $wifiAddress + ':8000/api' }
    else { $ApiUrl = 'http://10.0.2.2:8000/api' }
}
$serverAddress = [uri]$ApiUrl
if (-not $serverAddress.IsAbsoluteUri -or $serverAddress.Scheme -notin @('http', 'https') -or $serverAddress.UserInfo -or $serverAddress.Query -or $serverAddress.Fragment) { throw 'ApiUrl must be an HTTP or HTTPS server address without credentials, a query, or a fragment.' }
$ApiUrl = $ApiUrl.TrimEnd('/')
if (-not $ApiUrl.EndsWith('/api')) { $ApiUrl += '/api' }

$priorJava = $env:JAVA_HOME
$priorAndroid = $env:ANDROID_HOME
$priorGradle = $env:GRADLE_USER_HOME
$priorApi = $env:PAWADOPT_API_URL

try {
    $env:JAVA_HOME = $JavaHome
    $env:ANDROID_HOME = [System.IO.Path]::GetFullPath($AndroidSdk)
    $env:GRADLE_USER_HOME = Join-Path $projectRoot '.android-tools/gradle-home'
    $env:PAWADOPT_API_URL = $ApiUrl
    if ($UseGradleMirror) {
        $gradleArchivePath = Join-Path $projectRoot '.android-tools/gradle-9.1.0-bin.zip'
        $expectedHash = 'a17ddd85a26b6a7f5ddb71ff8b05fc5104c0202c6e64782429790c933686c806'
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $gradleArchivePath) | Out-Null
        $archiveIsComplete = (Test-Path -LiteralPath $gradleArchivePath) -and ((Get-FileHash -LiteralPath $gradleArchivePath -Algorithm SHA256).Hash.ToLowerInvariant() -eq $expectedHash)
        if (-not $archiveIsComplete) {
            Write-Host 'Resuming the Gradle archive from the mirror. This may take time on a slow connection.'
            curl.exe --location --fail --continue-at - --connect-timeout 20 --max-time 1800 --output $gradleArchivePath 'https://repo.huaweicloud.com/gradle/gradle-9.1.0-bin.zip'
            if ($LASTEXITCODE -ne 0) { throw 'Gradle download interrupted. Its partial archive is saved; rerun with -UseGradleMirror to resume.' }
        }
        if ((Get-FileHash -LiteralPath $gradleArchivePath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expectedHash) { throw 'The Gradle archive does not match the official SHA-256 and will not be used.' }
        $localGradlePath = Join-Path $projectRoot '.android-tools/gradle-9.1.0'
        if (-not (Test-Path -LiteralPath (Join-Path $localGradlePath 'bin/gradle.bat'))) {
            Expand-Archive -LiteralPath $gradleArchivePath -DestinationPath (Join-Path $projectRoot '.android-tools') -Force
        }
        Write-Host 'Verified Gradle 9.1.0 is ready.' -ForegroundColor Green
    }
    $sdkProperties = 'sdk.dir=' + $env:ANDROID_HOME.Replace('\', '/')
    [System.IO.File]::WriteAllText((Join-Path $androidPath 'local.properties'), $sdkProperties, [System.Text.UTF8Encoding]::new($false))
    Write-Host ('Building PAWADOPT for API server: ' + $ApiUrl) -ForegroundColor Green
    Push-Location -LiteralPath $frontendPath
    try {
        npm.cmd exec -- quasar build -m capacitor -T android --skip-pkg
        if ($LASTEXITCODE -ne 0) { throw 'Capacitor frontend build failed.' }
    } finally { Pop-Location }

    Push-Location -LiteralPath $androidPath
    try {
        if ($UseGradleMirror) {
            & (Join-Path $projectRoot '.android-tools/gradle-9.1.0/bin/gradle.bat') assembleDebug --no-daemon --console=plain --max-workers=2
        } else {
            ./gradlew.bat assembleDebug --no-daemon --console=plain --max-workers=2
        }
        if ($LASTEXITCODE -ne 0) { throw 'Gradle APK build failed. Check the error above.' }
    } finally { Pop-Location }

    $compiledApk = Join-Path $androidPath 'app/build/outputs/apk/debug/app-debug.apk'
    if (-not (Test-Path -LiteralPath $compiledApk)) { throw 'Gradle did not produce the expected APK.' }
    $outputPath = Join-Path $projectRoot 'output/android'
    New-Item -ItemType Directory -Force -Path $outputPath | Out-Null
    $appVersion = (Get-Content -LiteralPath (Join-Path $frontendPath 'package.json') -Raw | ConvertFrom-Json).version
    $apkPath = Join-Path $outputPath ('PAWADOPT-v' + $appVersion + '.apk')
    Copy-Item -LiteralPath $compiledApk -Destination $apkPath -Force
    Copy-Item -LiteralPath $apkPath -Destination (Join-Path $outputPath 'PAWADOPT-debug.apk') -Force
    Write-Host ('APK ready: ' + $apkPath) -ForegroundColor Green
    Write-Host ('SHA-256: ' + (Get-FileHash -LiteralPath $apkPath -Algorithm SHA256).Hash)
    Write-Host 'Run ./start-mobile-backend.ps1 before using API features on your phone.'
} finally {
    $env:JAVA_HOME = $priorJava
    $env:ANDROID_HOME = $priorAndroid
    $env:GRADLE_USER_HOME = $priorGradle
    $env:PAWADOPT_API_URL = $priorApi
}
