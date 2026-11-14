<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class RenderStaffSeeder extends Seeder
{
    public function run(): void
    {
        $this->createStaff('admin', 'PAWADOPT_ADMIN_EMAIL', 'PAWADOPT_ADMIN_PASSWORD', 'PAWADOPT Administrator');
        $this->createStaff('manager', 'PAWADOPT_MANAGER_EMAIL', 'PAWADOPT_MANAGER_PASSWORD', 'PAWADOPT Manager');
    }

    private function createStaff(string $role, string $emailKey, string $passwordKey, string $name): void
    {
        $email = env($emailKey);
        $password = env($passwordKey);

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || strlen($password) < 16) {
            throw new RuntimeException("Set a valid {$emailKey} and a generated {$passwordKey} in the Render environment.");
        }

        $user = User::firstOrCreate(
            ['email' => strtolower($email)],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        if ($user->role !== $role) {
            $user->forceFill(['role' => $role])->save();
        }
    }
}
