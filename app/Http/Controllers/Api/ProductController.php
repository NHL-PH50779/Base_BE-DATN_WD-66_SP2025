<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    // Lấy danh sách tất cả sản phẩm cùng biến thể
public function index(Request $request)
{
    $perPage = $request->get('per_page', 15);
    $search = $request->get('search', '');
    $isAdmin = auth()->check() && in_array(auth()->user()->role ?? '', ['admin', 'super_admin']);
    
    // Admin route - chỉ select fields cần thiết cho admin
    if ($request->is('admin/*') || $isAdmin) {
        $query = Product::select('id', 'name', 'brand_id', 'category_id', 'thumbnail', 'is_active', 'updated_at')
            ->whereNull('deleted_at');
        
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }
        
        $products = $query->with(['brand:id,name', 'category:id,name'])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'message' => 'Danh sách sản phẩm',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total()
            ]
        ]);
    }
    
    // Client route - có variants và price
    $query = Product::select('id', 'name', 'brand_id', 'category_id', 'thumbnail', 'is_active', 'updated_at')
        ->whereNull('deleted_at');
    
    if ($search) {
        $query->where('name', 'like', '%' . $search . '%');
    }
    
    if (!$isAdmin) {
        $query->where('is_active', true);
    }
    
    $products = $query->with([
            'brand:id,name', 
            'category:id,name',
            'variants:id,product_id,price,stock,quantity,is_active'
        ])
        ->orderBy('updated_at', 'desc')
        ->paginate($perPage);
    
    // Thêm price từ variant đầu tiên
    $products->getCollection()->transform(function ($product) {
        if ($product->variants && $product->variants->count() > 0) {
            $product->price = $product->variants->first()->price;
        } else {
            $product->price = 0;
        }
        return $product;
    });
    
    return response()->json([
        'message' => 'Danh sách sản phẩm',
        'data' => $products->items(),
        'pagination' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total()
        ]
    ]);
}


    // Tìm kiếm sản phẩm theo từ khóa
    public function search(Request $request)
    {
        try {
            $keyword = $request->input('keyword', '');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');

            if (!$keyword && !$minPrice && !$maxPrice) {
                return response()->json([
                    'message' => 'Thiếu từ khóa tìm kiếm',
                    'data' => []
                ], 400);
            }

            $query = Product::with([
                    'brand:id,name', 
                    'category:id,name',
                    'variants:id,product_id,price,stock,quantity,is_active'
                ])
                ->where('is_active', true);

            // Tìm kiếm thông minh
            if ($keyword) {
                $searchTerms = $this->extractSearchTerms($keyword);
                
                $query->where(function($q) use ($searchTerms, $keyword) {
                    // Tìm theo từ khóa gốc
                    $q->where('name', 'like', "%{$keyword}%")
                      ->orWhere('description', 'like', "%{$keyword}%");
                    
                    // Tìm theo các từ khóa đã tách
                    foreach ($searchTerms as $term) {
                        $q->orWhere('name', 'like', "%{$term}%")
                          ->orWhere('description', 'like', "%{$term}%");
                    }
                    
                    // Tìm theo brand
                    $q->orWhereHas('brand', function($brandQuery) use ($searchTerms, $keyword) {
                        $brandQuery->where('name', 'like', "%{$keyword}%");
                        foreach ($searchTerms as $term) {
                            $brandQuery->orWhere('name', 'like', "%{$term}%");
                        }
                    });
                    
                    // Tìm theo category
                    $q->orWhereHas('category', function($catQuery) use ($searchTerms, $keyword) {
                        $catQuery->where('name', 'like', "%{$keyword}%");
                        foreach ($searchTerms as $term) {
                            $catQuery->orWhere('name', 'like', "%{$term}%");
                        }
                    });
                });
            }

            // Lọc theo giá
            if ($minPrice) {
                $query->where('price', '>=', $minPrice);
            }
            if ($maxPrice) {
                $query->where('price', '<=', $maxPrice);
            }

            $products = $query->orderBy('created_at', 'desc')->take(20)->get();
            
            // Thêm price từ variant đầu tiên và xử lý ảnh
            $products->transform(function ($product) {
                if ($product->variants && $product->variants->count() > 0) {
                    $product->price = $product->variants->first()->price;
                } else {
                    $product->price = 0;
                }
                
                // Xử lý ảnh thumbnail
                if (!$product->thumbnail || $product->thumbnail === 'No image') {
                    $product->thumbnail = 'http://127.0.0.1:8000/placeholder.svg';
                }
                
                return $product;
            });

            $message = 'Tìm thấy ' . $products->count() . ' sản phẩm';
            if ($keyword) {
                if (stripos($keyword, 'laptop') !== false && stripos($keyword, 'chơi game') !== false) {
                    $message = 'Tìm thấy ' . $products->count() . ' sản phẩm trong danh mục laptop chơi game';
                } else {
                    $message = 'Tìm thấy ' . $products->count() . ' sản phẩm phù hợp với "' . $keyword . '"';
                }
            }

            return response()->json([
                'message' => $message,
                'data' => $products,
                'total' => $products->count()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Product search error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Có lỗi xảy ra khi tìm kiếm sản phẩm',
                'data' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function extractSearchTerms($message)
    {
        $terms = [];
        
        // Thêm các từ khóa quan trọng
        if (stripos($message, 'laptop') !== false) {
            $terms[] = 'laptop';
        }
        
        if (stripos($message, 'chơi game') !== false || stripos($message, 'gaming') !== false) {
            $terms[] = 'chơi game';
            $terms[] = 'gaming';
        }
        
        if (stripos($message, 'gaming') !== false) {
            $terms[] = 'gaming';
            $terms[] = 'chơi game';
        }
        
        // Xử lý thương hiệu
        $brands = ['hp', 'dell', 'asus', 'acer', 'lenovo', 'apple', 'msi'];
        foreach ($brands as $brand) {
            if (stripos($message, $brand) !== false) {
                $terms[] = $brand;
            }
        }
        
        return array_unique($terms);
    }

    // Tạo mới sản phẩm
    public function store(Request $request)
{
    try {
        // Log request data for debugging
        \Log::info('Product creation request:', $request->all());
        
        // Validate chỉ những field cần thiết
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:products,name,NULL,id,deleted_at,NULL',
            'description' => 'nullable|string',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'thumbnail' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
                'request_data' => $request->all()
            ], 422);
        }
        
        // Tạo sản phẩm
        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'brand_id' => $request->brand_id,
            'category_id' => $request->category_id,
            'thumbnail' => $request->thumbnail,
            'is_active' => $request->is_active ?? true,
        ]);

        // Tạo variants nếu có
        if ($request->has('variants') && is_array($request->variants)) {
            foreach ($request->variants as $variantData) {
                if (isset($variantData['sku']) && $variantData['sku']) {
                    $product->variants()->create([
                        'sku' => $variantData['sku'],
                        'Name' => $variantData['Name'] ?? $variantData['name'] ?? 'Default',
                        'price' => $variantData['price'] ?? 0,
                        'stock' => $variantData['stock'] ?? 0,
                        'quantity' => $variantData['quantity'] ?? $variantData['stock'] ?? 0,
                        'is_active' => true,
                    ]);
                }
            }
        } else {
            // Tạo variant mặc định
            $product->variants()->create([
                'sku' => 'SKU-' . $product->id . '-DEFAULT',
                'Name' => 'Mặc định',
                'price' => 0,
                'stock' => 1,
                'quantity' => 1,
                'is_active' => true,
            ]);
        }
        
        // Clear cache
        Cache::flush();
        
        return response()->json([
            'message' => 'Thêm sản phẩm thành công',
            'data' => $product->load(['variants', 'brand', 'category']),
            'timestamp' => now()->timestamp
        ], 201);
        
    } catch (\Exception $e) {
        \Log::error('Product creation error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Lỗi khi tạo sản phẩm: ' . $e->getMessage()
        ], 500);
    }
}


    // Cập nhật sản phẩm
