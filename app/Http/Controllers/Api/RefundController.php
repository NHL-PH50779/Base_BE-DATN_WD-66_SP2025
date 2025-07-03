<?php

namespace App\Http\Controllers\Api;

use App\Models\Refund;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RefundController extends Controller
{
    // Lấy danh sách yêu cầu rút tiền
    public function index()
    {
        return response()->json(Refund::all(), 200);
    }

    // Người dùng tạo yêu cầu rút tiền
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount'   => 'required|numeric|min:1000',
            'status'   => 'in:pending', // Chỉ cho phép khởi tạo ở trạng thái pending
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $user = $order->user;

        // Kiểm tra số dư
        if ($user->balance < $validated['amount']) {
            return response()->json(['message' => 'Số dư không đủ'], 400);
        }

        $refund = Refund::create([
            'order_id' => $order->id,
            'amount'   => $validated['amount'],
            'status'   => 'pending',
        ]);

        return response()->json([
            'message' => 'Đã tạo yêu cầu rút tiền',
            'data' => $refund
        ], 201);
    }

    // Admin duyệt yêu cầu rút tiền
    public function approve($id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu rút tiền'], 404);
        }

        if ($refund->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu đã được xử lý'], 400);
        }

        $user = $refund->order->user;

        // Kiểm tra lại số dư
        if ($user->balance < $refund->amount) {
            return response()->json(['message' => 'Số dư không đủ'], 400);
        }

        // Trừ tiền khỏi ví
        $user->decrement('balance', $refund->amount);

        // Ghi log ví
        WalletLog::create([
            'user_id' => $user->id,
            'amount' => $refund->amount,
            'type' => '-',
            'reason' => 'Rút về ngân hàng',
        ]);

        // Đánh dấu đã duyệt
        $refund->status = 'approved';
        $refund->save();

        return response()->json(['message' => 'Đã duyệt yêu cầu rút tiền và trừ tiền khỏi ví']);
    }

    // Xem chi tiết yêu cầu rút
    public function show($id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        return response()->json($refund, 200);
    }

    // Cập nhật (không nên cho user update status)
    public function update(Request $request, $id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:pending,approved,rejected',
        ]);

        $refund->update($data);

        return response()->json($refund, 200);
    }

    // Xóa yêu cầu
    public function destroy($id)
    {
        $refund = Refund::find($id);

        if (!$refund) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $refund->delete();

        return response()->json(['message' => 'Đã xóa'], 200);
    }
}
