<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;

class AutoCompleteOrders extends Command
{
    protected $signature = 'orders:auto-complete';
    protected $description = 'Tự động hoàn thành đơn hàng sau 3 ngày giao hàng';

    public function handle()
    {
        $threeDaysAgo = Carbon::now()->subDays(3);
        
        $orders = Order::where('order_status_id', Order::STATUS_DELIVERED)
            ->where('updated_at', '<=', $threeDaysAgo)
            ->get();

        $completedCount = 0;
        
        foreach($orders as $order) {
            $updateData = ['order_status_id' => Order::STATUS_COMPLETED];
            
            // Tự động cập nhật trạng thái thanh toán cho COD
            if($order->payment_method === 'cod' && $order->payment_status_id == Order::PAYMENT_PENDING) {
                $updateData['payment_status_id'] = Order::PAYMENT_PAID;
            }
            
            $order->update($updateData);
            $completedCount++;
            
            $this->info("Đã hoàn thành đơn hàng #{$order->id}");
        }

        $this->info("Tổng cộng đã hoàn thành {$completedCount} đơn hàng");
        
        return 0;
    }
}