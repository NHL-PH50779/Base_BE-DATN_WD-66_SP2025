<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    public function index($productId)
    {
        $variants = ProductVariant::where('product_id', $productId)->get();
        
        return response()->json([
            'message' => 'Danh sách biến thể',
            'data' => $variants
        ]);
    }

    public function store(Request $request, $productId)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:product_variants,sku',
            'Name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'quantity' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $product = Product::findOrFail($productId);

        $variant = $product->variants()->create([
            'sku' => $validated['sku'],
            'Name' => $validated['Name'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'quantity' => $validated['quantity'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Tạo biến thể thành công',
            'data' => $variant
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $variant = ProductVariant::findOrFail($id);

        $validated = $request->validate([
            'sku' => 'required|string|unique:product_variants,sku,' . $id,
            'Name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'quantity' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $variant->update($validated);

        return response()->json([
            'message' => 'Cập nhật biến thể thành công',
            'data' => $variant
        ]);
    }

    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->delete();

        return response()->json([
            'message' => 'Xóa biến thể thành công'
        ]);
    }
}
