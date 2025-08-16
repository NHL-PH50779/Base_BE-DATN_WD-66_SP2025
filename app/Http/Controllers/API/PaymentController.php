<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private $vnp_TmnCode = 'E53K6FXV';
    private $vnp_HashSecret = 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB';
    private $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    private $vnp_ReturnUrl;

    public function __construct()
    {
        $this->vnp_ReturnUrl = env('VNP_RETURNURL', 'http://localhost:3000/vnpay-return');
    }

    public function createVnpayUrl(Request $request)
    {
        $request->validate([
            'orderId' => 'required|integer',
            'amount' => 'required|integer|min:1000'
        ]);

        $order = Order::findOrFail($request->orderId);
        
        if ($order->payment_status === 'paid') {
            return response()->json(['error' => 'Order already paid'], 400);
        }

        // Cập nhật đơn hàng với is_vnpay = true và status = pending
        $order->update([
            'is_vnpay' => true,
            'order_status_id' => Order::STATUS_PENDING,
            'payment_status_id' => Order::PAYMENT_PENDING,
            'status' => 'pending'
        ]);

        $vnp_TxnRef = $order->id . '_' . time();
        $vnp_OrderInfo = 'Thanh toan don hang #' . $order->id;
        $vnp_Amount = $request->amount * 100;

        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $request->ip(),
            "vnp_Locale" => "vn",
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => "other",
            "vnp_ReturnUrl" => $this->vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query = "";
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            $query .= ($query ? '&' : '') . urlencode($key) . "=" . urlencode($value);
        }

        $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);
        $vnp_Url = $this->vnp_Url . "?" . $query . '&vnp_SecureHash=' . $vnpSecureHash;

        return response()->json(['paymentUrl' => $vnp_Url]);
    }

    public function vnpayReturn(Request $request)
    {
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']);

        ksort($inputData);
        $hashData = "";
        foreach ($inputData as $key => $value) {
            $hashData .= ($hashData ? '&' : '') . urlencode($key) . "=" . urlencode($value);
        }

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

        if ($secureHash !== $vnp_SecureHash) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $orderId = explode('_', $inputData['vnp_TxnRef'])[0];
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($inputData['vnp_ResponseCode'] === '00') {
            // Cập nhật đơn hàng thành công
            $order->update([
                'payment_status_id' => Order::PAYMENT_PAID,
                'order_status_id' => Order::STATUS_CONFIRMED,
                'payment_status' => 'paid',
                'status' => 'success'
            ]);

            // Ghi log vào bảng payments
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'vnpay',
                'amount' => $inputData['vnp_Amount'] / 100,
                'transaction_id' => $inputData['vnp_TransactionNo'] ?? null,
                'status' => 'success',
                'response_data' => $inputData
            ]);

            Log::info('VNPay payment success', ['orderId' => $orderId]);
            return response()->json([
                'success' => true, 
                'message' => 'Payment successful',
                'order' => $order
            ]);
        }

        // Ghi log thất bại
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'vnpay',
            'amount' => $inputData['vnp_Amount'] / 100,
            'transaction_id' => $inputData['vnp_TransactionNo'] ?? null,
            'status' => 'failed',
            'response_data' => $inputData
        ]);

        Log::info('VNPay payment failed', ['orderId' => $orderId, 'responseCode' => $inputData['vnp_ResponseCode']]);
        return response()->json([
            'success' => false, 
            'message' => 'Payment failed',
            'responseCode' => $inputData['vnp_ResponseCode']
        ]);
    }

    public function vnpayCallback(Request $request)
    {
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']);

        ksort($inputData);
        $hashData = "";
        foreach ($inputData as $key => $value) {
            $hashData .= ($hashData ? '&' : '') . urlencode($key) . "=" . urlencode($value);
        }

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

        if ($secureHash !== $vnp_SecureHash) {
            return response()->json(['code' => '97', 'message' => 'Invalid signature']);
        }

        $orderId = explode('_', $inputData['vnp_TxnRef'])[0];
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json(['code' => '01', 'message' => 'Order not found']);
        }

        if ($inputData['vnp_ResponseCode'] === '00') {
            $order->update([
                'order_status_id' => Order::STATUS_CONFIRMED,
                'payment_status_id' => Order::PAYMENT_PAID,
                'payment_method' => 'vnpay',
                'payment_status' => 'paid',
                'status' => 'confirmed'
            ]);

            Log::info('VNPay payment success', ['orderId' => $orderId]);
            return response()->json(['code' => '00', 'message' => 'Payment successful']);
        }

        Log::info('VNPay payment failed', ['orderId' => $orderId, 'responseCode' => $inputData['vnp_ResponseCode']]);
        return response()->json(['code' => $inputData['vnp_ResponseCode'], 'message' => 'Payment failed']);
    }
}
