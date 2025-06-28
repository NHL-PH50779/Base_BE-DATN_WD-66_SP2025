<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('products')
            ->orderBy('name')
            ->get();
        
        return response()->json([
            'message' => 'Danh sách thương hiệu',
            'data' => $brands
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:brands,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $brand = Brand::create([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Thêm thương hiệu thành công',
            'data' => $brand
        ], 201);
    }

    public function show($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Không tìm thấy thương hiệu'
            ], 404);
        }

        return response()->json([
            'message' => 'Chi tiết thương hiệu',
            'data' => $brand
        ]);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Không tìm thấy thương hiệu'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:brands,name,' . $id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $brand->update([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Cập nhật thương hiệu thành công',
            'data' => $brand
        ]);
    }

    public function destroy($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'message' => 'Không tìm thấy thương hiệu'
            ], 404);
        }

        // Kiểm tra xem có sản phẩm nào đang sử dụng thương hiệu này không
        if ($brand->products()->count() > 0) {
            return response()->json([
                'message' => 'Không thể xóa thương hiệu đang được sử dụng'
            ], 400);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Xóa thương hiệu thành công'
        ]);
    }
}