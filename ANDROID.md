# PAWADOPT v3 for Android

The existing Vue 3 + Quasar application now has a Capacitor 8 Android project in `frontend/src-capacitor/android`. Its app ID is `com.hidevon2.pawadopt`, and its display name is **PAWADOPT**.

Public download: [PAWADOPT v3.1.0 APK](https://github.com/Hidevon2/PAWADOPT/releases/download/v3.1.0/PAWADOPT-v3.1.0.apk). The [release page](https://github.com/Hidevon2/PAWADOPT/releases/tag/v3.1.0) also includes the checksum and source backup.

**Current status (November 9, 2026): v3.1.0 APK built successfully in Android Studio.** File: `output/android/PAWADOPT-v3.1.0.apk` (12,857,578 bytes); `PAWADOPT-debug.apk` is the identical latest-build copy. Package: `com.hidevon2.pawadopt`, version 3.1.0, code 4, minimum API 24, target API 36. APK v2 signature verified. SHA-256: `32DA2C4D48D845B41710B4168EAD4E96AF873D0EACBCE3D25641E8B677C5BB41`. All 17 API tests and 7 browser tests pass. Phone installation and Android runtime testing remain pending.

## Build the APK

Open PowerShell in the PAWADOPT folder and run:

```powershell
./build-apk.ps1
```

To use the already downloaded and verified Gradle archive:

```powershell
./build-apk.ps1 -UseGradleMirror
```

This option uses the complete local archive without downloading it again. If you use the script on a computer with an incomplete archive, it can resume the saved bytes instead of starting over, with up to 30 minutes for the transfer. It checks SHA-256 against Gradle's [official checksum reference](https://gradle.org/release-checksums/) before extracting or running the distribution. The first successful native build still needs Maven dependencies and any missing SDK components, so a stable internet connection is necessary.

The command runner encountered a Java NIO loopback error matching a [reported Windows command-runner issue](https://github.com/openai/codex/issues/40902). Building through Android Studio succeeded. For later builds, open `frontend/src-capacitor/android` in Android Studio and choose **Build > Generate App Bundles or APKs > Generate APKs**, or run the saved script in a normal Windows PowerShell terminal. No GitHub authentication is involved.

The first build downloads Gradle, Maven dependencies, and any missing Android SDK components. Existing Android SDK licenses must cover the required components. If the build reports an unaccepted license or missing platform, use Android Studio's SDK Manager to install **Android SDK Platform 36** and **Build-Tools 36.0.0**, then accept the displayed licenses. The required Android command-line tools are not currently installed on this computer.

The script uses the installed Android Studio Java 25 runtime and a project-local Gradle 9.1 wrapper, which supports Java 25. It does not change your system Java configuration. Java sources still use Capacitor's generated compilation settings. The SDK and Java paths can be overridden:

```powershell
./build-apk.ps1 -JavaHome 'C:/path/to/jdk' -AndroidSdk 'C:/path/to/Android/Sdk'
```

Successful output:

```text
output/android/PAWADOPT-v3.1.0.apk
```

The original Gradle output is `frontend/src-capacitor/android/app/build/outputs/apk/debug/app-debug.apk`. This is a debug-signed classroom APK for direct installation, not a Play Store release.

## Staff logins

Admin and manager dashboards are included in this APK. Staff accounts live in Laravel, and the initial passwords are saved only on the computer that creates them. Use the normal sign-in form and follow [STAFF.md](STAFF.md). Update the backend and run `php artisan migrate` before using this APK with an older server.

## Connect your phone to Laravel

The APK packages the frontend and illustrative pet photos. Laravel and SQLite stay on your computer; they are not embedded in the APK.

1. Connect your computer and phone to the same Wi-Fi network.
2. Run `./start-mobile-backend.ps1` on the computer. Keep that terminal open.
3. The script prints the computer's API address. The address detected while setting up this project was `http://192.168.254.156:8000/api`; it can change when you reconnect to Wi-Fi.
4. On the phone, open that address with `/hello` appended in its browser. A JSON connection message confirms the phone can reach Laravel.
5. Open PAWADOPT, open its menu, and select **Server connection**. Enter the API address, choose **Test connection**, then **Save & reconnect**.

The APK build script detects the computer's current network address. To override it explicitly:

```powershell
./build-apk.ps1 -ApiUrl 'http://192.168.254.156:8000/api'
```

For an Android emulator on this computer, select **Use Android emulator address** in the app, or build with:

```powershell
./build-apk.ps1 -ApiUrl 'http://10.0.2.2:8000/api'
```

If another Laravel server is already running on port 8000, stop that server before starting `start-mobile-backend.ps1`. A server bound only to `127.0.0.1` cannot be reached from a physical phone.

If Windows asks about firewall access, only allow the local network you intend to use. This setup does not change firewall settings automatically. Some Wi-Fi networks prevent devices from communicating with each other; try a network or hotspot that allows local connections.

Local HTTP is enabled only in the Android **debug** build to support the classroom LAN demo. Native release builds disallow cleartext traffic; use a hosted HTTPS API for an app distributed beyond that demo. Laravel CORS allows the Capacitor localhost origins. If you set `CORS_ALLOWED_ORIGINS` yourself, include `http://localhost` and `https://localhost`.

## Install and verify

Copy `output/android/PAWADOPT-v3.1.0.apk` to your phone, open it, and approve Android's installation prompt yourself. Alternatively, after you enable USB debugging and authorize the computer:

```powershell
& "$env:LOCALAPPDATA/Android/Sdk/platform-tools/adb.exe" devices
& "$env:LOCALAPPDATA/Android/Sdk/platform-tools/adb.exe" install -r ./output/android/PAWADOPT-debug.apk
```

Check the app opens, photos appear, navigation and back navigation work, the server test succeeds, sign-in works, a favorite survives a reload, and an adoption application is saved in My applications. The website and APK share the same API/database, so an account created on the website should work in the APK when connected to the same server.

No real-device installation or Android runtime testing has been performed. The command runner could not start the ADB server, so device availability could not be checked. Take your class screenshots from an actual running installation after completing these checks.

## References

- [Quasar Capacitor preparation](https://quasar.dev/quasar-cli-vite/developing-capacitor-apps/preparation/)
- [Capacitor Android](https://capacitorjs.com/docs/android)
- [Gradle Java compatibility](https://docs.gradle.org/9.1.0/userguide/compatibility.html)
