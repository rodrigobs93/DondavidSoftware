<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@minegocio.local'],
            [
                'name'     => 'Administrador',
                'email'    => 'admin@minegocio.local',
                'password' => Hash::make('Admin1234!'),
                'role'     => 'admin',
                'active'   => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'cajero@minegocio.local'],
            [
                'name'     => 'Cajero',
                'email'    => 'cajero@minegocio.local',
                'password' => Hash::make('Cajero2024!'),
                'role'     => 'cashier',
                'active'   => true,
            ]
        );
    }
}
