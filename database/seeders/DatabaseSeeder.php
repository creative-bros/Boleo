<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultAdminEmail = config('auth.default_admin.email');
        $defaultAdminPassword = config('auth.default_admin.password');

        if (! is_string($defaultAdminEmail) || $defaultAdminEmail === '' || ! is_string($defaultAdminPassword) || $defaultAdminPassword === '') {
            return;
        }

        User::query()->firstOrCreate([
            'email' => $defaultAdminEmail,
        ], [
            'name' => (string) config('auth.default_admin.name', 'Administrador Boleo'),
            'phone' => config('auth.default_admin.phone'),
            'role' => 'admin',
            'password' => Hash::make($defaultAdminPassword),
        ]);
    }
}
