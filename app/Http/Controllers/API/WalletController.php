<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function getWallet()
    {
        $user = auth('sanctum')->user();
        $wallet = $user->getOrCreateWallet();
        
        return response()->json([
            'message' => 'Thông tin ví',
            'data' => [
                'balance' => $wallet->balance,
                'formatted_balance' => number_format($wallet->balance) . ' VND'
            ]
        ]);
    }

    public function getTransactions(Request $request)
    {
        $user = auth('sanctum')->user();
        
        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return response()->json([
            'message' => 'Lịch sử giao dịch ví',
            'data' => $transactions
        ]);
    }
}