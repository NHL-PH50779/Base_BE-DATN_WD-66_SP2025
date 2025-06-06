<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Brand;




class BrandSeeder extends Seeder
{
    public function run(): void
    {
        Brand::create(['name' => 'Apple']);
        Brand::create(['name' => 'Samsung']);
        Brand::create(['name' => 'Dell']);
    }
}


