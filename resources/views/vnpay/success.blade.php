<!DOCTYPE html>
<html>
<head>
    <title>Thanh toán thành công</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            animation: bounce 1s ease-in-out;
        }
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 30px;
        }
        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #28a745;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            color: #6c757d;
            font-weight: 500;
        }
        .info-value {
            font-weight: 600;
            color: #495057;
        }
        .amount {
            color: #28a745;
            font-size: 18px;
        }
        .buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            min-width: 140px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #dee2e6;
        }
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        .vnpay-logo {
            position: absolute;
            top: 15px;
            right: 20px;
            background: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="vnpay-logo">VNPay</div>
            <div class="success-icon">✓</div>
            <h1>Thanh toán thành công!</h1>
            <p>Giao dịch của bạn đã được xử lý thành công</p>
        </div>
        
        <div class="content">
            <div class="order-info">
                <div class="info-row">
                    <span class="info-label">Mã đơn hàng</span>
                    <span class="info-value">#{{ $order_id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Số tiền</span>
                    <span class="info-value amount">{{ number_format($amount) }} VND</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mã giao dịch</span>
                    <span class="info-value">{{ $transaction_id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Thời gian</span>
                    <span class="info-value">{{ date('d/m/Y H:i:s') }}</span>
                </div>
            </div>
            
            <div class="buttons">
                <button onclick="viewOrderDetails()" class="btn btn-primary">
                    📄 Xem chi tiết đơn hàng
                </button>
                <button onclick="continueShopping()" class="btn btn-secondary">
                    🛒 Tiếp tục mua sắm
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function viewOrderDetails() {
            // Mở tab mới đến trang đơn hàng của frontend
            window.open('http://localhost:5174/orders/{{ $order_id }}', '_blank');
            
            // Thông báo cho parent window
            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'payment_success', 
                    order_id: '{{ $order_id }}', 
                    amount: {{ $amount }}, 
                    transaction_id: '{{ $transaction_id }}'
                }, '*');
            }
        }
        
        function continueShopping() {
            if (window.parent !== window) {
                window.parent.postMessage({type: 'continue_shopping'}, '*');
            } else {
                window.location.href = 'http://localhost:5174/';
            }
        }
        
        // Tự động thông báo sau 3 giây
        setTimeout(() => {
            if (window.parent !== window) {
                window.parent.postMessage({
                    type: 'payment_success', 
                    order_id: '{{ $order_id }}', 
                    amount: {{ $amount }}, 
                    transaction_id: '{{ $transaction_id }}'
                }, '*');
            }
        }, 3000);
    </script>
</body>
</html>