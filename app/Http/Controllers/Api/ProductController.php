<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    // Lấy danh sách tất cả sản phẩm cùng biến thể
   public function index()
{
    $products = Product::with([
        'variants.attributeValues.attribute'
    ])->get();

    return response()->json([
        'message' => 'Danh sách sản phẩm',
        'data' => $products
    ]);
}


    // Tìm kiếm sản phẩm theo từ khóa
    public function search(Request $request)
    {
        $keyword = $request->input('keyword');

        if (!$keyword) {
            return response()->json([
                'message' => 'Thiếu từ khóa tìm kiếm',
                'data' => []
            ], 400);
        }

        $products = Product::with('variants')
            ->where('name', 'like', "%{$keyword}%")
            ->get();

        return response()->json([
            'message' => 'Kết quả tìm kiếm',
            'data' => $products
        ]);
    }

    // Tạo mới sản phẩm
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'description' => 'nullable',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'thumbnail' => 'nullable|url',
            'variants' => 'nullable|array',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create($request->only([
            'name', 'description', 'brand_id', 'category_id', 'thumbnail'
        ]));

        if ($request->has('variants')) {
            foreach ($request->input('variants') as $variant) {
                $product->variants()->create($variant);
            }
        }

        return response()->json([
            'message' => 'Tạo sản phẩm thành công',
            'data' => $product->load('variants')
        ], 201);
    }

    // Cập nhật sản phẩm
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'brand_id' => 'sometimes|required|exists:brands,id',
            'category_id' => 'sometimes|required|exists:categories,id',
            'thumbnail' => 'nullable|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        if (empty($validated)) {
            return response()->json([
                'message' => 'Không có dữ liệu nào được gửi để cập nhật.'
            ], 400);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Cập nhật sản phẩm thành công!',
            'data' => $product
        ]);
    }

    // Xem chi tiết sản phẩm (kèm biến thể)
    public function show($id)
{
    $product = Product::withTrashed()
        ->with(['variants.attributeValues.attribute'])
        ->find($id);

    if (!$product) {
        return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
    }

    return response()->json([
        'message' => 'Chi tiết sản phẩm',
        'data' => $product
    ]);
}

    // Xóa mềm sản phẩm
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Xóa (mềm) sản phẩm thành công']);
    }


    // Khôi phục sản phẩm
    public function restore($id)
    {
        $product = Product::onlyTrashed()->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm đã xóa'], 404);
        }

        $product->restore();

        return response()->json([
            'message' => 'Khôi phục sản phẩm thành công',
            'data' => $product
        ]);
    }

    // Bật / tắt trạng thái sản phẩm
    public function toggleActive($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'message' => $product->is_active ? 'Đã bật sản phẩm' : 'Đã tắt sản phẩm',
            'data' => $product
        ]);
    }

// Xem danh sách sản phẩm đã xóa (có nền trạng thái is_deleted = true)
public function trashed()
{
    $products = Product::onlyTrashed()->with('variants')->get();

    return response()->json([
        'message' => 'Danh sách sản phẩm đã xóa',
        'data' => $products
    ]);
}

}