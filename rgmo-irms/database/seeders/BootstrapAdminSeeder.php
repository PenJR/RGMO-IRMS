<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::exists()) {
            return;
        }

        $credentials = config('bootstrap_admin');

        Validator::make($credentials, [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(10)->letters()->mixedCase()->numbers()->symbols()],
        ])->validate();

        User::forceCreate([
            'name' => $credentials['name'],
            'email' => $credentials['email'],
            'password' => Hash::make($credentials['password']),
            'role' => User::ROLE_ADMIN,
            'department' => 'RGMO',
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
