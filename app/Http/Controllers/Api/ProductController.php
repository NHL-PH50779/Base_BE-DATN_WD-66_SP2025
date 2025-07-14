<?php

namespace App\Http\Controllers\API;


use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    // Lấy danh sách tất cả sản phẩm cùng biến thể
   public function index(Request $request)
{
    // Cache key dựa trên parameters
    $cacheKey = 'products_' . md5(serialize($request->all()));
    
    return cache()->remember($cacheKey, 300, function () use ($request) {
        $query = Product::with([
            'variants' => function($q) {
                $q->select('id', 'product_id', 'price', 'stock', 'is_active')
                  ->where('is_active', true)
                  ->orderBy('price')
                  ->limit(1);
            },
            'brand:id,name', 
            'category:id,name'
        ])->select('id', 'name', 'brand_id', 'category_id', 'thumbnail', 'is_active');
        
        // Filters
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        // Permissions
        if (!($request->has('brand_id') || $request->has('category_id'))) {
            if (auth()->check() && in_array(auth()->user()->role, ['admin', 'super_admin'])) {
                $query->withTrashed();
            } else {
                $query->where('is_active', true);
            }
        } else {
            $query->where('is_active', true);
        }
        
        $products = $query->limit(50)->get();
        
        // Add price from first variant
        $products->each(function ($product) {
            $product->price = $product->variants->first()->price ?? 0;
        });

        return response()->json([
            'message' => 'Danh sách sản phẩm',
            'data' => $products
        ]);
    });
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
        'thumbnail' => 'nullable|string',
        'is_active' => 'boolean',

        'variants' => 'array',
        'variants.*.sku' => 'required|string|unique:product_variants,sku',
        'variants.*.Name' => 'required|string|max:255',
        'variants.*.price' => 'required|numeric|min:0',
        'variants.*.stock' => 'required|integer|min:0',
        'variants.*.quantity' => 'integer|min:0',
        'variants.*.is_active' => 'boolean',
    ]);

    // Create Product
    $product = Product::create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'brand_id' => $validated['brand_id'],
        'category_id' => $validated['category_id'],
        'thumbnail' => $validated['thumbnail'] ?? null,
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
                'quantity' => $variantData['quantity'] ?? 0,
                'is_active' => $variantData['is_active'] ?? true,
            ]);
        }
    }

    return response()->json($product->load('variants'), 201);
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
        'thumbnail' => 'nullable|string',
        'is_active' => 'sometimes|boolean',
        
        // Thêm validation cho variants
        'variants' => 'array',
        'variants.*.sku' => 'required|string',
        'variants.*.Name' => 'required|string|max:255',
        'variants.*.price' => 'required|numeric|min:0',
        'variants.*.stock' => 'required|integer|min:0',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Dữ liệu không hợp lệ',
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $validator->validated();

    // Nếu có ảnh mới (URL), cập nhật
    if ($request->has('thumbnail') && $request->thumbnail) {
        $data['thumbnail'] = $request->thumbnail;
    }

    // Cập nhật thông tin sản phẩm
    $product->update($data);

    // Cập nhật variants nếu có
    if ($request->has('variants') && is_array($request->variants)) {
        foreach ($request->variants as $variantData) {
            if (isset($variantData['id'])) {
                // Cập nhật variant hiện có
                $variant = $product->variants()->find($variantData['id']);
                if ($variant) {
                    $variant->update([
                        'sku' => $variantData['sku'],
                        'Name' => $variantData['Name'],
                        'price' => $variantData['price'],
                        'stock' => $variantData['stock'],
                    ]);
                }
            }
        }
    }

    return response()->json([
        'message' => 'Cập nhật sản phẩm thành công!',
        'data' => $product->load('variants')
    ]);
}


    // Xem chi tiết sản phẩm (kèm biến thể)
    public function show($id)
{
    $product = Product::withTrashed()
        ->with(['variants:id,product_id,sku,Name,price,stock,quantity,is_active', 'brand:id,name', 'category:id,name'])
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

// Xem danh sách sản phẩm đã xóa
public function trashed()
{
    $products = Product::onlyTrashed()
        ->with(['variants', 'brand', 'category'])
        ->get();

    return response()->json([
        'message' => 'Danh sách sản phẩm đã xóa',
        'data' => $products
    ]);
}

// Xóa vĩnh viễn sản phẩm
public function forceDelete($id)
{
    $product = Product::onlyTrashed()->find($id);

    if (!$product) {
        return response()->json(['message' => 'Không tìm thấy sản phẩm đã xóa'], 404);
    }

    $product->forceDelete();

    return response()->json(['message' => 'Xóa vĩnh viễn sản phẩm thành công']);
}

public function getByBrand($brandId)
{
    $products = Product::with(['brand', 'category', 'variants:id,product_id,price,stock,quantity,is_active'])
        ->where('brand_id', $brandId)
        ->where('is_active', true)
        ->get();
        
    $products->each(function ($product) {
        if ($product->variants && $product->variants->count() > 0) {
            $product->price = $product->variants->first()->price;
        } else {
            $product->price = 0;
        }
    });

    return response()->json([
        'message' => 'Sản phẩm theo thương hiệu',
        'data' => $products
    ]);
}

public function getByCategory($categoryId)
{
    $products = Product::with(['brand', 'category', 'variants:id,product_id,price,stock,quantity,is_active'])
        ->where('category_id', $categoryId)
        ->where('is_active', true)
        ->get();
        
    $products->each(function ($product) {
        if ($product->variants && $product->variants->count() > 0) {
            $product->price = $product->variants->first()->price;
        } else {
            $product->price = 0;
        }
    });

    return response()->json([
        'message' => 'Sản phẩm theo danh mục',
        'data' => $products
    ]);
}

}