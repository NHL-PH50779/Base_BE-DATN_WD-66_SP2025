# 🚀 VNPay Integration - Improved Version

## 📋 Tổng quan cải thiện

### ✅ Đã khắc phục
- **Thống nhất Controller**: Loại bỏ `PaymentController`, chỉ sử dụng `VNPayController`
- **Bảo mật nâng cao**: Kiểm tra chữ ký nghiêm ngặt, validation đầu vào
- **Database Transaction**: Đảm bảo tính nhất quán dữ liệu
- **Xử lý lỗi tốt hơn**: Logging chi tiết, error handling
- **Tránh duplicate**: Kiểm tra giao dịch trùng lặp
- **Environment config**: Cấu hình linh hoạt qua .env

## 🔧 Cấu hình

### 1. Environment Variables
```bash
# Thêm vào file .env
VNP_TMN_CODE=E53K6FXV
VNP_HASH_SECRET=WD2X54VNM4W6PDRDNBPXUH95YV4B38NB
VNP_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNP_RETURN_URL=http://localhost:5174/vnpay-return
```

### 2. Database Migration
```bash
php artisan migrate
```

## 🔄 Luồng thanh toán

### Bước 1: Tạo đơn hàng
```php
POST /api/orders
{
    "name": "Nguyễn Văn A",
    "phone": "0123456789",
    "email": "user@example.com",
    "address": "123 ABC Street",
    "payment_method": "vnpay",
    "total": 100000,
    "items": [...]
}
```

### Bước 2: Tạo URL thanh toán VNPay
```php
POST /api/vnpay/create-payment
{
    "order_id": 123,
    "amount": 100000,
    "order_desc": "Thanh toán đơn hàng #123"
}

Response:
{
    "success": true,
    "payment_url": "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?...",
    "txn_ref": "123_1234567890_1234"
}
```

### Bước 3: Redirect user đến VNPay
```javascript
window.location.href = response.payment_url;
```

### Bước 4: Xử lý kết quả trả về
```php
GET /api/vnpay/return?vnp_ResponseCode=00&vnp_TxnRef=123_1234567890_1234&...

Response:
{
    "success": true,
    "message": "Thanh toán thành công",
    "order_id": 123,
    "amount": 100000,
    "transaction_id": "VNP123456789"
}
```

## 🛡️ Bảo mật

### Kiểm tra chữ ký
```php
// Tự động kiểm tra vnp_SecureHash
// Từ chối request nếu chữ ký không hợp lệ
if ($secureHash !== $vnp_SecureHash) {
    return response()->json(['error' => 'Invalid signature'], 400);
}
```

### Validation đầu vào
```php
$request->validate([
    'order_id' => 'required|integer|exists:orders,id',
    'amount' => 'required|numeric|min:1000|max:1000000000',
    'order_desc' => 'nullable|string|max:255',
]);
```

## 📊 Database Schema

### Bảng Orders (đã thêm)
```sql
vnpay_txn_ref VARCHAR(255) NULL
vnpay_transaction_no VARCHAR(255) NULL  
vnpay_response_code VARCHAR(255) NULL
paid_at TIMESTAMP NULL
```

### Bảng Payments (đã cập nhật)
```sql
payment_method VARCHAR(255)
vnp_txn_ref VARCHAR(255) NULL
vnp_response_code VARCHAR(255) NULL
vnp_transaction_no VARCHAR(255) NULL
vnp_bank_code VARCHAR(255) NULL
vnp_pay_date VARCHAR(255) NULL
vnp_order_info VARCHAR(255) NULL
vnp_data JSON NULL
response_data JSON NULL
```

## 🔍 Logging & Monitoring

### Success Log
```php
Log::info('VNPay payment success', [
    'order_id' => $orderId,
    'amount' => $vnp_Amount,
    'transaction_id' => $vnp_TransactionNo,
    'response_code' => $vnp_ResponseCode
]);
```

### Error Log
```php
Log::error('VNPay signature mismatch', [
    'expected' => $secureHash,
    'received' => $vnp_SecureHash,
    'hash_data' => $hashData,
    'input_data' => $inputData
]);
```

## 🧪 Testing

### Chạy test
```bash
php test_vnpay_improved.php
```

### Test cases
- ✅ Tạo URL thanh toán
- ✅ Validation đầu vào
- ✅ Kiểm tra chữ ký
- ✅ Xử lý kết quả trả về
- ✅ IPN webhook
- ✅ Duplicate prevention

## 🚨 Xử lý lỗi

### Frontend Error Handling
```typescript
try {
    const response = await vnpayService.createPayment(data);
    window.location.href = response.payment_url;
} catch (error) {
    console.error('VNPay error:', error);
    showError('Không thể tạo thanh toán VNPay');
}
```

### Backend Error Response
```php
return response()->json([
    'success' => false,
    'message' => 'Đơn hàng đã được thanh toán',
    'error_code' => 'ORDER_ALREADY_PAID'
], 400);
```

## 📈 Performance

### Database Transaction
```php
DB::beginTransaction();
try {
    // Create payment record
    // Update order status
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}
```

### Duplicate Prevention
```php
$existingPayment = Payment::where('vnp_txn_ref', $vnp_TxnRef)->first();
if ($existingPayment) {
    return response()->json(['message' => 'Already processed']);
}
```

## 🔄 IPN Webhook

### Endpoint
```
POST /api/vnpay/ipn
```

### Response Format
```php
// Success
return response()->json(['RspCode' => '00', 'Message' => 'Success']);

// Error  
return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
```

## 🎯 Best Practices

1. **Luôn kiểm tra chữ ký** trước khi xử lý
2. **Sử dụng database transaction** cho tính nhất quán
3. **Log chi tiết** để debug và monitor
4. **Validate đầu vào** nghiêm ngặt
5. **Xử lý duplicate** để tránh xung đột
6. **Environment config** cho flexibility
7. **Error handling** toàn diện

## 🚀 Production Checklist

- [ ] Cập nhật VNP_URL sang production
- [ ] Kiểm tra VNP_TMN_CODE và VNP_HASH_SECRET
- [ ] Test IPN webhook với VNPay
- [ ] Setup monitoring và alerting
- [ ] Backup database trước khi deploy
- [ ] Test end-to-end payment flow