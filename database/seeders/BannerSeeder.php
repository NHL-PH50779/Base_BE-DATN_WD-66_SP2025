<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Banner;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::create([
            'image' => 'https://example.com/banner1.jpg',
            'link' => '/sale',
            'position' => 1,
            'status' => true,
        ]);

        Banner::create([
            'image' => 'https://example.com/banner2.jpg',
            'link' => '/new-products',
            'position' => 2,
            'status' => true,
        ]);
    }
}
