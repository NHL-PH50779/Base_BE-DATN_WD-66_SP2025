<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Voucher;
use Carbon\Carbon;

class VoucherSeeder extends Seeder
{
    public function run()
    {
        $vouchers = [
            [
                'code' => 'WELCOME2025',
                'name' => 'Chào mừng năm mới 2025',
                'description' => 'Giảm 100,000đ cho đơn hàng từ 500,000đ',
                'type' => 'fixed',
                'value' => 100000,
                'min_order_amount' => 500000,
                'max_discount_amount' => null,
                'quantity' => 100,
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(3),
                'is_active' => true
            ],
            [
                'code' => 'SAVE10',
                'name' => 'Giảm 10%',
                'description' => 'Giảm 10% tối đa 200,000đ cho đơn hàng từ 300,000đ',
                'type' => 'percent',
                'value' => 10,
                'min_order_amount' => 300000,
                'max_discount_amount' => 200000,
                'quantity' => 50,
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(2),
                'is_active' => true
            ],
            [
                'code' => 'STUDENT',
                'name' => 'Ưu đãi sinh viên',
                'description' => 'Giảm 50,000đ cho sinh viên',
                'type' => 'fixed',
                'value' => 50000,
                'min_order_amount' => 200000,
                'max_discount_amount' => null,
                'quantity' => 200,
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(6),
                'is_active' => true
            ],
            [
                'code' => 'FREESHIP',
                'name' => 'Miễn phí vận chuyển',
                'description' => 'Giảm 30,000đ phí vận chuyển',
                'type' => 'fixed',
                'value' => 30000,
                'min_order_amount' => 100000,
                'max_discount_amount' => null,
                'quantity' => 500,
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(1),
                'is_active' => true
            ],
            [
                'code' => 'VIP20',
                'name' => 'Khách hàng VIP',
                'description' => 'Giảm 20% tối đa 500,000đ cho khách VIP',
                'type' => 'percent',
                'value' => 20,
                'min_order_amount' => 1000000,
                'max_discount_amount' => 500000,
                'quantity' => 20,
                'used_count' => 0,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(12),
                'is_active' => true
            ]
        ];

        foreach ($vouchers as $voucher) {
            Voucher::create($voucher);
        }
    }
}