<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Minimal seed for a clean / test database — ONLY the records the app needs to
 * boot and operate. Deliberately excludes ProductSeeder (sample catalog) and any
 * other demo data.
 *
 * Inserts:
 *   - Settings keys (SettingSeeder)
 *   - The required GENERIC customer (CustomerSeeder)
 *   - Admin + cashier logins (UserSeeder)
 *
 * Used by scripts/reset-db.ps1. The default DatabaseSeeder (with sample products)
 * remains the install-time seeder and is left untouched.
 */
class MinimalSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CustomerSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
