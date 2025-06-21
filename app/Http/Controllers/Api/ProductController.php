<?php

namespace App\Http\Controllers\Api;


use Illuminate\Support\Facades\Storage;

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
    // Validate input
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'brand_id' => 'required|exists:brands,id',
        'category_id' => 'required|exists:categories,id',
        'is_active' => 'boolean',

        'variants' => 'array',
        'variants.*.sku' => 'required|string|unique:product_variants,sku',
        'variants.*.Name' => 'required|string|max:255', // Uppercase N
        'variants.*.price' => 'required|numeric|min:0',
        'variants.*.stock' => 'required|integer|min:0',
        'variants.*.is_active' => 'boolean',

        // Optional: attributes inside each variant
        'variants.*.attributes' => 'array',
        'variants.*.attributes.*.value_id' => 'integer|exists:attribute_values,id',
    ]);

    // Create Product
    $product = Product::create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'brand_id' => $validated['brand_id'],
        'category_id' => $validated['category_id'],
        'is_active' => $validated['is_active'] ?? true,
    ]);

    // Create Variants
    if (!empty($validated['variants'])) {
        foreach ($validated['variants'] as $variantData) {
            $variant = $product->variants()->create([
                'sku' => $variantData['sku'],
                'Name' => $variantData['Name'],
                'price' => $variantData['price'],
                'stock' => $variantData['stock'],
                'is_active' => $variantData['is_active'] ?? true,
            ]);

            // Attach attribute values
            if (!empty($variantData['attributes'])) {
                foreach ($variantData['attributes'] as $attr) {
                    if (!empty($attr['value_id'])) {
                        $variant->attributeValues()->attach($attr['value_id']);
                    }
                }
            }
        }
    }

    return response()->json($product->load('variants.attributeValues'), 201);
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
        'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Dữ liệu không hợp lệ',
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $validator->validated();

    // Nếu có ảnh mới, upload lại
    if ($request->hasFile('thumbnail')) {
        $path = $request->file('thumbnail')->store('products', 'public');
        $data['thumbnail'] = asset('storage/' . $path);
    }

    $product->update($data);

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