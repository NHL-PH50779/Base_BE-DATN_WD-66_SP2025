<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WithdrawRequest;
use App\Models\WalletLog;
use Illuminate\Support\Facades\Auth;

class WithdrawRequestController extends Controller
{
    // Lấy danh sách tất cả yêu cầu rút tiền (chỉ nên dùng cho admin)
    public function index()
    {
        return WithdrawRequest::all();
    }

    // Tạo yêu cầu rút tiền mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:10000',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
        ]);

        $user = Auth::user();

        // Kiểm tra số dư
        if ($user->balance < $validated['amount']) {
            return response()->json(['message' => 'Số dư không đủ'], 400);
        }

        // Tạo yêu cầu rút tiền
        $withdraw = WithdrawRequest::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Yêu cầu rút tiền đã được tạo',
            'data' => $withdraw
        ], 201);
    }

    // Admin duyệt yêu cầu rút tiền
    public function approve($id)
    {
        $withdraw = WithdrawRequest::findOrFail($id);

        if ($withdraw->status === 'approved') {
            return response()->json(['message' => 'Yêu cầu đã được xử lý.']);
        }

        // Trừ tiền khỏi ví
        $user = $withdraw->user;
        if ($user->balance < $withdraw->amount) {
            return response()->json(['message' => 'Số dư không đủ để duyệt rút tiền.'], 400);
        }

        $user->decrement('balance', $withdraw->amount);

        // Ghi log ví
        WalletLog::create([
            'user_id' => $user->id,
            'amount' => -$withdraw->amount,
            'type' => '-',
            'reason' => 'Rút về ngân hàng',
        ]);

        // Cập nhật trạng thái
        $withdraw->status = 'approved';
        $withdraw->save();

        return response()->json(['message' => 'Yêu cầu rút tiền đã duyệt.']);
    }
    public function reject($id)
{
    $withdraw = WithdrawRequest::findOrFail($id);

    if ($withdraw->status !== 'pending') {
        return response()->json(['message' => 'Yêu cầu đã được xử lý.'], 400);
    }

    $withdraw->status = 'rejected';
    $withdraw->save();

    return response()->json(['message' => 'Yêu cầu rút tiền đã bị từ chối.']);
}
}
