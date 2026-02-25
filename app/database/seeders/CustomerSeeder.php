<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // The one required GENERIC customer — must always exist, cannot be deleted
        Customer::firstOrCreate(
            ['is_generic' => true],
            [
                'name'        => 'CLIENTE GENÉRICO',
                'is_generic'  => true,
                'requires_fe' => false,
                'active'      => true,
            ]
        );
    }
}
