<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\News;

class NewsSeeder extends Seeder
{
    public function run()
    {
        // Tạo 20 bản tin tức máy tính fake
        News::factory()->count(20)->create();
    }
}
