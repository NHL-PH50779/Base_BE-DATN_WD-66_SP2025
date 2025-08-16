<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount(['products' => function ($query) {
            $query->where('is_active', true);
        }])
            ->orderBy('name')
            ->get();
        
        return response()->json([
            'message' => 'Danh sách danh mục',
            'data' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Thêm danh mục thành công',
            'data' => $category
        ], 201);
    }

    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Không tìm thấy danh mục'
            ], 404);
        }
        
        // Đếm sản phẩm active
        $category->products_count = $category->products()->where('is_active', true)->count();

        return response()->json([
            'message' => 'Chi tiết danh mục',
            'data' => $category
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Không tìm thấy danh mục'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name,' . $id
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update([
            'name' => $request->name
        ]);

        return response()->json([
            'message' => 'Cập nhật danh mục thành công',
            'data' => $category
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Không tìm thấy danh mục'
            ], 404);
        }

        // Không cho phép xóa danh mục "Không xác định"
        if ($category->name === 'Không xác định') {
            return response()->json([
                'message' => 'Không thể xóa danh mục "Không xác định"'
            ], 400);
        }

        try {
            \DB::beginTransaction();
            
            // Tạo hoặc lấy danh mục "Không xác định"
            $uncategorized = Category::firstOrCreate(
                ['name' => 'Không xác định'],
                ['name' => 'Không xác định']
            );

            // Chuyển tất cả sản phẩm sang danh mục "Không xác định"
            \DB::table('products')
                ->where('category_id', $id)
                ->update(['category_id' => $uncategorized->id]);

            $category->delete();
            
            \DB::commit();

            return response()->json([
                'message' => 'Xóa danh mục thành công. Các sản phẩm đã được chuyển sang "Không xác định"'
            ]);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json([
                'message' => 'Lỗi khi xóa danh mục: ' . $e->getMessage()
            ], 500);
        }
    }
}
