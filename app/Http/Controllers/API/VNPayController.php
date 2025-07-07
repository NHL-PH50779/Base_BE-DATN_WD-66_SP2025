<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VNPayController extends Controller
{
    private $vnp_TmnCode;
    private $vnp_HashSecret;
    private $vnp_Url;
    private $vnp_ReturnUrl;
    
    public function __construct()
    {
        $this->vnp_TmnCode = env('VNP_TMN_CODE', 'E53K6FXV');
        $this->vnp_HashSecret = env('VNP_HASH_SECRET', 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB');
        $this->vnp_Url = env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $this->vnp_ReturnUrl = env('VNP_RETURN_URL', 'http://localhost:5174/vnpay-return');
    }

    // 🧩 1. Tạo URL thanh toán VNPay
    public function createPayment(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
                'amount' => 'required|numeric|min:1000|max:1000000000',
                'order_desc' => 'nullable|string|max:255',
            ]);

            $orderId = $request->order_id;
            $amount = $request->amount;
            $orderDesc = $request->order_desc ?? "Thanh toan don hang #{$orderId}";

            // Kiểm tra đơn hàng tồn tại và chưa thanh toán
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng không tồn tại'
                ], 404);
            }

            if ($order->payment_status_id == Order::PAYMENT_PAID) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng đã được thanh toán'
                ], 400);
            }

            // Tạo mã giao dịch duy nhất
            $vnp_TxnRef = $orderId . '_' . time() . '_' . rand(1000, 9999);
            
            // Kiểm tra trùng lặp transaction
            $existingPayment = Payment::where('vnp_txn_ref', $vnp_TxnRef)->first();
            if ($existingPayment) {
                $vnp_TxnRef = $orderId . '_' . time() . '_' . rand(10000, 99999);
            }
            
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

            // Cập nhật đơn hàng với thông tin VNPay
            $order->update([
                'payment_method' => 'vnpay',
                'vnpay_txn_ref' => $vnp_TxnRef
            ]);

            Log::info('VNPay payment URL created', [
                'order_id' => $orderId,
                'amount' => $amount,
                'txn_ref' => $vnp_TxnRef
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $vnpUrl,
                'txn_ref' => $vnp_TxnRef
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('VNPay create payment error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
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
            
            if (empty($vnp_SecureHash)) {
                Log::warning('VNPay return without signature', $inputData);
                return response()->json([
                    'success' => false,
                    'message' => 'Thiếu chữ ký bảo mật'
                ], 400);
            }
            
            unset($inputData['vnp_SecureHash']);

            // Kiểm tra chữ ký bắt buộc
            ksort($inputData);
            $hashData = "";
            foreach ($inputData as $key => $value) {
                if (!empty($value)) {
                    $hashData .= ($hashData ? '&' : '') . urlencode($key) . "=" . urlencode($value);
                }
            }

            $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

            if ($secureHash !== $vnp_SecureHash) {
                Log::error('VNPay signature mismatch', [
                    'expected' => $secureHash,
                    'received' => $vnp_SecureHash,
                    'hash_data' => $hashData,
                    'input_data' => $inputData
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Chữ ký không hợp lệ'
                ], 400);
            }

            // Lấy thông tin giao dịch
            $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? '';
            $vnp_ResponseCode = $inputData['vnp_ResponseCode'] ?? '';
            $vnp_Amount = ($inputData['vnp_Amount'] ?? 0) / 100;
            $vnp_TransactionNo = $inputData['vnp_TransactionNo'] ?? '';

            if (empty($vnp_TxnRef)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thiếu mã giao dịch'
                ], 400);
            }

            // Lấy order_id từ vnp_TxnRef
            $orderIdParts = explode('_', $vnp_TxnRef);
            $orderId = $orderIdParts[0] ?? 0;
            
            if (!$orderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã đơn hàng không hợp lệ'
                ], 400);
            }

            // Kiểm tra order có tồn tại không
            $order = Order::find($orderId);
            if (!$order) {
                Log::error('Order not found for VNPay return', ['order_id' => $orderId, 'txn_ref' => $vnp_TxnRef]);
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng'
                ], 404);
            }

            // Kiểm tra xem payment đã tồn tại chưa (tránh duplicate)
            $existingPayment = Payment::where('vnp_txn_ref', $vnp_TxnRef)->first();
            
            if ($existingPayment) {
                Log::info('Payment already processed', ['txn_ref' => $vnp_TxnRef]);
                return response()->json([
                    'success' => $existingPayment->status === 'success',
                    'message' => $existingPayment->status === 'success' ? 'Thanh toán đã được xử lý thành công' : 'Thanh toán thất bại',
                    'order_id' => $orderId,
                    'amount' => $vnp_Amount,
                    'transaction_id' => $vnp_TransactionNo
                ]);
            }

            // Sử dụng database transaction
            DB::beginTransaction();
            try {
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

                // Cập nhật đơn hàng nếu thanh toán thành công
                if ($vnp_ResponseCode === '00') {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_status_id' => Order::PAYMENT_PAID,
                        'status' => 'confirmed',
                        'order_status_id' => Order::STATUS_CONFIRMED,
                        'vnpay_transaction_no' => $vnp_TransactionNo,
                        'vnpay_response_code' => $vnp_ResponseCode,
                        'paid_at' => now()
                    ]);

                    DB::commit();

                    Log::info('VNPay payment success', [
                        'order_id' => $orderId,
                        'amount' => $vnp_Amount,
                        'transaction_id' => $vnp_TransactionNo,
                        'response_code' => $vnp_ResponseCode
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Thanh toán thành công',
                        'order_id' => $orderId,
                        'amount' => $vnp_Amount,
                        'transaction_id' => $vnp_TransactionNo
                    ]);
                } else {
                    DB::commit();
                    
                    Log::info('VNPay payment failed', [
                        'order_id' => $orderId,
                        'response_code' => $vnp_ResponseCode,
                        'amount' => $vnp_Amount
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Thanh toán thất bại',
                        'response_code' => $vnp_ResponseCode,
                        'order_id' => $orderId
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('VNPay return error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xử lý kết quả thanh toán'
            ], 500);
        }
    }

    // 🧩 4. IPN - Xử lý webhook từ VNPay
    public function vnpayIPN(Request $request)
    {
        try {
            $inputData = $request->all();
            $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
            
            Log::info('VNPay IPN received', $inputData);
            
            if (empty($vnp_SecureHash)) {
                Log::error('VNPay IPN missing signature');
                return response()->json(['RspCode' => '97', 'Message' => 'Missing signature']);
            }
            
            unset($inputData['vnp_SecureHash']);

            // Kiểm tra chữ ký nghiêm ngặt
            ksort($inputData);
            $hashData = "";
            foreach ($inputData as $key => $value) {
                if (!empty($value)) {
                    $hashData .= ($hashData ? '&' : '') . urlencode($key) . "=" . urlencode($value);
                }
            }

            $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

            if ($secureHash !== $vnp_SecureHash) {
                Log::error('VNPay IPN signature mismatch', [
                    'expected' => $secureHash,
                    'received' => $vnp_SecureHash,
                    'hash_data' => $hashData
                ]);
                return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
            }

            $vnp_TxnRef = $inputData['vnp_TxnRef'] ?? '';
            $vnp_ResponseCode = $inputData['vnp_ResponseCode'] ?? '';
            $vnp_Amount = ($inputData['vnp_Amount'] ?? 0) / 100;
            $vnp_TransactionNo = $inputData['vnp_TransactionNo'] ?? '';
            
            if (empty($vnp_TxnRef)) {
                Log::error('VNPay IPN missing transaction reference');
                return response()->json(['RspCode' => '02', 'Message' => 'Missing transaction reference']);
            }
            
            $orderIdParts = explode('_', $vnp_TxnRef);
            $orderId = $orderIdParts[0] ?? 0;

            $order = Order::find($orderId);
            if (!$order) {
                Log::error('VNPay IPN order not found', ['order_id' => $orderId, 'txn_ref' => $vnp_TxnRef]);
                return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
            }

            // Kiểm tra xem đã xử lý chưa (tránh duplicate)
            $existingPayment = Payment::where('vnp_txn_ref', $vnp_TxnRef)->first();
            if ($existingPayment) {
                Log::info('VNPay IPN already processed', ['txn_ref' => $vnp_TxnRef]);
                return response()->json(['RspCode' => '00', 'Message' => 'Already processed']);
            }

            // Sử dụng database transaction
            DB::beginTransaction();
            try {
                // Tạo payment record
                Payment::create([
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

                if ($vnp_ResponseCode === '00') {
                    // Cập nhật đơn hàng thành công
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_status_id' => Order::PAYMENT_PAID,
                        'status' => 'confirmed',
                        'order_status_id' => Order::STATUS_CONFIRMED,
                        'vnpay_transaction_no' => $vnp_TransactionNo,
                        'vnpay_response_code' => $vnp_ResponseCode,
                        'paid_at' => now()
                    ]);

                    DB::commit();
                    
                    Log::info('VNPay IPN success', [
                        'order_id' => $orderId,
                        'amount' => $vnp_Amount,
                        'transaction_id' => $vnp_TransactionNo
                    ]);
                    
                    return response()->json(['RspCode' => '00', 'Message' => 'Success']);
                } else {
                    DB::commit();
                    
                    Log::info('VNPay IPN payment failed', [
                        'order_id' => $orderId, 
                        'response_code' => $vnp_ResponseCode,
                        'amount' => $vnp_Amount
                    ]);
                    
                    return response()->json(['RspCode' => $vnp_ResponseCode, 'Message' => 'Payment failed']);
                }
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('VNPay IPN error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json(['RspCode' => '99', 'Message' => 'System error']);
        }
    }
}