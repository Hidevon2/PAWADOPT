<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateStaff extends Command
{
    protected $signature = 'pawadopt:staff {role : admin or manager} {email} {--name=}';
    protected $description = 'Create a staff account with a generated password, saved only in local private storage';

    public function handle(): int
    {
        $role = $this->argument('role');
        $email = Str::lower(trim($this->argument('email')));
        if (!in_array($role, ['admin', 'manager'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Use admin or manager and a valid email address.');
            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('That account already exists. No credentials or roles were changed.');
            return self::FAILURE;
        }
        $password = Str::random(20);
        $user = new User(['name' => $this->option('name') ?: ucfirst($role), 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->save();
        $directory = storage_path('app/private');
        if (!is_dir($directory)) { mkdir($directory, 0700, true); }
        file_put_contents($directory.'/staff-logins.txt', ucfirst($role)."\nEmail: $email\nInitial password: $password\nChange it from the staff dashboard after signing in.\n\n", FILE_APPEND | LOCK_EX);
        $this->info("Created $role: $email. Initial login saved in backend/storage/app/private/staff-logins.txt.");
        return self::SUCCESS;
    }
}
