<!DOCTYPE html>
<html>
<head>
    <title>Test VNPay</title>
    <meta charset="utf-8">
</head>
<body>
    <h2>Test VNPay Payment</h2>
    
    <form id="paymentForm">
        <div>
            <label>Mã đơn hàng:</label>
            <input type="text" id="order_id" value="{{ time() }}" required>
        </div>
        <div>
            <label>Số tiền (VND):</label>
            <input type="number" id="amount" value="100000" required>
        </div>
        <div>
            <label>Thông tin đơn hàng:</label>
            <input type="text" id="order_info" value="Test thanh toán VNPay">
        </div>
        <button type="submit">Thanh toán VNPay</button>
    </form>

    <script>
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                order_id: document.getElementById('order_id').value,
                amount: document.getElementById('amount').value,
                order_info: document.getElementById('order_info').value
            };
            
            fetch('http://127.0.0.1:8000/api/vnpay/create-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.payment_url) {
                    window.location.href = data.payment_url;
                } else {
                    alert('Lỗi tạo thanh toán');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Lỗi kết nối');
            });
        });
    </script>
</body>
</html>