<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::truncate();

        Product::create(['name' => 'Widget A', 'price_cents' => 1999, 'stock' => 10]);
        Product::create(['name' => 'Widget B', 'price_cents' => 2999, 'stock' => 5]);
        Product::create(['name' => 'Widget C', 'price_cents' => 999,  'stock' => 100]);
    }
}
