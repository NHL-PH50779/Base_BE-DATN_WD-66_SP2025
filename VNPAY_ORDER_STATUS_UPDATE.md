# Cập nhật trạng thái đơn hàng VNPay

## Vấn đề
Các đơn hàng VNPay hiện tại đang ở trạng thái "Chờ xác nhận/Chưa thanh toán" thay vì "Đã xác nhận/Đã thanh toán" sau khi thanh toán thành công.

## Giải pháp đã thực hiện

### 1. Sửa PaymentController
- Cập nhật method `vnpayReturn()` và `vnpayCallback()` để set đúng trạng thái:
  - `order_status_id` = 2 (Đã xác nhận)
  - `payment_status_id` = 2 (Đã thanh toán)
  - `status` = 'confirmed'
  - `payment_status` = 'paid'

### 2. Sửa OrderController
- Đảm bảo khi tạo đơn hàng mới, các field trạng thái được set đúng giá trị mặc định

### 3. Tạo Command cập nhật
- Command: `orders:update-vnpay-status`
- Tự động cập nhật tất cả đơn hàng VNPay hiện có từ trạng thái chờ thành đã xác nhận/đã thanh toán

## Cách sử dụng

### Chạy migration (nếu cần)
```bash
php artisan migrate
```

### Cập nhật đơn hàng VNPay hiện có
```bash
php artisan orders:update-vnpay-status
```

Hoặc chạy file batch:
```bash
update_vnpay_orders.bat
```

### Kiểm tra kết quả
- Tất cả đơn hàng VNPay sẽ có:
  - `order_status_id` = 2 (Đã xác nhận)
  - `payment_status_id` = 2 (Đã thanh toán)
  - `status` = 'confirmed'
  - `payment_status` = 'paid'

## Lưu ý
- Các đơn hàng VNPay mới sẽ tự động được cập nhật trạng thái đúng khi thanh toán thành công
- Command chỉ cập nhật các đơn hàng VNPay đang ở trạng thái chờ xác nhận/chưa thanh toán
- Backup database trước khi chạy command để đảm bảo an toàn