<?php

namespace App\Http\Controllers\API;

use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ReturnRequestController extends Controller
{
    // Danh sách yêu cầu hoàn hàng
    public function index()
    {
        $user = auth('sanctum')->user();
        
        if ($user && in_array($user->role, ['admin', 'super_admin'])) {
            // Admin xem tất cả
            $requests = ReturnRequest::with(['user', 'order'])->latest()->get();
        } else {
            // User chỉ xem của mình
            $requests = ReturnRequest::with('order')
                ->where('user_id', $user->id)
                ->latest()->get();
        }
        
        return response()->json([
            'message' => 'Danh sách yêu cầu hoàn hàng',
            'data' => $requests
        ]);
    }

    // Người dùng gửi yêu cầu hoàn hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string|max:500',
        ]);
        
        $user = auth('sanctum')->user();
        
        // Kiểm tra order thuộc về user
        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();
            
        // Kiểm tra trạng thái order
        if (!in_array($order->order_status_id, [4, 5])) { // Delivered hoặc Completed
            return response()->json([
                'message' => 'Chỉ có thể yêu cầu hoàn hàng cho đơn đã giao hoặc hoàn thành'
            ], 400);
        }
        
        // Kiểm tra đã có yêu cầu chưa
        $existingRequest = ReturnRequest::where('order_id', $order->id)->first();
        if ($existingRequest) {
            return response()->json([
                'message' => 'Đơn hàng này đã có yêu cầu hoàn hàng'
            ], 400);
        }
        
        $returnRequest = ReturnRequest::create([
            'user_id' => $user->id,
            'order_id' => $validated['order_id'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);
        
        return response()->json([
            'message' => 'Đã gửi yêu cầu hoàn hàng',
            'data' => $returnRequest->load('order')
        ], 201);
    }

    // Xem chi tiết
    public function show($id)
    {
        $user = auth('sanctum')->user();
        $returnRequest = ReturnRequest::with(['user', 'order'])->findOrFail($id);
        
        // Kiểm tra quyền xem
        if ($user->role !== 'admin' && $returnRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }
        
        return response()->json([
            'message' => 'Chi tiết yêu cầu hoàn hàng',
            'data' => $returnRequest
        ]);
    }

    // Admin duyệt hoàn hàng
    public function approve($id)
    {
        $user = auth('sanctum')->user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Không có quyền admin'], 403);
        }
        
        $returnRequest = ReturnRequest::with(['order.user'])->findOrFail($id);
        
        if ($returnRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu đã được xử lý'], 400);
        }
        
        $order = $returnRequest->order;
        $orderUser = $order->user;
        $refundAmount = $order->total;
        
        // Sử dụng hệ thống wallet hiện có
        $wallet = $orderUser->wallet;
        if (!$wallet) {
            $wallet = $orderUser->wallet()->create(['balance' => 0]);
        }
        
        // Cộng tiền vào ví
        $wallet->addMoney(
            $refundAmount,
            'Hoàn tiền hoàn hàng đơn #' . $order->id,
            'return_request',
            $returnRequest->id
        );
        
        // Cập nhật trạng thái
        $returnRequest->update(['status' => 'approved']);
        $order->update(['order_status_id' => 8]); // Return approved
        
        return response()->json([
            'message' => 'Đã duyệt hoàn hàng và hoàn tiền vào ví',
            'data' => $returnRequest
        ]);
    }

    // Admin từ chối hoàn hàng
    public function reject(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Không có quyền admin'], 403);
        }
        
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500'
        ]);
        
        $returnRequest = ReturnRequest::findOrFail($id);
        
        if ($returnRequest->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu đã được xử lý'], 400);
        }
        
        $returnRequest->update([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? 'Admin từ chối yêu cầu hoàn hàng'
        ]);
        
        return response()->json([
            'message' => 'Đã từ chối yêu cầu hoàn hàng',
            'data' => $returnRequest
        ]);
    }
}