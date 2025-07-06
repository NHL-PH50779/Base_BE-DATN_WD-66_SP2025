<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    private $vnp_TmnCode = 'E53K6FXV';
    private $vnp_HashSecret = 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB';
    private $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    private $vnp_ReturnUrl = 'http://localhost:5174/vnpay-return';

    // 🧩 1. Tạo URL thanh toán VNPay
    public function createPayment(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer',
                'amount' => 'required|numeric|min:1000',
                'order_desc' => 'nullable|string',
            ]);

            $orderId = $request->order_id;
            $amount = $request->amount;
            $orderDesc = $request->order_desc ?? "Thanh toan don hang #{$orderId}";

            // Tạo mã giao dịch duy nhất
            $vnp_TxnRef = $orderId . '_' . time();
            
            $inputData = [
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => $this->vnp_TmnCode,
                "vnp_Amount" => $amount * 100, // VNPay yêu cầu nhân 100
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => $request->ip(),
                "vnp_Locale" => "vn",
                "vnp_OrderInfo" => $orderDesc,
                "vnp_OrderType" => "other",
                "vnp_ReturnUrl" => $this->vnp_ReturnUrl,
                "vnp_TxnRef" => $vnp_TxnRef,
            ];

            // Sắp xếp và tạo hash
            ksort($inputData);
            $query = "";
            $hashdata = "";
            
            foreach ($inputData as $key => $value) {
                $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . "=" . urlencode($value);
                $query .= ($query ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            }

            $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);
            $vnpUrl = $this->vnp_Url . "?" . $query . '&vnp_SecureHash=' . $vnpSecureHash;

            return response()->json([
                'success' => true,
                'payment_url' => $vnpUrl,
                'txn_ref' => $vnp_TxnRef
            ]);

        } catch (\Exception $e) {
            Log::error('VNPay create payment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo thanh toán'
            ], 500);
        }
    }

    // 🧩 3. Xử lý kết quả trả về từ VNPay
    public function vnpayReturn(Request $request)
    {
        try {
            $inputData = $request->all();
            $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
            unset($inputData['vnp_SecureHash']);

            // Kiểm tra chữ ký nếu có
            if ($vnp_SecureHash) {
                ksort($inputData);
                $hashData = "";
                foreach ($inputData as $key => $value) {
                    $hashData .= ($hashData ? '&' : '') . urlencode($key) . "=" . urlencode($value);
                }

                $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

                if ($secureHash !== $vnp_SecureHash) {
                    Log::warning('VNPay signature mismatch', [
                        'expected' => $secureHash,
                        'received' => $vnp_SecureHash,
                        'data' => $inputData
                    ]);
                }
            }

            // Lấy thông tin giao dịch
            $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? '';
            $vnp_ResponseCode = $inputData['vnp_ResponseCode'] ?? '';
            $vnp_Amount = ($inputData['vnp_Amount'] ?? 0) / 100;
            $vnp_TransactionNo = $inputData['vnp_TransactionNo'] ?? '';

            // Lấy order_id từ vnp_TxnRef
            $orderId = explode('_', $vnp_TxnRef)[0] ?? 0;
            
            // Kiểm tra order có tồn tại không
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng'
                ], 404);
            }

            // Kiểm tra xem payment đã tồn tại chưa
            $existingPayment = Payment::where('vnp_txn_ref', $vnp_TxnRef)->first();
            
            if (!$existingPayment) {
                // Ghi log giao dịch vào bảng payments
                $payment = Payment::create([
                    'order_id' => $orderId,
                    'vnp_txn_ref' => $vnp_TxnRef,
                    'amount' => $vnp_Amount,
                    'vnp_response_code' => $vnp_ResponseCode,
                    'vnp_transaction_no' => $vnp_TransactionNo,
                    'vnp_bank_code' => $inputData['vnp_BankCode'] ?? '',
                    'vnp_pay_date' => $inputData['vnp_PayDate'] ?? '',
                    'vnp_order_info' => $inputData['vnp_OrderInfo'] ?? '',
                    'status' => $vnp_ResponseCode === '00' ? 'success' : 'failed',
                    'vnp_data' => $inputData
                ]);
            } else {
                $payment = $existingPayment;
            }

            // Cập nhật đơn hàng nếu thanh toán thành công
            if ($vnp_ResponseCode === '00') {
                $order->update([
                    'payment_status' => 'paid',
                    'payment_status_id' => Order::PAYMENT_PAID,
                    'status' => 'confirmed',
                    'order_status_id' => Order::STATUS_CONFIRMED
                ]);

                Log::info('VNPay payment success', [
                    'order_id' => $orderId,
                    'amount' => $vnp_Amount,
                    'transaction_id' => $vnp_TransactionNo
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Thanh toán thành công',
                    'order_id' => $orderId,
                    'amount' => $vnp_Amount,
                    'transaction_id' => $vnp_TransactionNo
                ]);
            } else {
                Log::info('VNPay payment failed', [
                    'order_id' => $orderId,
                    'response_code' => $vnp_ResponseCode
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Thanh toán thất bại',
                    'response_code' => $vnp_ResponseCode
                ]);
            }

        } catch (\Exception $e) {
            Log::error('VNPay return error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xử lý kết quả thanh toán',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // 🧩 4. IPN - Xử lý tự động từ VNPay
    public function vnpayIPN(Request $request)
    {
        try {
            $inputData = $request->all();
            $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
            unset($inputData['vnp_SecureHash']);

            // Kiểm tra chữ ký
            ksort($inputData);
            $hashData = "";
            foreach ($inputData as $key => $value) {
                $hashData .= ($hashData ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            }

            $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

            if ($secureHash !== $vnp_SecureHash) {
                return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
            }

            $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? '';
            $vnp_ResponseCode = $inputData['vnp_ResponseCode'] ?? '';
            $orderId = explode('_', $vnp_TxnRef)[0] ?? 0;

            $order = Order::find($orderId);
            if (!$order) {
                return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
            }

            if ($vnp_ResponseCode === '00') {
                $order->update([
                    'payment_status' => 'paid',
                    'payment_status_id' => Order::PAYMENT_PAID,
                    'status' => 'confirmed',
                    'order_status_id' => Order::STATUS_CONFIRMED
                ]);

                Log::info('VNPay IPN success', ['order_id' => $orderId]);
                return response()->json(['RspCode' => '00', 'Message' => 'Success']);
            }

            Log::info('VNPay IPN failed', ['order_id' => $orderId, 'response_code' => $vnp_ResponseCode]);
            return response()->json(['RspCode' => $vnp_ResponseCode, 'Message' => 'Payment failed']);

        } catch (\Exception $e) {
            Log::error('VNPay IPN error: ' . $e->getMessage());
            return response()->json(['RspCode' => '99', 'Message' => 'System error']);
        }
    }
}