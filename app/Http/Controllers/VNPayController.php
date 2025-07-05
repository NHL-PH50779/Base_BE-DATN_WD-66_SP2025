<?php

namespace App\Http\Controllers;

use App\Services\VNPayService;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    protected $vnpayService;

    public function __construct(VNPayService $vnpayService)
    {
        $this->vnpayService = $vnpayService;
    }

    public function createPayment(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer',
                'amount' => 'required|numeric|min:1000'
            ]);

            $orderId = $request->order_id;
            $amount = $request->amount;
            $orderInfo = $request->order_info ?? 'Thanh toán đơn hàng #' . $orderId;

            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng không tồn tại'
                ], 404);
            }

            if ($order->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng đã được thanh toán'
                ], 400);
            }

            $paymentUrl = $this->vnpayService->createPaymentUrl($orderId, $amount, $orderInfo);

            return response()->json([
                'success' => true,
                'payment_url' => $paymentUrl,
                'order_id' => $orderId
            ]);
        } catch (\Exception $e) {
            Log::error('VNPay create payment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi tạo thanh toán: ' . $e->getMessage()
            ], 500);
        }
    }

    public function vnpayReturn(Request $request)
    {
        try {
            $inputData = $request->all();
            Log::info('VNPay return data: ', $inputData);
            
            if ($this->vnpayService->validateResponse($inputData)) {
                $orderId = $inputData['vnp_TxnRef'];
                $order = Order::find($orderId);
                
                if ($inputData['vnp_ResponseCode'] == '00') {
                    if ($order) {
                        $order->update([
                            'payment_status' => 'paid',
                            'vnpay_txn_ref' => $inputData['vnp_TxnRef'],
                            'vnpay_response_code' => $inputData['vnp_ResponseCode'],
                            'vnpay_transaction_no' => $inputData['vnp_TransactionNo'] ?? null,
                            'order_status_id' => 2,
                            'payment_status_id' => 2,
                            'paid_at' => now()
                        ]);
                    }
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Thanh toán thành công',
                        'data' => [
                            'order_id' => $orderId,
                            'amount' => $inputData['vnp_Amount'] / 100,
                            'transaction_id' => $inputData['vnp_TransactionNo'] ?? null
                        ]
                    ]);
                } else {
                    if ($order) {
                        $order->update([
                            'payment_status' => 'failed',
                            'vnpay_response_code' => $inputData['vnp_ResponseCode']
                        ]);
                    }
                    
                    return response()->json([
                        'success' => false,
                        'message' => $this->getVNPayMessage($inputData['vnp_ResponseCode']),
                        'data' => [
                            'order_id' => $orderId,
                            'response_code' => $inputData['vnp_ResponseCode']
                        ]
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Chữ ký không hợp lệ'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('VNPay return error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xử lý kết quả thanh toán'
            ], 500);
        }
    }

    public function vnpayIPN(Request $request)
    {
        try {
            $inputData = $request->all();
            Log::info('VNPay IPN data: ', $inputData);
            
            if ($this->vnpayService->validateResponse($inputData)) {
                $orderId = $inputData['vnp_TxnRef'];
                $order = Order::find($orderId);
                
                if ($inputData['vnp_ResponseCode'] == '00' && $order) {
                    if ($order->payment_status !== 'paid') {
                        $order->update([
                            'payment_status' => 'paid',
                            'vnpay_txn_ref' => $inputData['vnp_TxnRef'],
                            'vnpay_response_code' => $inputData['vnp_ResponseCode'],
                            'vnpay_transaction_no' => $inputData['vnp_TransactionNo'] ?? null,
                            'order_status_id' => 2,
                            'payment_status_id' => 2,
                            'paid_at' => now()
                        ]);
                    }
                    return response()->json(['RspCode' => '00', 'Message' => 'success']);
                }
            }
            return response()->json(['RspCode' => '97', 'Message' => 'Fail checksum']);
        } catch (\Exception $e) {
            Log::error('VNPay IPN error: ' . $e->getMessage());
            return response()->json(['RspCode' => '99', 'Message' => 'Unknown error']);
        }
    }

    private function getVNPayMessage($responseCode)
    {
        $messages = [
            '00' => 'Giao dịch thành công',
            '24' => 'Khách hàng hủy giao dịch',
            '51' => 'Tài khoản không đủ số dư',
            '65' => 'Vượt quá hạn mức giao dịch',
            '75' => 'Ngân hàng đang bảo trì',
            '99' => 'Lỗi không xác định'
        ];
        
        return $messages[$responseCode] ?? 'Lỗi không xác định';
    }
}