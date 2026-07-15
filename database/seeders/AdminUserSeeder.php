<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@tcsuper.local')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'change-me')),
                'email_verified_at' => now(),
            ]
        );
    }
}
