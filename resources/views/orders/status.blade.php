<!DOCTYPE html>
<html>
<head>
    <title>Trạng thái đơn hàng #{{ $order->id }}</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .order-header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .status-badge { padding: 5px 15px; border-radius: 20px; color: white; font-weight: bold; }
        .status-pending { background: #ffc107; }
        .status-paid { background: #28a745; }
        .status-failed { background: #dc3545; }
        .status-processing { background: #17a2b8; }
        .status-shipped { background: #6f42c1; }
        .status-delivered { background: #28a745; }
        .order-items { background: white; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px 0; }
        .item { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .item:last-child { border-bottom: none; }
        .total { background: #e9ecef; padding: 15px; font-weight: bold; text-align: right; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 5px; }
    </style>
</head>
<body>
    <div class="order-header">
        <h1>Đơn hàng #{{ $order->id }}</h1>
        <p><strong>Ngày đặt:</strong> {{ $order->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>Khách hàng:</strong> {{ $order->user->name ?? 'Khách vãng lai' }}</p>
        <p><strong>Trạng thái thanh toán:</strong> 
            <span class="status-badge status-{{ $order->payment_status }}">
                @switch($order->payment_status)
                    @case('pending') Chờ thanh toán @break
                    @case('paid') Đã thanh toán @break
                    @case('failed') Thanh toán thất bại @break
                    @default {{ $order->payment_status }}
                @endswitch
            </span>
        </p>
        <p><strong>Trạng thái đơn hàng:</strong> 
            <span class="status-badge status-{{ $order->status }}">
                @switch($order->status)
                    @case('pending') Chờ xử lý @break
                    @case('processing') Đang xử lý @break
                    @case('shipped') Đã gửi hàng @break
                    @case('delivered') Đã giao hàng @break
                    @case('cancelled') Đã hủy @break
                    @default {{ $order->status }}
                @endswitch
            </span>
        </p>
        @if($order->transaction_id)
            <p><strong>Mã giao dịch:</strong> {{ $order->transaction_id }}</p>
        @endif
    </div>

    <div class="order-items">
        <h3 style="padding: 15px; margin: 0; background: #f8f9fa;">Chi tiết đơn hàng</h3>
        @foreach($order->items as $item)
            <div class="item">
                <div>
                    <strong>{{ $item->product->name ?? 'Sản phẩm đã xóa' }}</strong><br>
                    <small>Số lượng: {{ $item->quantity }}</small>
                </div>
                <div>{{ number_format($item->price * $item->quantity) }} VND</div>
            </div>
        @endforeach
        <div class="total">
            Tổng cộng: {{ number_format($order->total_amount) }} VND
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        @if($order->payment_status === 'pending')
            <a href="/api/payment/vnpay" class="btn" onclick="retryPayment({{ $order->id }})">Thanh toán lại</a>
        @endif
        <a href="/" class="btn">Tiếp tục mua sắm</a>
    </div>

    <script>
        function retryPayment(orderId) {
            event.preventDefault();
            fetch('/api/payment/vnpay', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    amount: {{ $order->total_amount }}
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.payment_url;
                }
            });
        }
    </script>
</body>
</html>