<?php

namespace App\Http\Controllers\Api;

use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ReturnRequestController extends Controller
{
    // Danh sách yêu cầu hoàn hàng
    public function index()
    {
        return response()->json(ReturnRequest::all(), 200);
    }

    // Người dùng gửi yêu cầu hoàn hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string',
            'status' => 'in:pending',
        ]);

        $returnRequest = ReturnRequest::create($validated);

        return response()->json([
            'message' => 'Đã gửi yêu cầu hoàn hàng',
            'data' => $returnRequest
        ], 201);
    }

    // Xem chi tiết
    public function show($id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);
        return response()->json($returnRequest);
    }

    // Cập nhật yêu cầu (không khuyến nghị cho user tự update)
    public function update(Request $request, $id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'order_id' => 'sometimes|exists:orders,id',
            'reason' => 'nullable|string',
            'status' => 'sometimes|string',
        ]);

        $returnRequest->update($data);

        return response()->json($returnRequest);
    }

    // Xóa yêu cầu
    public function destroy($id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);
        $returnRequest->delete();

        return response()->json(null, 204);
    }

    // ✅ Admin duyệt hoàn hàng ➝ cộng tiền vào ví
    public function approve($id)
    {
        $request = ReturnRequest::findOrFail($id);

        if ($request->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu đã xử lý.'], 400);
        }

        $order = $request->order;
        $user = $order->user;
        $refundAmount = $order->total;

        // Cộng tiền vào ví
        $user->increment('balance', $refundAmount);

        // Ghi log ví
        WalletLog::create([
            'user_id' => $user->id,
            'amount' => $refundAmount,
            'type' => '+',
            'reason' => 'Hoàn hàng đơn #' . $order->id,
        ]);

        // Đánh dấu return request đã duyệt
        $request->status = 'approved';
        $request->save();

        return response()->json(['message' => 'Đã hoàn tiền vào ví.']);
    }
     public function reject($id)
{
    $request = ReturnRequest::findOrFail($id);

    if ($request->status !== 'pending') {
        return response()->json(['message' => 'Yêu cầu đã được xử lý.'], 400);
    }

    $request->status = 'rejected';
    $request->save();

    return response()->json(['message' => 'Yêu cầu hoàn hàng đã bị từ chối.']);
}

}
