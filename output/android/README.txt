PAWADOPT v3.1.0

Install PAWADOPT-v3.1.0.apk on your Android phone (Android 7.0 or later).
PAWADOPT-debug.apk is the same current build under the older filename.
Both are debug-signed classroom builds.

For sign-in and pet data:
1. Keep your computer and phone on the same Wi-Fi network.
2. Run ./start-mobile-backend.ps1 from the PAWADOPT project folder.
3. In the app's menu, open Server connection.
4. Enter the API address printed by the backend script, test, and save.
5. Create an account if you do not have one, then sign in.

The APK includes the frontend. Laravel and the database stay on the computer.
Phone installation and native runtime testing are pending.
Full setup and verification instructions are in ANDROID.md.

Admin and manager dashboards are included. Staff sign in through the normal form.
Staff credentials belong to the configured Laravel server, not the APK.
See STAFF.md for setup. Private initial passwords are not included in this release.
