<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function getWallet()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập'
                ], 401);
            }
            
            $wallet = $user->wallet;
            
            if (!$wallet) {
                $wallet = $user->wallet()->create(['balance' => 0]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $wallet->balance,
                    'formatted_balance' => number_format($wallet->balance, 0, ',', '.') . ' VND',
                    'pending_amount' => 0, // Tiền đang chờ xử lý
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar ?? null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin ví'
            ], 500);
        }
    }

    public function getTransactions(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập'
                ], 401);
            }
            
            $wallet = $user->wallet;
            
            if (!$wallet) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $query = $wallet->transactions()
                ->orderBy('created_at', 'desc');
            
            // Lọc theo loại giao dịch
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }
            
            // Lọc theo khoảng thời gian
            if ($request->has('from_date') && $request->from_date) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date') && $request->to_date) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            
            $transactions = $query->paginate(20);
            
            // Thêm thông tin trạng thái và format
            $transactions->getCollection()->transform(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'formatted_amount' => number_format($transaction->amount, 0, ',', '.') . ' VND',
                    'description' => $transaction->description,
                    'status' => 'success', // Mặc định là thành công
                    'transaction_code' => 'TXN' . str_pad($transaction->id, 8, '0', STR_PAD_LEFT),
                    'created_at' => $transaction->created_at,
                    'formatted_date' => $transaction->created_at->format('d/m/Y H:i'),
                    'balance_before' => $transaction->balance_before,
                    'balance_after' => $transaction->balance_after,
                    'reference_type' => $transaction->reference_type,
                    'reference_id' => $transaction->reference_id
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy lịch sử giao dịch'
            ], 500);
        }
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000'
        ]);

        try {
            $user = Auth::user();
            if (!$user) {
                \Log::error('Wallet deposit: User not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng đăng nhập'
                ], 401);
            }

            \Log::info('Wallet deposit request', [
                'user_id' => $user->id,
                'amount' => $request->amount
            ]);

            // Tạo URL thanh toán VNPay cho nạp tiền
            $vnpayController = new \App\Http\Controllers\API\VNPayController();
            
            // Tạo request giả lập cho VNPay
            $vnpayRequest = new \Illuminate\Http\Request();
            $vnpayRequest->merge([
                'amount' => $request->amount,
                'order_desc' => 'Nạp tiền vào ví - User ID: ' . $user->id,
                'wallet_deposit' => true,
                'user_id' => $user->id
            ]);
            
            // Set IP address
            $vnpayRequest->server->set('REMOTE_ADDR', request()->ip());
            
            $vnpayResponse = $vnpayController->createPayment($vnpayRequest);
            $responseData = $vnpayResponse->getData(true);
            
            \Log::info('VNPay response for wallet deposit', $responseData);
            
            if ($responseData['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tạo liên kết thanh toán thành công',
                    'payment_url' => $responseData['payment_url']
                ]);
            } else {
                \Log::error('VNPay failed to create payment URL', $responseData);
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tạo liên kết thanh toán: ' . ($responseData['message'] ?? 'Unknown error')
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Wallet deposit error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo yêu cầu nạp tiền: ' . $e->getMessage()
            ], 500);
        }
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:50000',
            'bank_account' => 'required|string',
            'bank_name' => 'required|string'
        ]);

        $user = Auth::user();
        $wallet = $user->wallet;
        
        if (!$wallet || $wallet->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Số dư không đủ'
            ], 400);
        }

        // Logic rút tiền - tạo yêu cầu rút tiền
        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu rút tiền đã được gửi, xử lý trong 1-3 ngày'
        ]);
    }
}
