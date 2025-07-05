<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    private $vnp_TmnCode;
    private $vnp_HashSecret;
    private $vnp_Url;
    private $vnp_ReturnUrl;
    
    public function __construct()
    {
        $this->vnp_TmnCode = config('vnpay.vnp_TmnCode');
        $this->vnp_HashSecret = config('vnpay.vnp_HashSecret');
        $this->vnp_Url = config('vnpay.vnp_Url');
        $this->vnp_ReturnUrl = 'http://localhost:5174/vnpay-return';
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'order_desc' => 'string|nullable',
            'bank_code' => 'nullable|string',
            'order_id' => 'nullable|numeric'
        ]);
        
        // Debug log
        Log::info('VNPay create payment request:', $request->all());

        // Lấy order_id từ request hoặc tạo mới
        $orderId = $request->order_id;
        
        if ($orderId) {
            // Kiểm tra đơn hàng tồn tại
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
            }
        } else {
            // Tạo đơn hàng tạm thời cho VNPay (sẽ được cập nhật sau)
            $orderId = time(); // Sử dụng timestamp làm ID tạm
        }
        
        // Sử dụng order ID thực tế
        $vnp_TxnRef = $orderId;
        $vnp_OrderInfo = $request->order_desc ?: 'Thanh toan don hang';
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = intval($request->amount * 100); // VNPay yêu cầu số tiền * 100
        $vnp_Locale = 'vn';
        $vnp_BankCode = $request->bank_code;
        $vnp_IpAddr = $request->ip() ?: '127.0.0.1';
        
        // Lưu thông tin thanh toán vào session hoặc cache để xử lý sau
        if (!$request->order_id) {
            session(['vnpay_pending_' . $vnp_TxnRef => [
                'amount' => $request->amount,
                'order_desc' => $vnp_OrderInfo,
                'created_at' => now()
            ]]);
        }
        
        // Validation
        if ($vnp_Amount < 100000) { // Tối thiểu 1000 VND
            return response()->json(['error' => 'Số tiền tối thiểu là 1,000 VND'], 400);
        }

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $this->vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
        );

        if (!empty($vnp_BankCode)) {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);
        $vnp_Url = $this->vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;
        
        // Debug log
        Log::info('VNPay URL created:', [
            'url' => $vnp_Url,
            'hashdata' => $hashdata,
            'inputData' => $inputData,
            'secureHash' => $vnpSecureHash
        ]);

        return response()->json([
            'payment_url' => $vnp_Url,
            'paymentUrl' => $vnp_Url,
            'txn_ref' => $vnp_TxnRef,
            'order_id' => $order->id
        ]);
    }

    public function vnpayReturn(Request $request)
    {
        $vnp_SecureHash = $request->vnp_SecureHash;
        $inputData = array();
        
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $hashData = "";
        $i = 0;
        
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);
        
        Log::info('VNPay Return - Hash verification', [
            'calculated_hash' => $secureHash,
            'received_hash' => $vnp_SecureHash,
            'match' => $secureHash == $vnp_SecureHash,
            'response_code' => $request->vnp_ResponseCode
        ]);
        
        if ($secureHash == $vnp_SecureHash) {
            $orderId = $request->vnp_TxnRef;
            
            if ($request->vnp_ResponseCode == '00') {
                try {
                    Log::info('VNPay: Processing successful payment', [
                        'order_id' => $orderId,
                        'response_code' => $request->vnp_ResponseCode,
                        'txn_ref' => $request->vnp_TxnRef
                    ]);
                    
                    // Tìm đơn hàng đã tạo trước đó
                    $order = Order::find($orderId);
                    
                    if (!$order) {
                        Log::error('VNPay: Order not found', ['order_id' => $orderId]);
                        return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=99&vnp_TxnRef=' . $orderId . '&vnp_TransactionStatus=99');
                    }
                    
                    Log::info('VNPay: Order found, current status', [
                        'order_id' => $order->id,
                        'current_order_status' => $order->order_status_id,
                        'current_payment_status' => $order->payment_status_id
                    ]);
                    
                    // Cập nhật trạng thái đơn hàng - Đã xác nhận và đã thanh toán
                    $updated = $order->update([
                        'order_status_id' => 2, // Đã xác nhận
                        'payment_status_id' => 2, // Đã thanh toán
                        'payment_status' => 'paid',
                        'vnpay_txn_ref' => $request->vnp_TxnRef,
                        'vnpay_response_code' => $request->vnp_ResponseCode,
                        'vnpay_transaction_no' => $request->vnp_TransactionNo ?? null,
                        'paid_at' => now()
                    ]);
                    
                    Log::info('VNPay: Order update result', [
                        'order_id' => $order->id,
                        'update_success' => $updated,
                        'new_order_status' => $order->fresh()->order_status_id,
                        'new_payment_status' => $order->fresh()->payment_status_id
                    ]);
                    
                    // Xóa giỏ hàng
                    $cart = \App\Models\Cart::where('user_id', $order->user_id)->first();
                    if ($cart) {
                        $cart->items()->delete();
                        Log::info('VNPay: Cart cleared for user', ['user_id' => $order->user_id]);
                    }
                    
                    return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=00&vnp_TxnRef=' . $order->id . '&vnp_TransactionStatus=00');
                } catch (\Exception $e) {
                    Log::error('VNPay: Failed to update order', [
                        'error' => $e->getMessage(),
                        'order_id' => $orderId
                    ]);
                    return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=99&vnp_TxnRef=' . $orderId . '&vnp_TransactionStatus=99');
                }
            } else {
                return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=' . $request->vnp_ResponseCode . '&vnp_TxnRef=' . $orderId . '&vnp_TransactionStatus=02');
            }
        } else {
            Log::error('VNPay: Hash verification failed', [
                'calculated' => $secureHash,
                'received' => $vnp_SecureHash,
                'request_data' => $request->all()
            ]);
        }
        
        return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=99&vnp_TxnRef=0&vnp_TransactionStatus=99');
    }

    public function vnpayIpn(Request $request)
    {
        $vnp_SecureHash = $request->vnp_SecureHash;
        $inputData = array();
        
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $hashData = "";
        $i = 0;
        
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);
        
        if ($secureHash == $vnp_SecureHash && $request->vnp_ResponseCode == '00') {
            $orderId = $request->vnp_TxnRef;
            $order = Order::where('vnpay_txn_ref', $request->vnp_TxnRef)->first();
            
            if ($order) {
                $order->update([
                    'order_status_id' => 2, // Đã xác nhận
                    'payment_status_id' => 2, // Đã thanh toán
                    'payment_status' => 'paid'
                ]);
                
                Log::info('VNPay IPN: Order confirmed', ['order_id' => $order->id]);
            }
            
            return response('RESPONSE=00', 200);
        }
        
        Log::error('VNPay IPN: Invalid signature or failed payment', $request->all());
        return response('RESPONSE=97', 200);
    }

    public function checkPaymentStatus($orderId)
    {
        // Tìm theo ID hoặc vnpay_txn_ref
        $order = Order::where('id', $orderId)
                     ->orWhere('vnpay_txn_ref', $orderId)
                     ->first();
        
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }
        
        return response()->json([
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'vnpay_response_code' => $order->vnpay_response_code,
            'paid_at' => $order->paid_at
        ]);
    }
    
    public function checkPaymentByTxnRef($txnRef)
    {
        $order = Order::where('vnpay_txn_ref', $txnRef)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }
        
        return response()->json([
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
            'vnpay_response_code' => $order->vnpay_response_code,
            'paid_at' => $order->paid_at
        ]);
    }
    
    public function processReturn(Request $request)
    {
        Log::info('VNPay processReturn called:', $request->all());
        
        $vnp_TxnRef = $request->vnp_TxnRef;
        $vnp_ResponseCode = $request->vnp_ResponseCode;
        
        if ($vnp_ResponseCode === '00') {
            $order = Order::find($vnp_TxnRef);
            
            if ($order) {
                $order->update([
                    'order_status_id' => 2,
                    'payment_status_id' => 2,
                    'payment_status' => 'paid',
                    'vnpay_response_code' => $vnp_ResponseCode,
                    'paid_at' => now()
                ]);
                
                Log::info('VNPay: Order status updated successfully', ['order_id' => $order->id]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Cập nhật trạng thái thành công',
                    'order_id' => $order->id
                ]);
            }
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Không thể cập nhật trạng thái đơn hàng'
        ]);
    }
}