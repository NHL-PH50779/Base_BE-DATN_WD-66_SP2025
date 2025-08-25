<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run()
    {
        DB::table('products')->insert([
            [
                'name' => 'MacBook Pro 14"',
                'description' => 'Laptop Apple mạnh mẽ cho lập trình viên',
                'brand_id' => 1,       // Apple
                'category_id' => 1,    // Laptop
                'thumbnail' => 'macbook14.jpg',
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => 'Điện thoại cao cấp từ Samsung',
                'brand_id' => 2,       // Samsung
                'category_id' => 2,    // Smartphone
                'thumbnail' => 'galaxy-s24.jpg',
            ],
            [
                'name' => 'Dell XPS 13',
                'description' => 'Laptop siêu nhẹ và mạnh mẽ từ Dell',
                'brand_id' => 3,       // Dell
                'category_id' => 1,    // Laptop
                'thumbnail' => 'xps13.jpg',
            ],
        ]);
    }
}
