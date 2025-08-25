<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cập nhật đơn hàng</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .status { background: #4CAF50; color: white; padding: 10px; border-radius: 5px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>TechStore</h1>
        </div>
        <div class="content">
            <p>Xin chào <strong>{{ $order->name ?? $order->user->name ?? 'Khách hàng' }}</strong>,</p>
            
            <p>Đơn hàng <strong>#{{ $order->id }}</strong> của bạn đã được cập nhật:</p>
            
            <div class="status">
                {{ $order->status_text ?? 'Đã cập nhật' }}
            </div>
            
            <p><strong>Thông tin đơn hàng:</strong></p>
            <ul>
                <li>Mã đơn hàng: #{{ $order->id }}</li>
                <li>Tổng tiền: {{ number_format($order->total) }} VND</li>
                <li>Ngày đặt: {{ $order->created_at->format('d/m/Y H:i') }}</li>
            </ul>
            
            <p>Cảm ơn bạn đã mua sắm tại <strong>TechStore</strong>!</p>
            
            <p>Trân trọng,<br>Đội ngũ TechStore</p>
        </div>
    </div>
</body>
</html>