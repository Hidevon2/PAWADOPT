# Admin and manager accounts

PAWADOPT v3.1.0 adds a protected staff workspace to both the website and Android app. Sign in through the normal sign-in form; staff accounts open their dashboard automatically. The navigation also shows **Admin dashboard** or **Manager dashboard**.

| Feature | Adopter | Manager | Admin |
| --- | --- | --- | --- |
| Browse, favorites, own adoption applications | Yes | Yes | Yes |
| View and review all applications | No | Yes | Yes |
| Add/edit pet profiles and availability | No | Yes | Yes |
| Read contact messages and mark resolved | No | Yes | Yes |
| View account list and change other roles | No | No | Yes |

Permissions are enforced by Laravel, including when API requests are sent directly. Public registration always creates an adopter. Passwords are hashed; passwords and token hashes are never included in account responses.

## Set up staff accounts on your computer

After the normal README setup, run from `backend`:

```powershell
php artisan migrate
php artisan pawadopt:staff admin admin@pawadopt.test --name="PAWADOPT Admin"
php artisan pawadopt:staff manager manager@pawadopt.test --name="PAWADOPT Manager"
```

Each command generates a different 20-character initial password and saves it only in `backend/storage/app/private/staff-logins.txt`. This private file is excluded from GitHub, source ZIPs, and the APK. There are no shared default staff passwords. The command refuses to overwrite an existing account or its role.

The requested admin and manager accounts have already been created in the original local SQLite database. Other computers must create their own accounts with these commands. The website and APK use whichever Laravel server is configured; the APK does not contain its own users or database.

Sign in, then use **Change password** in the staff workspace to replace the initial password. It requires the current password and a matching new password of at least 12 characters. The initial-login file is not updated after a password change.

## Review behavior

- Applications move from Submitted to Under review, Approved, or Not selected.
- Approving marks the pet adopted and marks competing pending applications Not selected. It cannot approve a second match for the same pet.
- Adopters see the decision and any review note under My applications.
- Decided applications cannot be reopened. Pets with an approved adoption cannot be made available again through the profile editor.
- Admins cannot change their own role. Changing another account's role revokes that account's existing sessions; it must sign in again.
- Pet seeding adds missing sample profiles without overwriting staff edits or adoption decisions.

This is a fictional classroom workflow. No email is delivered and no real shelter, adopter, or animal is contacted.
