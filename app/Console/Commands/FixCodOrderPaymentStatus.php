<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class FixCodOrderPaymentStatus extends Command
{
    protected $signature = 'orders:fix-cod-payment-status';
    protected $description = 'Fix payment status for completed COD orders';

    public function handle()
    {
        $this->info('Đang kiểm tra và sửa trạng thái thanh toán cho đơn hàng COD đã hoàn thành...');

        // Tìm các đơn hàng COD đã hoàn thành nhưng chưa thanh toán
        $orders = Order::where('order_status_id', Order::STATUS_COMPLETED)
            ->where('payment_method', 'cod')
            ->where('payment_status_id', Order::PAYMENT_PENDING)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Không có đơn hàng nào cần sửa.');
            return;
        }

        $count = 0;
        foreach ($orders as $order) {
            $order->update(['payment_status_id' => Order::PAYMENT_PAID]);
            $count++;
            $this->line("Đã cập nhật đơn hàng #{$order->id}");
        }

        $this->info("Đã sửa thành công {$count} đơn hàng.");
    }
}