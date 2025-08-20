<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WithdrawRequest;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawRequestController extends Controller
{
    // Lấy danh sách yêu cầu rút tiền
    public function index()
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        try {
            if (in_array($user->role, ['admin', 'super_admin'])) {
                // Admin xem tất cả
                $requests = WithdrawRequest::with(['user' => function($query) {
                    $query->select('id', 'name', 'email');
                }])->latest()->get();
            } else {
                // User chỉ xem của mình
                $requests = WithdrawRequest::where('user_id', $user->id)->latest()->get();
            }
        } catch (\Exception $e) {
            \Log::error('WithdrawRequest index error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Có lỗi xảy ra khi tải danh sách',
                'error' => $e->getMessage()
            ], 500);
        }
        
        return response()->json([
            'message' => 'Danh sách yêu cầu rút tiền',
            'data' => $requests
        ]);
    }

    // Tạo yêu cầu rút tiền mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:50000|max:50000000', // Tối thiểu 50k, tối đa 50M
            'bank_name' => 'required|string|in:VCB,TCB,MB,ACB,VTB,BIDV,CTG,EIB,TPB,STB',
            'account_number' => 'required|string|min:6|max:20',
            'account_name' => 'required|string|max:100',
        ]);

        $user = Auth::user();
        $wallet = $user->wallet;
        
        if (!$wallet) {
            return response()->json(['message' => 'Chưa có ví điện tử'], 400);
        }

        // Kiểm tra số dư
        if ($wallet->balance < $validated['amount']) {
            return response()->json([
                'message' => 'Số dư không đủ. Số dư hiện tại: ' . number_format($wallet->balance) . ' VNĐ'
            ], 400);
        }
        
        // Kiểm tra có yêu cầu pending không
        $pendingRequest = WithdrawRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
            
        if ($pendingRequest) {
            return response()->json([
                'message' => 'Bạn có yêu cầu rút tiền đang chờ xử lý'
            ], 400);
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
            'message' => 'Yêu cầu rút tiền đã được tạo, vui lòng chờ admin duyệt',
            'data' => $withdraw
        ], 201);
    }

    // Xem chi tiết yêu cầu
    public function show($id)
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        try {
            $withdraw = WithdrawRequest::with(['user' => function($query) {
                $query->select('id', 'name', 'email');
            }])->findOrFail($id);
            
            // Kiểm tra quyền xem
            if (!in_array($user->role, ['admin', 'super_admin']) && $withdraw->user_id !== $user->id) {
                return response()->json(['message' => 'Không có quyền truy cập'], 403);
            }
            
            return response()->json([
                'message' => 'Chi tiết yêu cầu rút tiền',
                'data' => $withdraw
            ]);
        } catch (\Exception $e) {
            \Log::error('WithdrawRequest show error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Không tìm thấy yêu cầu rút tiền',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Admin duyệt yêu cầu rút tiền
    public function approve($id)
    {
        $user = auth('sanctum')->user();
        if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Không có quyền admin'], 403);
        }
        
        try {
            $withdraw = WithdrawRequest::with([
                'user' => function($query) {
                    $query->select('id', 'name', 'email');
                },
                'user.wallet'
            ])->findOrFail($id);

            if ($withdraw->status !== 'pending') {
                return response()->json(['message' => 'Yêu cầu đã được xử lý'], 400);
            }

            $withdrawUser = $withdraw->user;
            $wallet = $withdrawUser->wallet;
            
            if (!$wallet || $wallet->balance < $withdraw->amount) {
                return response()->json([
                    'message' => 'Số dư không đủ để duyệt rút tiền'
                ], 400);
            }

            DB::beginTransaction();
            try {
                // Trừ tiền khỏi ví
                $wallet->subtractMoney(
                    $withdraw->amount,
                    'Rút tiền về ngân hàng ' . $withdraw->bank_name . ' - ' . $withdraw->account_number,
                    'withdraw_request',
                    $withdraw->id
                );

                // Cập nhật trạng thái
                $withdraw->update([
                    'status' => 'approved',
                    'processed_at' => now(),
                    'processed_by' => $user->id
                ]);
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Đã duyệt yêu cầu rút tiền và trừ tiền khỏi ví',
                    'data' => $withdraw
                ]);
                
            } catch (\Exception $e) {
                DB::rollback();
                \Log::error('WithdrawRequest approve transaction error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('WithdrawRequest approve error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Không tìm thấy yêu cầu rút tiền',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Admin từ chối yêu cầu rút tiền
    public function reject(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Không có quyền admin'], 403);
        }
        
        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500'
        ]);
        
        $withdraw = WithdrawRequest::findOrFail($id);

        if ($withdraw->status !== 'pending') {
            return response()->json(['message' => 'Yêu cầu đã được xử lý'], 400);
        }

        $withdraw->update([
            'status' => 'rejected',
            'admin_note' => $validated['admin_note'] ?? 'Admin từ chối yêu cầu rút tiền',
            'processed_at' => now(),
            'processed_by' => $user->id
        ]);

        return response()->json([
            'message' => 'Đã từ chối yêu cầu rút tiền',
            'data' => $withdraw
        ]);
    }
}