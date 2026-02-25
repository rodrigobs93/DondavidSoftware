<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Costilla de res',       'sale_unit' => 'KG',   'base_price' => 32000],
            ['name' => 'Lomo de res',            'sale_unit' => 'KG',   'base_price' => 38000],
            ['name' => 'Pecho de res',           'sale_unit' => 'KG',   'base_price' => 18000],
            ['name' => 'Molida de res',          'sale_unit' => 'KG',   'base_price' => 22000],
            ['name' => 'Filete de res',          'sale_unit' => 'KG',   'base_price' => 45000],
            ['name' => 'Costilla de cerdo',      'sale_unit' => 'KG',   'base_price' => 16000],
            ['name' => 'Lomo de cerdo',          'sale_unit' => 'KG',   'base_price' => 20000],
            ['name' => 'Tocineta',               'sale_unit' => 'KG',   'base_price' => 24000],
            ['name' => 'Chorizo',                'sale_unit' => 'UNIT', 'base_price' => 4500],
            ['name' => 'Morcilla',               'sale_unit' => 'UNIT', 'base_price' => 3500],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(['name' => $product['name']], $product);
        }
    }
}
