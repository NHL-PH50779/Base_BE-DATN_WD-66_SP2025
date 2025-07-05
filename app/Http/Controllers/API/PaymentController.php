<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
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
        $this->vnp_ReturnUrl = config('vnpay.vnp_ReturnUrl');
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|numeric',
            'amount' => 'required|numeric|min:1000',
            'order_desc' => 'string|nullable',
            'bank_code' => 'nullable|string'
        ]);
        
        Log::info('Payment create request:', $request->all());

        // Kiểm tra đơn hàng tồn tại
        $order = Order::find($request->order_id);
        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }
        
        $vnp_TxnRef = $request->order_id;
        $vnp_OrderInfo = $request->order_desc ?: 'Thanh toan don hang #' . $request->order_id;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = intval($request->amount * 100);
        $vnp_Locale = 'vn';
        $vnp_BankCode = $request->bank_code;
        $vnp_IpAddr = $request->ip() ?: '127.0.0.1';
        
        if ($vnp_Amount < 100000) {
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
        
        Log::info('VNPay URL created:', ['url' => $vnp_Url]);

        return response()->json([
            'payment_url' => $vnp_Url,
            'paymentUrl' => $vnp_Url,
            'txn_ref' => $vnp_TxnRef,
            'order_id' => $order->id
        ]);
    }

    public function vnpayReturn(Request $request)
    {
        try {
            Log::info('VNPay Return Request:', $request->all());
            
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
                    $order = Order::find($orderId);
                    
                    if (!$order) {
                        Log::error('VNPay: Order not found', ['order_id' => $orderId]);
                        return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=99&vnp_TxnRef=' . $orderId . '&vnp_TransactionStatus=99');
                    }
                    
                    // Cập nhật trạng thái đơn hàng
                    $order->update([
                        'order_status_id' => 2, // Đã xác nhận
                        'payment_status_id' => 2, // Đã thanh toán
                        'payment_status' => 'paid',
                        'vnpay_txn_ref' => $request->vnp_TxnRef,
                        'vnpay_response_code' => $request->vnp_ResponseCode,
                        'vnpay_transaction_no' => $request->vnp_TransactionNo ?? null,
                        'paid_at' => now()
                    ]);
                    
                    Log::info('VNPay: Order updated successfully', [
                        'order_id' => $order->id,
                        'update_data' => $updateData
                    ]);
                    
                    // Xóa giỏ hàng
                    try {
                        $cart = \App\Models\Cart::where('user_id', $order->user_id)->first();
                        if ($cart) {
                            $cart->items()->delete();
                        }
                    } catch (\Exception $e) {
                        Log::warning('VNPay: Failed to clear cart', ['error' => $e->getMessage()]);
                    }
                    
                    return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=00&vnp_TxnRef=' . $order->id . '&vnp_TransactionStatus=00');
                } else {
                    return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=' . $request->vnp_ResponseCode . '&vnp_TxnRef=' . $request->vnp_TxnRef . '&vnp_TransactionStatus=02');
                }
            } else {
                Log::error('VNPay: Hash verification failed');
                return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=97&vnp_TxnRef=' . $request->vnp_TxnRef . '&vnp_TransactionStatus=97');
            }
        } catch (\Exception $e) {
            Log::error('VNPay Return Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect('http://localhost:5174/vnpay-return?vnp_ResponseCode=99&vnp_TxnRef=0&vnp_TransactionStatus=99');
        }
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
            $order = Order::find($orderId);
            
            if ($order) {
                $order->update([
                    'order_status_id' => 2, // Đã xác nhận
                    'payment_status_id' => 2, // Đã thanh toán
                    'payment_status' => 'paid',
                    'paid_at' => now()
                ]);
                
                Log::info('VNPay IPN: Order confirmed', ['order_id' => $order->id]);
            }
            
            return response('RESPONSE=00', 200);
        }
        
        Log::error('VNPay IPN: Invalid signature or failed payment', $request->all());
        return response('RESPONSE=97', 200);
    }
}