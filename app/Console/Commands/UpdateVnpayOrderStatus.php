<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class UpdateVnpayOrderStatus extends Command
{
    protected $signature = 'orders:update-vnpay-status';
    protected $description = 'Cập nhật trạng thái đơn hàng VNPay từ "Chờ xác nhận/Chưa thanh toán" thành "Đã xác nhận/Đã thanh toán"';

    public function handle()
    {
        $this->info('Bắt đầu cập nhật trạng thái đơn hàng VNPay...');

        // Tìm các đơn hàng VNPay đang ở trạng thái chờ xác nhận và chưa thanh toán
        $orders = Order::where('payment_method', 'vnpay')
            ->where(function($query) {
                $query->where('order_status_id', Order::STATUS_PENDING)
                      ->orWhere('status', 'pending');
            })
            ->where(function($query) {
                $query->where('payment_status_id', Order::PAYMENT_PENDING)
                      ->orWhere('payment_status', 'unpaid');
            })
            ->get();

        $updatedCount = 0;

        foreach ($orders as $order) {
            try {
                $order->update([
                    'order_status_id' => Order::STATUS_CONFIRMED,
                    'payment_status_id' => Order::PAYMENT_PAID,
                    'status' => 'confirmed',
                    'payment_status' => 'paid'
                ]);
                
                $updatedCount++;
                $this->info("Đã cập nhật đơn hàng #{$order->id}");
                
            } catch (\Exception $e) {
                $this->error("Lỗi khi cập nhật đơn hàng #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("Hoàn thành! Đã cập nhật {$updatedCount} đơn hàng VNPay.");
        
        return 0;
    }
}