public function update(Request $request, $id)
{
    try {
        Log::info('Updating product', ['id' => $id, 'data' => $request->all()]);
        
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:products,name,' . $id . ',id,deleted_at,NULL',
            'description' => 'nullable|string',
            'brand_id' => 'sometimes|required|exists:brands,id',
            'category_id' => 'sometimes|required|exists:categories,id',
            'thumbnail' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->only(['name', 'description', 'brand_id', 'category_id', 'thumbnail', 'is_active']);
        // Không filter để cho phép cập nhật giá trị rỗng
        $data = array_filter($data, function($value, $key) {
            return $key === 'is_active' ? $value !== null : true;
        }, ARRAY_FILTER_USE_BOTH);
        
        $product->update($data);
        
        // Clear cache khi cập nhật sản phẩm
        Cache::flush();
        
        Log::info('Product updated successfully', ['id' => $id, 'changes' => $product->getChanges()]);
        
        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công!',
            'data' => $product->fresh()->load(['brand', 'category'])
        ]);
        
    } catch (\Exception $e) {
        Log::error('Product update error', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => 'Lỗi khi cập nhật sản phẩm: ' . $e->getMessage()
        ], 500);
    }
}


    // Xem chi tiết sản phẩm (kèm biến thể)
    public function show($id)
{
    $product = Product::withTrashed()
        ->with([
            'variants' => function($q) {
                $q->select('id', 'product_id', 'sku', 'Name', 'price', 'stock', 'quantity', 'is_active')
                  ->orderBy('id', 'asc');
            }, 
            'brand:id,name', 
            'category:id,name'
        ])
        ->find($id);

    if (!$product) {
        return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
    }

    $totalStock = $product->variants->sum('stock');
    $product->total_stock = $totalStock;
    
    // Xử lý ảnh thumbnail
    if (!$product->thumbnail || $product->thumbnail === 'No image') {
        $product->thumbnail = 'http://127.0.0.1:8000/placeholder.svg';
    }

    return response()->json([
        'message' => 'Chi tiết sản phẩm',
        'data' => $product
    ]);
}

