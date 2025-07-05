<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CancelRequest;
use App\Models\Order;
use Illuminate\Http\Request;

class CancelRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string|min:10'
        ]);

        $order = Order::find($request->order_id);
        
        // Kiểm tra đơn hàng có thể hủy không
        if ($order->order_status_id != 2 || $order->payment_method != 'vnpay') {
            return response()->json(['error' => 'Đơn hàng không thể hủy'], 400);
        }

        // Kiểm tra đã có yêu cầu hủy chưa
        $existingRequest = CancelRequest::where('order_id', $request->order_id)
            ->where('status', 'pending')
            ->first();
            
        if ($existingRequest) {
            return response()->json(['error' => 'Đã có yêu cầu hủy đang chờ duyệt'], 400);
        }

        $cancelRequest = CancelRequest::create([
            'order_id' => $request->order_id,
            'user_id' => auth()->id(),
            'reason' => $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu hủy đơn hàng đã được gửi, chờ admin duyệt',
            'cancel_request' => $cancelRequest
        ]);
    }

    public function adminIndex()
    {
        $requests = CancelRequest::with(['order', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($requests);
    }

    public function adminUpdate(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string'
        ]);

        $cancelRequest = CancelRequest::findOrFail($id);
        
        $cancelRequest->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note,
            'approved_at' => $request->status === 'approved' ? now() : null
        ]);

        // Nếu được duyệt, hủy đơn hàng
        if ($request->status === 'approved') {
            $cancelRequest->order->update(['order_status_id' => 6]); // Đã hủy
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật yêu cầu hủy đơn hàng'
        ]);
    }
}