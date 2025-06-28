<?php

namespace App\Observers;

use App\Models\Order;

class OrderObserver
{
    public function updating(Order $order)
    {
        // Kiểm tra nếu trạng thái đơn hàng thay đổi thành "Hoàn thành"
        if ($order->isDirty('order_status_id') && $order->order_status_id == Order::STATUS_COMPLETED) {
            // Nếu phương thức thanh toán là COD, tự động cập nhật trạng thái thanh toán
            if ($order->payment_method === 'cod' && $order->payment_status_id == Order::PAYMENT_PENDING) {
                $order->payment_status_id = Order::PAYMENT_PAID;
            }
        }
    }
}