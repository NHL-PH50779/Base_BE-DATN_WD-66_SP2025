<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $message = strtolower(trim($request->input('message', '')));
        
        if (empty($message)) {
            return response()->json([
                'response' => 'Xin chào! Tôi là AI hỗ trợ mua sắm. Tôi có thể giúp bạn:
• Tìm sản phẩm theo giá, thương hiệu, danh mục
• So sánh sản phẩm
• Tư vấn mua sắm
• Thông tin chi tiết sản phẩm'
            ]);
        }
        
        // Xử lý thông minh - kiểm tra nhiều pattern cùng lúc
        return $this->processIntelligentSearch($message);
    }

    private function processIntelligentSearch($message)
    {
        // Trích xuất thông tin từ message
        $info = $this->extractSearchInfo($message);
        
        // Nếu có nhiều tiêu chí, tìm kết hợp
        if (count(array_filter($info)) > 1) {
            return $this->searchCombined($info, $message);
        }
        
        // Xử lý từng tiêu chí riêng biệt
        if ($info['price_range']) {
            return $this->handlePriceSearch($info['price_range'], $message);
        }
        
        if ($info['brand']) {
            return $this->searchByBrand($info['brand']);
        }
        
        if ($info['category']) {
            return $this->searchByCategory($info['category']);
        }
        
        if ($info['keyword']) {
            return $this->searchByKeyword($info['keyword']);
        }
        
        // Câu hỏi chung về thông tin
        if (preg_match('/(?:có\s*bao\s*nhiều|số\s*lượng|list|danh\s*sách)/i', $message)) {
            return $this->handleGeneralInfo($message);
        }
        
        // Default fallback
        return $this->getDefaultResponse();
    }
    
    private function extractSearchInfo($message)
    {
        $info = [
            'brand' => null,
            'category' => null, 
            'price_range' => null,
            'keyword' => null
        ];
        
        // Trích xuất brand
        if (preg_match('/(?:thương\s*hiệu|brand|hãng)\s*([a-z0-9\s]+)/i', $message, $matches)) {
            $info['brand'] = trim($matches[1]);
        } elseif (preg_match('/\b(dell|hp|asus|acer|lenovo|apple|msi|samsung|lg)\b/i', $message, $matches)) {
            $info['brand'] = $matches[1];
        }
        
        // Trích xuất category
        if (preg_match('/(?:danh\s*mục|loại|category)\s*([a-zà-ỹ\s]+)/i', $message, $matches)) {
            $info['category'] = trim($matches[1]);
        } elseif (preg_match('/(?:laptop|gaming|game|đồ\s*họa|sinh\s*viên|văn\s*phòng)/i', $message, $matches)) {
            $info['category'] = $matches[0];
        }
        
        // Trích xuất giá
        if (preg_match('/(?:giá|price)\s*(?:trên|over|>)\s*(\d+)(?:\s*(?:triệu|tr))?/i', $message, $matches)) {
            $price = (int)$matches[1] * (preg_match('/triệu|tr/i', $message) ? 1000000 : 1000);
            $info['price_range'] = ['min' => $price, 'max' => null, 'type' => 'above'];
        } elseif (preg_match('/(?:giá|price)\s*(?:dưới|under|<)\s*(\d+)(?:\s*(?:triệu|tr))?/i', $message, $matches)) {
            $price = (int)$matches[1] * (preg_match('/triệu|tr/i', $message) ? 1000000 : 1000);
            $info['price_range'] = ['min' => null, 'max' => $price, 'type' => 'below'];
        } elseif (preg_match('/(?:giá|price)\s*(?:từ|from)?\s*(\d+)(?:\s*(?:đến|to|-)\s*(\d+))?(?:\s*(?:triệu|tr))?/i', $message, $matches)) {
            $multiplier = preg_match('/triệu|tr/i', $message) ? 1000000 : 1000;
            $min = (int)$matches[1] * $multiplier;
            $max = isset($matches[2]) ? (int)$matches[2] * $multiplier : $min + 2000000;
            $info['price_range'] = ['min' => $min, 'max' => $max, 'type' => 'range'];
        }
        
        // Trích xuất keyword chung
        if (!$info['brand'] && !$info['category'] && !$info['price_range']) {
            if (preg_match('/(?:tìm|search|cần|muốn)\s*(.+)/i', $message, $matches)) {
                $info['keyword'] = trim($matches[1]);
            }
        }
        
        return $info;
    }

    private function searchCombined($info, $message)
    {
        $query = Product::where('is_active', true);
        $conditions = [];
        
        // Áp dụng brand filter
        if ($info['brand']) {
            $brand = Brand::where('name', 'like', "%{$info['brand']}%")->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
                $conditions[] = "thương hiệu {$brand->name}";
            }
        }
        
        // Áp dụng category filter
        if ($info['category']) {
            $category = $this->findCategory($info['category']);
            if ($category) {
                $query->where('category_id', $category->id);
                $conditions[] = "danh mục {$category->name}";
            }
        }
        
        // Áp dụng price filter
        if ($info['price_range']) {
            $priceRange = $info['price_range'];
            $query->whereHas('variants', function($q) use ($priceRange) {
                if ($priceRange['min'] && $priceRange['max']) {
                    $q->whereBetween('price', [$priceRange['min'], $priceRange['max']]);
                } elseif ($priceRange['min']) {
                    $q->where('price', '>=', $priceRange['min']);
                } elseif ($priceRange['max']) {
                    $q->where('price', '<=', $priceRange['max']);
                }
            });
            
            $priceText = $this->formatPriceRange($priceRange);
            $conditions[] = "giá {$priceText}";
        }
        
        $products = $query->with(['variants', 'brand', 'category'])->take(8)->get();
        
        if ($products->isEmpty()) {
            return response()->json([
                'response' => "Không tìm thấy sản phẩm nào với điều kiện: " . implode(', ', $conditions)
            ]);
        }
        
        $productList = $products->map(function($product) {
            $price = $product->variants->first()?->price ?? 0;
            return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
        })->implode("\n");
        
        return response()->json([
            'response' => "Sản phẩm với điều kiện: " . implode(', ', $conditions) . ":\n{$productList}",
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->variants->first()?->price ?? 0,
                    'brand' => $product->brand?->name,
                    'category' => $product->category?->name,
                    'link' => 'http://localhost:5174/product/' . $product->id
                ];
            })
        ]);
    }
    
    private function handleGeneralInfo($message)
    {
        if (preg_match('/(?:thương\s*hiệu|brand)/i', $message)) {
            $brands = Brand::pluck('name')->take(10);
            return response()->json([
                'response' => "Các thương hiệu có sẵn:\n• " . $brands->implode("\n• ")
            ]);
        }
        
        if (preg_match('/(?:danh\s*mục|category)/i', $message)) {
            $categories = Category::pluck('name')->take(10);
            return response()->json([
                'response' => "Các danh mục có sẵn:\n• " . $categories->implode("\n• ")
            ]);
        }
        
        if (preg_match('/(?:sản\s*phẩm|product)/i', $message)) {
            $count = Product::where('is_active', true)->count();
            $latest = Product::where('is_active', true)->with('variants')->latest()->take(5)->get();
            
            $productList = $latest->map(function($product) {
                $price = $product->variants->first()?->price ?? 0;
                return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
            })->implode("\n");
            
            return response()->json([
                'response' => "Hiện có {$count} sản phẩm. Sản phẩm mới nhất:\n{$productList}",
                'products' => $latest->map(function($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->variants->first()?->price ?? 0,
                        'link' => 'http://localhost:5174/product/' . $product->id
                    ];
                })
            ]);
        }
        
        return $this->getDefaultResponse();
    }
    
    private function findCategory($categoryName)
    {
        $category = Category::where('name', 'like', "%{$categoryName}%")->first();
        
        if (!$category) {
            $keywords = [
                'gaming' => ['gaming', 'game', 'chơi game'],
                'laptop' => ['laptop', 'máy tính'],
                'đồ họa' => ['đồ họa', 'graphic', 'design'],
                'sinh viên' => ['sinh viên', 'học tập', 'student']
            ];
            
            foreach ($keywords as $key => $terms) {
                foreach ($terms as $term) {
                    if (stripos($categoryName, $term) !== false) {
                        $category = Category::where('name', 'like', "%{$key}%")->first();
                        if ($category) break 2;
                    }
                }
            }
        }
        
        return $category;
    }
    
    private function formatPriceRange($priceRange)
    {
        if ($priceRange['type'] === 'above') {
            return 'trên ' . number_format($priceRange['min'], 0, ',', '.') . 'đ';
        } elseif ($priceRange['type'] === 'below') {
            return 'dưới ' . number_format($priceRange['max'], 0, ',', '.') . 'đ';
        } else {
            return number_format($priceRange['min'], 0, ',', '.') . 'đ - ' . number_format($priceRange['max'], 0, ',', '.') . 'đ';
        }
    }
    
    private function handlePriceSearch($priceRange, $message)
    {
        if ($priceRange['type'] === 'above') {
            return $this->searchByPriceRange($priceRange['min'], null, 'above');
        } elseif ($priceRange['type'] === 'below') {
            return $this->searchByPriceRange(null, $priceRange['max'], 'below');
        } else {
            return $this->searchByPrice($priceRange['min'], $priceRange['max']);
        }
    }
    
    private function getDefaultResponse()
    {
        return response()->json([
            'response' => 'Tôi có thể giúp bạn:
• Tìm theo thương hiệu: "Dell", "HP", "Asus"
• Tìm theo danh mục: "laptop gaming", "laptop đồ họa"
• Tìm theo giá: "giá 15 triệu", "giá trên 20 triệu"
• Kết hợp: "laptop Dell giá dưới 25 triệu"
• Thông tin chung: "có bao nhiều thương hiệu?"'
        ]);
    }

    private function searchByBrand($brandName)
    {
        $brand = Brand::where('name', 'like', "%{$brandName}%")->first();
        
        if (!$brand) {
            return response()->json([
                'response' => "Không tìm thấy thương hiệu '{$brandName}'. Các thương hiệu có sẵn: " . 
                Brand::pluck('name')->take(5)->implode(', ')
            ]);
        }

        $products = Product::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->with(['variants', 'category'])
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'response' => "Không có sản phẩm nào của thương hiệu {$brand->name}"
            ]);
        }

        $productList = $products->map(function($product) {
            $price = $product->variants->first()?->price ?? 0;
            return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
        })->implode("\n");

        return response()->json([
            'response' => "Sản phẩm thương hiệu {$brand->name}:\n{$productList}",
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->variants->first()?->price ?? 0,
                    'link' => 'http://localhost:5174/product/' . $product->id
                ];
            })
        ]);
    }

    private function searchByCategory($categoryName)
    {
        $category = $this->findCategory($categoryName);
        
        if (!$category) {
            return response()->json([
                'response' => "Không tìm thấy danh mục '{$categoryName}'. Các danh mục có sẵn: " . 
                Category::pluck('name')->take(5)->implode(', ')
            ]);
        }

        $products = Product::where('category_id', $category->id)
            ->where('is_active', true)
            ->with(['variants', 'brand'])
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'response' => "Không có sản phẩm nào trong danh mục {$category->name}"
            ]);
        }

        $productList = $products->map(function($product) {
            $price = $product->variants->first()?->price ?? 0;
            return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
        })->implode("\n");

        return response()->json([
            'response' => "Sản phẩm danh mục {$category->name}:\n{$productList}",
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->variants->first()?->price ?? 0,
                    'link' => 'http://localhost:5174/product/' . $product->id
                ];
            })
        ]);
    }

    private function searchByPrice($minPrice, $maxPrice)
    {
        $products = Product::whereHas('variants', function($query) use ($minPrice, $maxPrice) {
                $query->whereBetween('price', [$minPrice, $maxPrice]);
            })
            ->where('is_active', true)
            ->with(['variants', 'brand', 'category'])
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            $products = Product::whereHas('variants')
                ->where('is_active', true)
                ->with(['variants', 'brand', 'category'])
                ->get()
                ->sortBy(function($product) use ($minPrice) {
                    $price = $product->variants->first()?->price ?? 0;
                    return abs($price - $minPrice);
                })
                ->take(3);
        }

        if ($products->isEmpty()) {
            return response()->json([
                'response' => "Không có sản phẩm nào trong hệ thống"
            ]);
        }

        $productList = $products->map(function($product) {
            $price = $product->variants->first()?->price ?? 0;
            return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
        })->implode("\n");

        $priceText = number_format($minPrice, 0, ',', '.') . "đ";
        if ($maxPrice != $minPrice + 2000000) {
            $priceText .= " - " . number_format($maxPrice, 0, ',', '.')  . "đ";
        }

        return response()->json([
            'response' => "Sản phẩm khoảng {$priceText}:\n{$productList}",
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->variants->first()?->price ?? 0,
                    'link' => 'http://localhost:5174/product/' . $product->id
                ];
            })
        ]);
    }

    private function searchByPriceRange($minPrice, $maxPrice, $type)
    {
        $query = Product::whereHas('variants', function($q) use ($minPrice, $maxPrice) {
            if ($minPrice && $maxPrice) {
                $q->whereBetween('price', [$minPrice, $maxPrice]);
            } elseif ($minPrice) {
                $q->where('price', '>=', $minPrice);
            } elseif ($maxPrice) {
                $q->where('price', '<=', $maxPrice);
            }
        })
        ->where('is_active', true)
        ->with(['variants', 'brand', 'category'])
        ->take(5);

        $products = $query->get();

        if ($products->isEmpty()) {
            $priceText = $minPrice ? number_format($minPrice, 0, ',', '.') . 'đ' : number_format($maxPrice, 0, ',', '.') . 'đ';
            $typeText = $type === 'above' ? 'trên' : 'dưới';
            return response()->json([
                'response' => "Không có sản phẩm nào có giá {$typeText} {$priceText}"
            ]);
        }

        $productList = $products->map(function($product) {
            $price = $product->variants->first()?->price ?? 0;
            return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
        })->implode("\n");

        $priceText = $minPrice ? number_format($minPrice, 0, ',', '.') . 'đ' : number_format($maxPrice, 0, ',', '.') . 'đ';
        $typeText = $type === 'above' ? 'trên' : 'dưới';

        return response()->json([
            'response' => "Sản phẩm có giá {$typeText} {$priceText}:\n{$productList}",
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->variants->first()?->price ?? 0,
                    'link' => 'http://localhost:5174/product/' . $product->id
                ];
            })
        ]);
    }

    private function searchByKeyword($keyword)
    {
        $products = Product::where('name', 'like', "%{$keyword}%")
            ->where('is_active', true)
            ->with(['variants', 'brand', 'category'])
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'response' => "Không tìm thấy sản phẩm nào với từ khóa '{$keyword}'"
            ]);
        }

        $productList = $products->map(function($product) {
            $price = $product->variants->first()?->price ?? 0;
            return "• {$product->name} - " . number_format($price, 0, ',', '.') . "đ";
        })->implode("\n");

        return response()->json([
            'response' => "Kết quả tìm kiếm '{$keyword}':\n{$productList}",
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->variants->first()?->price ?? 0,
                    'link' => 'http://localhost:5174/product/' . $product->id
                ];
            })
        ]);
    }
}