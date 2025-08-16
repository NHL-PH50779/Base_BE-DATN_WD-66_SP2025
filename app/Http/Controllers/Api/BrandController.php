<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    public function index()
    {
        $brands = \Cache::remember('brands', 600, function () {
            return Brand::withCount(['products' => function ($query) {
                $query->where('is_active', true);
            }])
                ->orderBy('name')
                ->get();
        });
        
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
        
        // Clear cache sau khi thêm
        \Cache::forget('brands');
        cache()->flush();

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
        
        // Đếm sản phẩm active
        $brand->products_count = $brand->products()->where('is_active', true)->count();

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
        
        // Clear cache sau khi cập nhật
        \Cache::forget('brands');
        cache()->flush();

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

        // Không cho phép xóa thương hiệu "Không xác định"
        if ($brand->name === 'Không xác định') {
            return response()->json([
                'message' => 'Không thể xóa thương hiệu "Không xác định"'
            ], 400);
        }

        try {
            \DB::beginTransaction();
            
            // Tạo hoặc lấy thương hiệu "Không xác định"
            $unbranded = Brand::firstOrCreate(
                ['name' => 'Không xác định'],
                ['name' => 'Không xác định']
            );

            // Chuyển tất cả sản phẩm sang thương hiệu "Không xác định"
            \DB::table('products')
                ->where('brand_id', $id)
                ->update(['brand_id' => $unbranded->id]);

            $brand->delete();
            
            // Clear cache sau khi xóa
            \Cache::forget('brands');
            cache()->flush();
            
            \DB::commit();

            return response()->json([
                'message' => 'Xóa thương hiệu thành công. Các sản phẩm đã được chuyển sang "Không xác định"'
            ]);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json([
                'message' => 'Lỗi khi xóa thương hiệu: ' . $e->getMessage()
            ], 500);
        }
    }
}
