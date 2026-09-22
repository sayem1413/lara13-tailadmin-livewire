<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default super-admin user so the app is usable right after
     * `migrate --seed`. Override the credentials per environment via
     * ADMIN_EMAIL / ADMIN_PASSWORD before seeding production data.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => config('app.admin.email')],
            [
                'name' => 'Super Admin',
                'password' => config('app.admin.password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $user->syncRoles(['Super Admin']);
    }
}
