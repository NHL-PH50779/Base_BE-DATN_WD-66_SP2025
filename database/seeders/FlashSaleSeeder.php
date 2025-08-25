<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Carbon\Carbon;

class FlashSaleSeeder extends Seeder
{
    public function run()
    {
        // Tạo Flash Sale đang diễn ra
        $currentFlashSale = FlashSale::create([
            'name' => 'Flash Sale Cuối Tuần',
            'description' => 'Giảm giá sốc các sản phẩm laptop hot nhất',
            'start_time' => Carbon::now()->subHours(1),
            'end_time' => Carbon::now()->addHours(23),
            'is_active' => true
        ]);

        // Lấy 6 sản phẩm đầu tiên để thêm vào flash sale
        $products = Product::take(6)->get();
        
        foreach ($products as $index => $product) {
            $originalPrice = $product->price ?? ($product->variants->first()->price ?? 1000000);
            $discountPercentage = [20, 30, 25, 35, 40, 15][$index % 6];
            $salePrice = $originalPrice * (100 - $discountPercentage) / 100;
            
            FlashSaleItem::create([
                'flash_sale_id' => $currentFlashSale->id,
                'product_id' => $product->id,
                'original_price' => $originalPrice,
                'sale_price' => $salePrice,
                'discount_percentage' => $discountPercentage,
                'quantity_limit' => rand(50, 200),
                'sold_quantity' => rand(10, 30),
                'is_active' => true
            ]);
        }

        // Tạo Flash Sale sắp tới
        $upcomingFlashSale = FlashSale::create([
            'name' => 'Flash Sale Thứ 2 Đen Tối',
            'description' => 'Siêu sale thứ 2 với giá không thể tin được',
            'start_time' => Carbon::now()->addDays(2),
            'end_time' => Carbon::now()->addDays(2)->addHours(12),
            'is_active' => true
        ]);

        // Thêm sản phẩm cho flash sale sắp tới
        $moreProducts = Product::skip(6)->take(4)->get();
        foreach ($moreProducts as $index => $product) {
            $originalPrice = $product->price ?? ($product->variants->first()->price ?? 1500000);
            $discountPercentage = [45, 50, 35, 40][$index % 4];
            $salePrice = $originalPrice * (100 - $discountPercentage) / 100;
            
            FlashSaleItem::create([
                'flash_sale_id' => $upcomingFlashSale->id,
                'product_id' => $product->id,
                'original_price' => $originalPrice,
                'sale_price' => $salePrice,
                'discount_percentage' => $discountPercentage,
                'quantity_limit' => rand(30, 100),
                'sold_quantity' => 0,
                'is_active' => true
            ]);
        }
    }
}