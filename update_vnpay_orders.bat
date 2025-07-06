@echo off
echo Cập nhật trạng thái đơn hàng VNPay...
php artisan orders:update-vnpay-status
echo Hoàn thành!
pause