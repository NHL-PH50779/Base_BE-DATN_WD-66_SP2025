<?php

namespace App\Http\Controllers;

use App\Services\VNPayService;
use App\Models\Order;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected $vnpayService;

    public function __construct(VNPayService $vnpayService)
    {
        $this->vnpayService = $vnpayService;
    }

    public function processPayment(Request $request)
    {
        $orderId = $request->order_id;
        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json(['error' => 'Đơn hàng không tồn tại'], 404);
        }

        $paymentUrl = $this->vnpayService->createPaymentUrl(
            $order->id,
            $order->total_amount,
            'Thanh toán đơn hàng #' . $order->id
        );

        return response()->json([
            'payment_url' => $paymentUrl,
            'order_id' => $order->id
        ]);
    }

    public function handleReturn(Request $request)
    {
        $inputData = $request->all();
        
        if ($this->vnpayService->validateResponse($inputData)) {
            $orderId = $inputData['vnp_TxnRef'];
            $order = Order::find($orderId);
            
            if ($order) {
                if ($inputData['vnp_ResponseCode'] == '00') {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'vnpay',
                        'transaction_id' => $inputData['vnp_TransactionNo']
                    ]);
                    
                    return redirect(env('FRONTEND_URL') . '/payment/success?order_id=' . $orderId);
                } else {
                    $order->update(['payment_status' => 'failed']);
                    return redirect(env('FRONTEND_URL') . '/payment/failed?order_id=' . $orderId);
                }
            }
        }
        
        return redirect(env('FRONTEND_URL') . '/payment/error');
    }
}