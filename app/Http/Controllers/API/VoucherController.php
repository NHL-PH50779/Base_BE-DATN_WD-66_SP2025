<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    // Admin methods
    public function index()
    {
        $vouchers = Voucher::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'data' => $vouchers
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:vouchers,code|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $voucher = Voucher::create($request->all());

        return response()->json([
            'message' => 'Tạo voucher thành công',
            'data' => $voucher
        ], 201);
    }

    public function show($id)
    {
        $voucher = Voucher::findOrFail($id);
        
        return response()->json([
            'data' => $voucher
        ]);
    }

    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:vouchers,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $voucher->update($request->all());

        return response()->json([
            'message' => 'Cập nhật voucher thành công',
            'data' => $voucher
        ]);
    }

    public function destroy($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();

        return response()->json([
            'message' => 'Xóa voucher thành công'
        ]);
    }

    // Client methods
    public function validateVoucher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'order_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $voucher = Voucher::where('code', $request->code)->first();

        if (!$voucher) {
            return response()->json([
                'message' => 'Mã voucher không tồn tại'
            ], 404);
        }

        if (!$voucher->isValid()) {
            return response()->json([
                'message' => 'Mã voucher đã hết hạn hoặc không còn hiệu lực'
            ], 400);
        }

        if ($request->order_amount < $voucher->min_order_amount) {
            return response()->json([
                'message' => "Đơn hàng tối thiểu " . number_format($voucher->min_order_amount) . "đ"
            ], 400);
        }

        $discount = $voucher->calculateDiscount($request->order_amount);

        return response()->json([
            'message' => 'Áp dụng voucher thành công',
            'data' => [
                'voucher' => $voucher,
                'discount_amount' => $discount
            ]
        ]);
    }

    // Get available vouchers for order amount
    public function getAvailableVouchers(Request $request)
    {
        $orderAmount = $request->query('order_amount', 0);
        
        $vouchers = Voucher::where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('min_order_amount', '<=', $orderAmount)
            ->where('quantity', '>', 0)
            ->orderBy('value', 'desc')
            ->get();
        
        return response()->json([
            'message' => 'Danh sách voucher có thể sử dụng',
            'data' => $vouchers
        ]);
    }
}