public function destroy($id)
{
    try {
        Log::info('Attempting to delete product', ['id' => $id, 'user' => auth()->id()]);
        
        $product = Product::find($id);
        
        if (!$product) {
            Log::warning('Product not found for deletion', ['id' => $id]);
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }
        
        $productName = $product->name;
        
        // Xóa mềm
        $deleted = $product->delete();
        
        Log::info('Product soft deleted successfully', [
            'id' => $id,
            'name' => $productName,
            'deleted' => $deleted,
            'deleted_at' => $product->fresh()->deleted_at
        ]);
        
        return response()->json([
            'success' => true,
            'message' => "Xóa sản phẩm '{$productName}' thành công",
            'data' => [
                'id' => $id,
                'name' => $productName,
                'deleted_at' => $product->fresh()->deleted_at
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('Product delete error', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Lỗi khi xóa sản phẩm: ' . $e->getMessage()
        ], 500);
    }
}


public function restore($id)
{
    try {
        $product = Product::onlyTrashed()->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm đã xóa'], 404);
        }

        // Khôi phục sản phẩm
        $product->restore();
        
        // Đảm bảo sản phẩm active
        $product->update(['is_active' => true]);
        
        // Cập nhật thời gian để hiện thị ở đầu danh sách
        $product->touch();
        
        \Log::info('Product restored:', [
            'id' => $id,
            'name' => $product->name,
            'is_active' => $product->is_active,
            'deleted_at' => $product->deleted_at
        ]);

        return response()->json([
            'message' => 'Khôi phục sản phẩm thành công',
            'data' => $product->fresh()->load(['brand', 'category'])
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Product restore error:', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'Lỗi khi khôi phục sản phẩm'], 500);
    }
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

public function trashed()
{
    $products = Product::onlyTrashed()
        ->select('id', 'name', 'brand_id', 'category_id', 'thumbnail', 'deleted_at')
        ->with(['brand:id,name', 'category:id,name'])
        ->orderBy('deleted_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Danh sách sản phẩm đã xóa',
        'data' => $products,
        'count' => $products->count()
    ]);
}

// Xóa vĩnh viễn sản phẩm
public function forceDelete($id)
{
    $product = Product::onlyTrashed()->with('variants')->find($id);

    if (!$product) {
        return response()->json(['message' => 'Không tìm thấy sản phẩm đã xóa'], 404);
    }

    // Xóa tất cả variants trước
    if ($product->variants) {
        foreach ($product->variants as $variant) {
            $variant->forceDelete();
        }
    }
    
    // Xóa ảnh nếu có
    if ($product->thumbnail && Storage::exists($product->thumbnail)) {
        Storage::delete($product->thumbnail);
    }
    
    // Xóa sản phẩm
    $product->forceDelete();
    
    // Clear cache
    cache()->flush();

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
        
        // Xử lý ảnh thumbnail
        if (!$product->thumbnail || $product->thumbnail === 'No image') {
            $product->thumbnail = 'http://127.0.0.1:8000/placeholder.svg';
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
        
        // Xử lý ảnh thumbnail
        if (!$product->thumbnail || $product->thumbnail === 'No image') {
            $product->thumbnail = 'http://127.0.0.1:8000/placeholder.svg';
        }
    });

    return response()->json([
        'message' => 'Sản phẩm theo danh mục',
        'data' => $products
    ]);
}

// API cho trang home
public function home()
{
    $products = Product::with([
            'brand:id,name', 
            'category:id,name',
            'variants:id,product_id,price,stock,quantity,is_active'
        ])
        ->where('is_active', true)
        ->orderBy('updated_at', 'desc')
        ->take(12)
        ->get();
    
    // Thêm price từ variant đầu tiên
    $products->transform(function ($product) {
        if ($product->variants && $product->variants->count() > 0) {
            $product->price = $product->variants->first()->price;
        } else {
            $product->price = 0;
        }
        return $product;
    });
    
    return response()->json([
        'message' => 'Sản phẩm trang chủ',
        'data' => $products,
        'timestamp' => now()->timestamp // Thêm timestamp để cache busting
    ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
}

// API admin siêu nhanh - không load relationships
public function adminIndex(Request $request)
{
    $perPage = min($request->get('per_page', 10), 20);
    $search = $request->get('search', '');
    
    $query = Product::select('id', 'name', 'brand_id', 'category_id', 'is_active')
        ->whereNull('deleted_at');
    
    if ($search) {
        $query->where('name', 'like', '%' . $search . '%');
    }
    
    $products = $query->orderBy('id', 'desc')->paginate($perPage);
    
    return response()->json([
        'data' => $products->items(),
        'pagination' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total()
        ]
    ]);
}

// API lấy brands và categories
public function adminMeta()
{
    return response()->json([
        'brands' => \App\Models\Brand::select('id', 'name')->get(),
        'categories' => \App\Models\Category::select('id', 'name')->get()
    ]);
}

}
