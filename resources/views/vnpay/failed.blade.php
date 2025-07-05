<!DOCTYPE html>
<html>
<head>
    <title>Thanh toán thất bại</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .failed-card { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 30px; text-align: center; }
        .failed-icon { font-size: 48px; color: #dc3545; margin-bottom: 20px; }
        .order-info { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; margin: 10px; }
        .btn-retry { background: #28a745; }
        .btn:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="failed-card">
        <div class="failed-icon">❌</div>
        <h2>Thanh toán thất bại!</h2>
        <p>Rất tiếc, giao dịch của bạn không thành công.</p>
        
        <div class="order-info">
            <p><strong>Mã đơn hàng:</strong> #{{ $order_id }}</p>
            <p><strong>Lý do:</strong> {{ $message }}</p>
            <p><strong>Thời gian:</strong> {{ date('d/m/Y H:i:s') }}</p>
        </div>
        
        <div>
            <button onclick="window.parent.postMessage({type: 'payment_retry', order_id: '{{ $order_id }}'}, '*')" class="btn btn-retry">Thử lại thanh toán</button>
            <button onclick="window.parent.postMessage({type: 'go_home'}, '*')" class="btn">Trở về trang chủ</button>
        </div>
    </div>
    
    <script>
        // Tự động thông báo cho parent window
        setTimeout(() => {
            window.parent.postMessage({
                type: 'payment_failed', 
                order_id: '{{ $order_id }}',
                message: '{{ $message }}'
            }, '*');
        }, 2000);
    </script>
</body>
</html>