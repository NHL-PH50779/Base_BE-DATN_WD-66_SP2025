<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\User;

class TestOrderSeeder extends Seeder
{
    public function run()
    {
        // Tạo user test nếu chưa có
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'phone' => '0123456789'
            ]
        );

        // Tạo đơn hàng test
        Order::create([
            'user_id' => $user->id,
            'order_status_id' => 1, // Chờ xác nhận
            'payment_status_id' => 1, // Chưa thanh toán
            'total' => 50000,
            'name' => 'Test User',
            'phone' => '0123456789',
            'email' => 'test@example.com',
            'address' => '123 Test Street, Test City',
            'payment_method' => 'vnpay',
            'note' => 'Test order for VNPay'
        ]);

        echo "Test order created successfully!\n";
    }
}