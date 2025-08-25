<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class TestController extends Controller
{
    // Test hoàn tiền VNPay
    public function testVNPayRefund(Request $request, $orderId)
    {
        $order = Order::with('user.wallet')->findOrFail($orderId);
        
        if (!$order->user) {
            return response()->json(['error' => 'Order has no user'], 400);
        }
        
        $wallet = $order->user->wallet;
        if (!$wallet) {
            $wallet = $order->user->wallet()->create(['balance' => 0]);
        }
        
        // Kiểm tra đã hoàn tiền chưa
        $alreadyRefunded = WalletTransaction::where('reference_id', $order->id)
            ->where('reference_type', 'order')
            ->where('type', 'credit')
            ->where('description', 'LIKE', '%Hoàn tiền%')
            ->exists();
            
        if ($alreadyRefunded) {
            return response()->json([
                'message' => 'Already refunded',
                'transactions' => WalletTransaction::where('reference_id', $order->id)->get()
            ]);
        }
        
        // Thực hiện hoàn tiền
        $balanceBefore = $wallet->balance;
        
        $wallet->addMoney(
            $order->total,
            'Test hoàn tiền đơn #' . $order->id . ' (' . strtoupper($order->payment_method) . ')',
            'order',
            $order->id
        );
        
        $balanceAfter = $wallet->fresh()->balance;
        
        return response()->json([
            'message' => 'Refund test completed',
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'amount' => $order->total,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'transaction' => WalletTransaction::where('reference_id', $order->id)
                ->where('reference_type', 'order')
                ->latest()
                ->first()
        ]);
    }
    
    // Test tạo withdraw request
    public function testWithdrawRequest()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }
        
        $wallet = $user->wallet;
        if (!$wallet) {
            $wallet = $user->wallet()->create(['balance' => 100000]);
        }
        
        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'wallet' => [
                'balance' => $wallet->balance,
                'formatted_balance' => number_format($wallet->balance) . ' VND'
            ],
            'withdraw_requests' => $user->withdrawRequests()->latest()->take(5)->get()
        ]);
    }
}