<?php

namespace App\Http\Controllers\API;

use App\Models\ReturnRequest;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ReturnRequestController extends Controller
{
    
    public function index()
    {
        $returnRequests = ReturnRequest::with(['user', 'order'])->get();
        return response()->json([
            'message' => 'Danh sách yêu cầu hoàn hàng',
            'data' => $returnRequests
        ]);
    }

    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string',
        ]);

        $validated['status'] = 'pending';
        $returnRequest = ReturnRequest::create($validated);

        // Tạo thông báo cho admin
        Notification::create([
            'type' => 'return_request',
            'title' => 'Yêu cầu hoàn hàng mới',
            'message' => "Khách hàng đã yêu cầu hoàn hàng cho đơn #{$validated['order_id']}",
            'data' => [
                'return_request_id' => $returnRequest->id,
                'order_id' => $validated['order_id'],
                'user_id' => $validated['user_id']
            ]
        ]);

        return response()->json([
            'message' => 'Yêu cầu hoàn hàng đã được gửi',
            'data' => $returnRequest
        ], 201);
    }


    
    public function show($id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);
        return $returnRequest;
    }

    
    public function update(Request $request, $id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $returnRequest->update($data);

        // Nếu admin xác nhận hoàn hàng, cập nhật trạng thái đơn hàng
        if ($data['status'] === 'approved') {
            $order = Order::find($returnRequest->order_id);
            if ($order) {
                $order->update(['order_status_id' => 6]); // 6 = cancelled
            }

            // Tạo thông báo cho khách hàng
            Notification::create([
                'type' => 'return_approved',
                'title' => 'Yêu cầu hoàn hàng được chấp nhận',
                'message' => "Yêu cầu hoàn hàng cho đơn #{$returnRequest->order_id} đã được chấp nhận. Đơn hàng đã bị hủy.",
                'user_id' => $returnRequest->user_id,
                'data' => [
                    'order_id' => $returnRequest->order_id,
                    'return_request_id' => $returnRequest->id
                ]
            ]);
        }

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'data' => $returnRequest
        ], 200);
    }

   
    public function destroy($id)
    {
        $returnRequest = ReturnRequest::findOrFail($id);
        $returnRequest->delete();

        return response()->json(null, 204);
    }
}

