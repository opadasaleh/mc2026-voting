<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the first admin if it does not exist yet. The password comes from
     * SEED_ADMIN_PASSWORD, or is generated and printed once.
     */
    public function run(): void
    {
        if (User::where('username', 'admin')->exists()) {
            return;
        }

        $password = env('SEED_ADMIN_PASSWORD') ?: Str::password(20, symbols: false);

        User::create(['username' => 'admin', 'password' => $password]);

        if (! env('SEED_ADMIN_PASSWORD')) {
            $this->command?->warn("Admin created: username [admin], password [{$password}]. Store it now; it is not shown again.");
        }
    }
}
