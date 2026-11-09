# PAWADOPT v3

A pet adoption classroom project recreated from the original PawHome screenshot. Built with **Vue 3, Quasar CLI, TypeScript, Pinia, Axios, and Laravel 12**. The default database is SQLite, so XAMPP MySQL does not have to be running.

## Version 3.0.0

The updated APK is `output/android/PAWADOPT-v3.0.0.apk`. This release fixes the overlapping sign-in button and the matching icons on the contact, adoption, FAQ, and notification interfaces. Mobile forms have larger touch targets, readable inputs, and scrollable dialogs for small screens. Sign-in prevents repeat submission, locks mode changes while a request is running, and clears passwords when closed. The website footer and API health response identify this release as 3.0.0.

Validation: TypeScript, all 12 Laravel tests (64 assertions), all 4 browser tests, web production build, Capacitor sync, and Android Studio APK build passed. The APK signature and Android version code 3 were verified. Phone installation and native runtime testing are pending. See [ANDROID.md](ANDROID.md) for phone setup.

## Open the app

From this folder, run `./start.ps1` in PowerShell. Keep the terminal open and visit **http://127.0.0.1:9001**. Press Ctrl+C to stop the servers.

You can also run the two servers separately:

```powershell
# Terminal 1
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

```powershell
# Terminal 2
cd frontend
npm.cmd run dev
```

## Features

- Responsive home page, dark/light theme, keyboard navigation, and mobile menu.
- Eight fictional pet profiles with photos, care descriptions, traits, and illustrative fees.
- Species tabs, name/breed/location search, city and gender filters, and sorting.
- Guest favorites saved in the browser; account favorites saved in the database. Guest favorites transfer when signing in.
- Registration, sign in, expiring API tokens, and sign out. Passwords are hashed. No preconfigured account: create your own.
- Adoption applications with validated fields, duplicate prevention, confirmation reference, and account-specific history.
- About, expandable FAQs, contact form with database persistence, and a real Test Backend action calling `GET /api/hello`.

This is a **fictional demonstration**. It does not send email, collect payments, contact shelters, or review applications. There is no staff dashboard. Submitted applications remain Submitted. Do not enter sensitive personal information when demonstrating it.

## First-time setup on another computer

Requirements: Node 22+, npm, Composer 2, PHP 8.2+ with `pdo_sqlite`, `mbstring`, `fileinfo`, and `openssl`.

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate --seed
cd ../frontend
npm.cmd install
```

Run `migrate --seed` on the existing project to apply migrations and refresh sample pets. Avoid `migrate:fresh` on a database containing records you want to keep. Back up `backend/database/database.sqlite` to preserve your local accounts, favorites, and applications. Source files are in this project folder; dependency folders can be recreated using the lockfiles.

Run `./backup-source.ps1` from PowerShell 7 to create a dated source ZIP, including the photographs and screenshots. It excludes dependency folders, runtime files, local database records, and `.env` secrets. Keep a separate copy of your database if you want to preserve account data.

## Use MySQL instead (optional)

Create an empty database named `pawadopt` in your local MySQL installation. Set these in `backend/.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pawadopt
DB_USERNAME=root
DB_PASSWORD=
```

Then run `php artisan config:clear` and `php artisan migrate --seed`. This creates a new dataset in MySQL; it does not transfer existing SQLite records. Use credentials appropriate to your installation.

## Checks

```powershell
cd backend
php artisan test
cd ../frontend
npm.cmd run typecheck
npm.cmd run build
```

Feature tests use a separate, in-memory SQLite database. They verify authentication, token expiry and revocation, private favorites, application persistence and duplicate prevention, and contact validation.

Browser checks are available with `npm.cmd run test:ui` from `frontend` while both servers are running. They use installed Microsoft Edge in headless mode. Catalog, guest favorites, backend connectivity, and navigation use the real local API. Account, application, and contact screen tests use deterministic API responses; the Laravel tests separately verify their persistence and authorization. Screenshots are saved in `screenshots/`.

## Project map

```text
PAWADOPT/
  frontend/
    src/pages/PawAdopt.vue       Pages, forms, dialogs, and navigation
    src/components/             Pet cards and icon component
    src/stores/paw.ts           Pinia catalog, account, and favorites state
    src/lib/api.ts              Axios client and types
    src/css/app.scss            Responsive design and themes
    public/images/              Downloaded illustrative pet photos
    quasar.config.ts            Dev server and Laravel API proxy
  backend/
    routes/api.php              API routes
    app/Http/Controllers/       API validation and actions
    app/Http/Middleware/        Expiring bearer-token authentication
    database/migrations/        Database schema
    database/seeders/           Fictional sample pets
    tests/Feature/              API feature tests
  start.ps1                     Starts both local servers
```

## Understanding one feature: adoption applications

1. A visitor opens a pet profile and chooses **Apply to adopt**. Sign-in is required.
2. Vue validates the form, then Axios sends the application and bearer token to `POST /api/pets/{id}/applications` through Quasar’s `/api` proxy.
3. Laravel authenticates the token, validates all fields again, checks pet availability and duplicate applications, and stores a record linked to the user and pet.
4. The API returns the saved record and reference number. The interface displays the confirmation.
5. **My applications** requests `GET /api/applications`; the backend returns only records belonging to that account.

Client validation improves feedback, but server validation and authorization enforce the rules. To verify this feature, try missing fields, invalid phone numbers, submitting twice for the same pet, signing out, and checking from a different account. The feature test suite automates these checks. Use your own explanation and screenshots if this feature is selected for class.

## Android work

The v3 Android APK was built successfully in Android Studio on November 9, 2026 and saved as `output/android/PAWADOPT-v3.0.0.apk`. `PAWADOPT-debug.apk` also points to the current build. Its APK signature was verified; phone installation and Android runtime testing are pending. The Capacitor 8 Android project is in `frontend/src-capacitor/android`. Use `./start-mobile-backend.ps1` for the phone's API connection. See [ANDROID.md](ANDROID.md) for installation, verification, and rebuild steps. The mobile app has a Server connection setting, so changing Wi-Fi addresses does not require rebuilding.

## Sources

- [Quasar CLI documentation](https://quasar.dev/start/quasar-cli)
- [Laravel 12 documentation](https://laravel.com/docs/12.x)
- Illustrative pet photographs: [Unsplash](https://unsplash.com). Photo identifiers and source URLs are listed in `frontend/public/images/credits.json`.
- Interface fonts: DM Sans and Manrope from Google Fonts, bundled locally with system font fallbacks.
