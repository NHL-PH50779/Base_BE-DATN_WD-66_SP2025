<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Hash;

class DashboardSeeder extends Seeder
{
    public function run()
    {
        // 1. Tạo admin user
        $admin = User::firstOrCreate([
            'email' => 'admin@techstore.com'
        ], [
            'name' => 'Admin TechStore',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'status' => 'active'
        ]);

        // 2. Tạo khách hàng
        $customers = [];
        for ($i = 1; $i <= 10; $i++) {
            $customers[] = User::firstOrCreate([
                'email' => "customer{$i}@gmail.com"
            ], [
                'name' => "Khách hàng {$i}",
                'password' => Hash::make('123456'),
                'role' => 'client',
                'status' => 'active',
                'phone' => '098765432' . $i,
                'address' => "Địa chỉ khách hàng {$i}"
            ]);
        }

        // 3. Tạo thương hiệu
        $brands = [
            ['name' => 'Apple', 'description' => 'Thương hiệu Apple'],
            ['name' => 'Samsung', 'description' => 'Thương hiệu Samsung'],
            ['name' => 'Dell', 'description' => 'Thương hiệu Dell'],
            ['name' => 'HP', 'description' => 'Thương hiệu HP'],
            ['name' => 'Asus', 'description' => 'Thương hiệu Asus']
        ];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(['name' => $brand['name']], $brand);
        }

        // 4. Tạo danh mục
        $categories = [
            ['name' => 'Laptop Gaming', 'description' => 'Laptop chơi game'],
            ['name' => 'Laptop Văn Phòng', 'description' => 'Laptop văn phòng'],
            ['name' => 'Điện Thoại', 'description' => 'Điện thoại thông minh'],
            ['name' => 'Tablet', 'description' => 'Máy tính bảng'],
            ['name' => 'Phụ Kiện', 'description' => 'Phụ kiện công nghệ']
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }

        // 5. Tạo sản phẩm
        $brandIds = Brand::pluck('id')->toArray();
        $categoryIds = Category::pluck('id')->toArray();

        $products = [
            ['name' => 'MacBook Pro M3', 'price' => 45000000, 'stock' => 15],
            ['name' => 'Dell XPS 13', 'price' => 25000000, 'stock' => 8],
            ['name' => 'HP Pavilion Gaming', 'price' => 18000000, 'stock' => 3],
            ['name' => 'Asus ROG Strix', 'price' => 35000000, 'stock' => 12],
            ['name' => 'Samsung Galaxy S24', 'price' => 22000000, 'stock' => 5],
            ['name' => 'iPad Pro', 'price' => 28000000, 'stock' => 20],
            ['name' => 'Surface Laptop', 'price' => 32000000, 'stock' => 7],
            ['name' => 'ThinkPad X1', 'price' => 38000000, 'stock' => 9]
        ];

        foreach ($products as $productData) {
            $product = Product::firstOrCreate([
                'name' => $productData['name']
            ], [
                'name' => $productData['name'],
                'description' => 'Mô tả sản phẩm ' . $productData['name'],
                'price' => $productData['price'],
                'stock' => $productData['stock'],
                'brand_id' => $brandIds[array_rand($brandIds)],
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'thumbnail' => '/images/products/default.jpg',
                'is_active' => true
            ]);

            // Tạo variant cho sản phẩm
            ProductVariant::firstOrCreate([
                'product_id' => $product->id,
                'Name' => 'Cấu hình cơ bản'
            ], [
                'product_id' => $product->id,
                'Name' => 'Cấu hình cơ bản',
                'price' => $productData['price'],
                'stock' => $productData['stock'],
                'is_active' => true
            ]);
        }

        // 6. Tạo đơn hàng
        $productIds = Product::pluck('id')->toArray();
        $customerIds = collect($customers)->pluck('id')->toArray();

        for ($i = 1; $i <= 20; $i++) {
            $customerId = $customerIds[array_rand($customerIds)];
            $customer = User::find($customerId);
            
            $order = Order::create([
                'user_id' => $customerId,
                'name' => $customer->name,
                'phone' => $customer->phone ?? '0987654321',
                'address' => $customer->address ?? 'Địa chỉ mặc định',
                'total' => rand(500000, 5000000),
                'payment_method' => rand(0, 1) ? 'vnpay' : 'cod',
                'payment_status_id' => rand(1, 2),
                'order_status_id' => rand(1, 5),
                'created_at' => now()->subDays(rand(1, 30))
            ]);

            // Tạo order items
            $numItems = rand(1, 3);
            for ($j = 0; $j < $numItems; $j++) {
                $productId = $productIds[array_rand($productIds)];
                $product = Product::find($productId);
                $quantity = rand(1, 2);
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price' => $product->price
                ]);
            }
        }

        echo "Dashboard seeder completed successfully!\n";
    }
}