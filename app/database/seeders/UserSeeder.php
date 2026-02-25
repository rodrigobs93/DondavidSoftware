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
            ['email' => 'admin@dondavid.co'],
            [
                'name'     => 'Administrador',
                'email'    => 'admin@dondavid.co',
                'password' => Hash::make('DonDavid2024!'),
                'role'     => 'admin',
                'active'   => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'cajero@dondavid.co'],
            [
                'name'     => 'Cajero',
                'email'    => 'cajero@dondavid.co',
                'password' => Hash::make('Cajero2024!'),
                'role'     => 'cashier',
                'active'   => true,
            ]
        );
    }
}
