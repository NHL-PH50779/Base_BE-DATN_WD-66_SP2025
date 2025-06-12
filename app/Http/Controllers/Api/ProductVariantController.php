<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeValue;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    // Lấy danh sách biến thể của sản phẩm
    public function index($productId)
    {
        return ProductVariant::with('attributeValues')->where('product_id', $productId)->get();
    }

    // Tạo biến thể mới
    public function store(Request $request, $productId)
    {
        $data = $request->validate([
            'sku' => 'required|unique:product_variants,sku',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'attribute_value_ids' => 'required|array'
        ]);

        $variant = ProductVariant::create([
            'product_id' => $productId,
            'sku' => $data['sku'],
            'price' => $data['price'],
            'stock' => $data['stock']
        ]);

        $variant->attributeValues()->attach($data['attribute_value_ids']);

        return response()->json(['message' => 'Variant created', 'variant' => $variant->load('attributeValues')]);
    }

    // Xóa biến thể
    public function destroy($id)
    {
        $variant = ProductVariant::findOrFail($id);
        $variant->attributeValues()->detach();
        $variant->delete();

        return response()->json(['message' => 'Variant deleted']);
    }
}
