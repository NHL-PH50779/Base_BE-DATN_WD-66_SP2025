<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FlashSaleController extends Controller
{
    // Lấy flash sale hiện tại
    public function current(Request $request)
    {
        // Kiểm tra nếu có yêu cầu refresh thì không dùng cache
        $useCache = !$request->has('refresh') || $request->get('refresh') !== '1';
        
        if ($useCache) {
            // Giảm độ phức tạp của query và tăng thời gian cache
            $flashSale = Cache::remember('current_flash_sale', 300, function () {
                return FlashSale::with(['activeItems' => function($q) {
                        $q->with(['product' => function($q) {
                            $q->select('id', 'name', 'thumbnail', 'brand_id', 'category_id');
                        }]);
                    }])
                    ->active()
                    ->current()
                    ->first();
            });
        } else {
            // Không dùng cache, lấy dữ liệu mới nhất
            $flashSale = FlashSale::with(['activeItems' => function($q) {
                    $q->with(['product' => function($q) {
                        $q->select('id', 'name', 'thumbnail', 'brand_id', 'category_id');
                    }]);
                }])
                ->active()
                ->current()
                ->first();
        }

        if (!$flashSale) {
            return response()->json([
                'message' => 'Không có flash sale nào đang diễn ra',
                'data' => null
            ]);
        }

        return response()->json([
            'message' => 'Flash sale hiện tại',
            'data' => [
                'id' => $flashSale->id,
                'name' => $flashSale->name,
                'description' => $flashSale->description,
                'start_time' => $flashSale->start_time,
                'end_time' => $flashSale->end_time,
                'time_remaining' => $flashSale->time_remaining,
                'status' => $flashSale->status,
                'items' => $flashSale->activeItems->map(function ($item) {
                    // Thêm kiểm tra null cho product
                    if (!$item->product) {
                        return null;
                    }
                    
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'thumbnail' => $item->product->thumbnail,
                            'brand' => $item->product->brand_id ?? null,
                            'category' => $item->product->category_id ?? null
                        ],
                        'original_price' => (float)$item->original_price,
                        'sale_price' => (float)$item->sale_price,
                        'discount_percentage' => (int)$item->discount_percentage,
                        'quantity_limit' => (int)$item->quantity_limit,
                        'sold_quantity' => (int)$item->sold_quantity,
                        'remaining_quantity' => (int)$item->remaining_quantity,
                        'sold_percentage' => (float)$item->sold_percentage,
                        'is_available' => (bool)$item->is_available
                    ];
                })->filter()->values()
            ]
        ]);
    }

    // Lấy flash sale sắp tới
    public function upcoming()
    {
        // Đơn giản hóa query và tăng thời gian cache
        $flashSale = Cache::remember('upcoming_flash_sale', 600, function () {
            return FlashSale::select('id', 'name', 'description', 'start_time', 'end_time', 'is_active')
                ->active()
                ->upcoming()
                ->orderBy('start_time')
                ->first();
        });

        if (!$flashSale) {
            return response()->json([
                'message' => 'Không có flash sale nào sắp tới',
                'data' => null
            ]);
        }

        return response()->json([
            'message' => 'Flash sale sắp tới',
            'data' => [
                'id' => $flashSale->id,
                'name' => $flashSale->name,
                'description' => $flashSale->description,
                'start_time' => $flashSale->start_time,
                'end_time' => $flashSale->end_time,
                'status' => $flashSale->status,
                'countdown' => $flashSale->start_time->diffInSeconds(Carbon::now())
            ]
        ]);
    }

    // Kiểm tra sản phẩm có trong flash sale không
    public function checkProduct(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $flashSaleItem = Cache::remember(
            "flash_sale_product_{$request->product_id}", 
            300, 
            function () use ($request) {
                return FlashSaleItem::with('flashSale')
                    ->where('product_id', $request->product_id)
                    ->whereHas('flashSale', function ($query) {
                        $query->active()->current();
                    })
                    ->active()
                    ->first();
            }
        );

        return response()->json([
            'message' => 'Kiểm tra flash sale sản phẩm',
            'data' => [
                'is_in_flash_sale' => !!$flashSaleItem,
                'flash_sale_item' => $flashSaleItem ? [
                    'id' => $flashSaleItem->id,
                    'original_price' => $flashSaleItem->original_price,
                    'sale_price' => $flashSaleItem->sale_price,
                    'discount_percentage' => $flashSaleItem->discount_percentage,
                    'remaining_quantity' => $flashSaleItem->remaining_quantity,
                    'is_available' => $flashSaleItem->is_available,
                    'flash_sale' => [
                        'name' => $flashSaleItem->flashSale->name,
                        'end_time' => $flashSaleItem->flashSale->end_time,
                        'time_remaining' => $flashSaleItem->flashSale->time_remaining
                    ]
                ] : null
            ]
        ]);
    }

    // Lấy thống kê flash sale
    public function stats($id)
    {
        $flashSale = FlashSale::with('items')->findOrFail($id);
        
        $stats = [
            'total_items' => $flashSale->items->count(),
            'total_sold' => $flashSale->items->sum('sold_quantity'),
            'total_revenue' => $flashSale->items->sum(function ($item) {
                return $item->sold_quantity * $item->sale_price;
            }),
            'completion_rate' => $flashSale->items->avg('sold_percentage'),
            'top_selling_items' => $flashSale->items()
                ->with('product')
                ->orderByDesc('sold_quantity')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'product_name' => $item->product->name,
                        'sold_quantity' => $item->sold_quantity,
                        'sold_percentage' => $item->sold_percentage
                    ];
                })
        ];

        return response()->json([
            'message' => 'Thống kê flash sale',
            'data' => $stats
        ]);
    }

    // Admin methods
    public function adminIndex()
    {
        try {
            $flashSales = FlashSale::with(['items.product'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($flashSale) {
                    $now = Carbon::now();
                    $startTime = Carbon::parse($flashSale->start_time);
                    $endTime = Carbon::parse($flashSale->end_time);
                    
                    $status = 'active';
                    if (!$flashSale->is_active) {
                        $status = 'inactive';
                    } elseif ($now->lt($startTime)) {
                        $status = 'upcoming';
                    } elseif ($now->gt($endTime)) {
                        $status = 'ended';
                    }
                    
                    return [
                        'id' => $flashSale->id,
                        'name' => $flashSale->name,
                        'description' => $flashSale->description,
                        'start_time' => $flashSale->start_time,
                        'end_time' => $flashSale->end_time,
                        'is_active' => $flashSale->is_active,
                        'status' => $status,
                        'items' => $flashSale->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'product' => $item->product ? [
                                    'id' => $item->product->id,
                                    'name' => $item->product->name,
                                    'thumbnail' => $item->product->thumbnail
                                ] : null,
                                'original_price' => $item->original_price,
                                'sale_price' => $item->sale_price,
                                'discount_percentage' => $item->discount_percentage,
                                'quantity_limit' => $item->quantity_limit,
                                'sold_quantity' => $item->sold_quantity,
                                'sold_percentage' => $item->sold_percentage
                            ];
                        })
                    ];
                });

            return response()->json([
                'message' => 'Danh sách flash sales',
                'data' => $flashSales
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách flash sales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adminStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.sale_price' => 'required|numeric|min:0',
            'products.*.quantity_limit' => 'required|integer|min:1'
        ]);

        $flashSale = FlashSale::create([
            'name' => $request->name,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_active' => true
        ]);

        // Thêm sản phẩm vào flash sale
        foreach ($request->products as $productData) {
            $product = Product::find($productData['product_id']);
            $originalPrice = $product->price ?? $product->variants->first()->price ?? 0;
            
            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_id' => $productData['product_id'],
                'original_price' => $originalPrice,
                'sale_price' => $productData['sale_price'],
                'discount_percentage' => $originalPrice > 0 ? round((($originalPrice - $productData['sale_price']) / $originalPrice) * 100) : 0,
                'quantity_limit' => $productData['quantity_limit'],
                'sold_quantity' => 0,
                'is_active' => true
            ]);
        }
        
        Cache::forget('current_flash_sale');
        Cache::forget('upcoming_flash_sale');

        return response()->json([
            'message' => 'Tạo flash sale thành công',
            'data' => $flashSale->load('items.product')
        ], 201);
    }

    public function adminUpdate(Request $request, $id)
    {
        $flashSale = FlashSale::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'products' => 'array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.sale_price' => 'required|numeric|min:0',
            'products.*.quantity_limit' => 'required|integer|min:1'
        ]);
        
        $flashSale->update([
            'name' => $request->name,
            'description' => $request->description,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_active' => $request->is_active ?? true
        ]);
        
        // Cập nhật sản phẩm nếu có
        if ($request->has('products') && is_array($request->products)) {
            // Xóa tất cả flash sale items cũ
            FlashSaleItem::where('flash_sale_id', $id)->delete();
            
            // Thêm lại flash sale items mới
            foreach ($request->products as $productData) {
                $product = Product::find($productData['product_id']);
                $originalPrice = $product->price ?? $product->variants->first()->price ?? 0;
                
                FlashSaleItem::create([
                    'flash_sale_id' => $flashSale->id,
                    'product_id' => $productData['product_id'],
                    'original_price' => $originalPrice,
                    'sale_price' => $productData['sale_price'],
                    'discount_percentage' => $originalPrice > 0 ? round((($originalPrice - $productData['sale_price']) / $originalPrice) * 100) : 0,
                    'quantity_limit' => $productData['quantity_limit'],
                    'sold_quantity' => 0,
                    'is_active' => true
                ]);
            }
        }
        
        Cache::forget('current_flash_sale');
        Cache::forget('upcoming_flash_sale');

        return response()->json([
            'message' => 'Cập nhật flash sale thành công',
            'data' => $flashSale->load('items.product')
        ]);
    }

    public function adminDestroy($id)
    {
        $flashSale = FlashSale::findOrFail($id);
        
        // Xóa tất cả flash sale items trước
        FlashSaleItem::where('flash_sale_id', $id)->delete();
        
        // Xóa flash sale
        $flashSale->delete();
        
        Cache::forget('current_flash_sale');
        Cache::forget('upcoming_flash_sale');

        return response()->json([
            'message' => 'Xóa flash sale thành công'
        ]);
    }
}
