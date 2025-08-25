<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AIService
{
    private $apiKey;
    private $model;
    private $enabled;

    public function __construct()
    {
        $this->apiKey = config('services.google_ai.api_key', env('GOOGLE_AI_API_KEY'));
        $this->model = config('services.google_ai.model', env('GOOGLE_AI_MODEL', 'gemini-1.5-flash'));
        $this->enabled = config('services.google_ai.enabled', env('AI_CHAT_ENABLED', true));
    }

    public function chat($message)
    {
        if (!$this->enabled || !$this->isValidApiKey()) {
            return $this->fallbackResponse($message);
        }

        try {
            $response = $this->callGeminiAPI($message);
            
            // Nếu là câu hỏi về sản phẩm, tìm kiếm thực tế
            if ($this->isProductQuery($message)) {
                $products = $this->searchProducts($message);
                
                if ($products->isNotEmpty()) {
                    return [
                        'message' => $response . "\n\n📱 **Sản phẩm phù hợp:**\n" . $this->formatProductList($products),
                        'products' => $products->take(5),
                        'type' => 'product_search',
                        'ai_powered' => true
                    ];
                }
            }
            
            return [
                'message' => $response,
                'type' => 'general',
                'ai_powered' => true
            ];
            
        } catch (\Exception $e) {
            Log::error('AI Service Error: ' . $e->getMessage());
            return $this->fallbackResponse($message);
        }
    }

    private function isValidApiKey()
    {
        return $this->apiKey && 
               $this->apiKey !== 'your_google_ai_api_key_here' && 
               $this->apiKey !== 'AIzaSyDummy_Key_For_Testing_Replace_With_Real_Key';
    }

    private function callGeminiAPI($message)
    {
        $prompt = $this->buildPrompt($message);
        
        $response = Http::timeout(15)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                    'topP' => 0.8,
                    'topK' => 40
                ]
            ]
        );

        if (!$response->successful()) {
            throw new \Exception('Gemini API failed: ' . $response->status());
        }

        $data = $response->json();
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid API response format');
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    private function buildPrompt($message)
    {
        $context = Cache::remember('ai_store_context', 3600, function() {
            return [
                'categories' => Category::pluck('name')->take(15)->join(', '),
                'brands' => Brand::pluck('name')->take(15)->join(', '),
                'product_count' => Product::where('is_active', true)->count()
            ];
        });

        return "Bạn là trợ lý AI của TechStore - cửa hàng công nghệ hàng đầu Việt Nam.

🏪 **Thông tin cửa hàng:**
- Sản phẩm: {$context['product_count']} sản phẩm đang bán
- Danh mục: {$context['categories']}
- Thương hiệu: {$context['brands']}
- Chuyên: laptop, điện thoại, phụ kiện công nghệ

🎯 **Nhiệm vụ:**
- Tư vấn sản phẩm công nghệ
- Hỗ trợ tìm kiếm theo nhu cầu
- So sánh và đưa ra gợi ý
- Trả lời thắc mắc về sản phẩm

📝 **Hướng dẫn trả lời:**
- Thân thiện, chuyên nghiệp
- Ngắn gọn (dưới 150 từ)
- Sử dụng emoji phù hợp
- Khuyến khích khách hàng tìm hiểu
- Nếu không biết, hướng dẫn liên hệ admin

❓ **Câu hỏi khách hàng:** {$message}";
    }

    private function isProductQuery($message)
    {
        $keywords = [
            'tìm', 'sản phẩm', 'giá', 'triệu', 'laptop', 'điện thoại', 'phone',
            'máy tính', 'pc', 'gaming', 'văn phòng', 'học tập', 'iphone', 'samsung',
            'dell', 'hp', 'asus', 'acer', 'macbook', 'surface', 'thinkpad',
            'dưới', 'trên', 'khoảng', 'từ', 'đến', 'budget', 'rẻ', 'cao cấp',
            'mua', 'chọn', 'so sánh', 'tốt nhất', 'khuyên', 'nên',
            'danh mục', 'chơi game', 'trong danh mục', 'có gì', 'có không'
        ];
        
        // Kiểm tra các pattern đặc biệt
        $patterns = [
            '/tìm.*sản phẩm/i',
            '/sản phẩm.*trong.*danh mục/i',
            '/danh mục.*laptop/i',
            '/laptop.*chơi game/i',
            '/chơi game.*laptop/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return collect($keywords)->some(fn($keyword) => stripos($message, $keyword) !== false);
    }

    private function searchProducts($message)
    {
        try {
            // Tìm trực tiếp trong database để tránh lỗi HTTP
            return $this->fallbackSearch($message);
            
        } catch (\Exception $e) {
            \Log::error('AI Search Products Error: ' . $e->getMessage());
            return collect([]);
        }
    }
    
    private function fallbackSearch($message)
    {
        $query = Product::with(['category', 'brand'])
            ->where('is_active', true);

        // Xử lý typo và tìm kiếm linh hoạt
        $searchTerms = $this->extractSearchTerms($message);
        
        $query->where(function($q) use ($searchTerms, $message) {
            foreach ($searchTerms as $term) {
                $q->orWhere('name', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%");
            }
            
            // Tìm theo category
            $q->orWhereHas('category', function($catQuery) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $catQuery->orWhere('name', 'LIKE', "%{$term}%");
                }
            });
            
            // Tìm theo brand
            $q->orWhereHas('brand', function($brandQuery) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $brandQuery->orWhere('name', 'LIKE', "%{$term}%");
                }
            });
        });

        if ($priceRange = $this->extractPriceRange($message)) {
            $query->whereBetween('price', $priceRange);
        }

        return $query->orderBy('created_at', 'desc')->take(5)->get();
    }
    
    private function extractSearchTerms($message)
    {
        $terms = [];
        
        // Thêm từ gốc
        $terms[] = $message;
        
        // Xử lý các từ khóa quan trọng
        if (stripos($message, 'laptop') !== false) {
            $terms[] = 'laptop';
        }
        
        if (stripos($message, 'chới game') !== false || stripos($message, 'chơi game') !== false) {
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

    private function extractPriceRange($message)
    {
        // Dưới X triệu
        if (preg_match('/(dưới|under)\s*(\d+)\s*triệu/i', $message, $matches)) {
            return [0, intval($matches[2]) * 1000000];
        }
        
        // Trên X triệu
        if (preg_match('/(trên|over)\s*(\d+)\s*triệu/i', $message, $matches)) {
            return [intval($matches[2]) * 1000000, 999999999];
        }
        
        // X - Y triệu
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*triệu/i', $message, $matches)) {
            return [intval($matches[1]) * 1000000, intval($matches[2]) * 1000000];
        }
        
        return null;
    }

    private function formatProductList($products)
    {
        return $products->map(function($product) {
            $price = number_format($product->price, 0, ',', '.') . ' VND';
            $category = $product->category?->name ?? 'N/A';
            $brand = $product->brand?->name ?? 'N/A';
            
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5174');
            return "• **{$product->name}**\n  💰 {$price} | 🏷️ {$brand} | 📂 {$category}\n  🔗 Chi tiết: {$frontendUrl}/product/{$product->id}";
        })->join("\n\n");
    }

    private function fallbackResponse($message)
    {
        // Chào hỏi
        if (preg_match('/^(chào|hello|hi|xin chào)/i', $message)) {
            return [
                'message' => '👋 Xin chào! Tôi là trợ lý AI của **TechStore**.

🤖 Tôi có thể giúp bạn:
• 🔍 Tìm sản phẩm theo nhu cầu
• 💰 So sánh giá cả
• 📱 Tư vấn lựa chọn
• ❓ Trả lời thắc mắc

Bạn đang tìm sản phẩm gì?',
                'type' => 'greeting',
                'ai_powered' => false
            ];
        }
        
        // Câu hỏi về sản phẩm
        if ($this->isProductQuery($message)) {
            $products = $this->searchProducts($message);
            
            if ($products->isNotEmpty()) {
                return [
                    'message' => "🔍 **Tìm thấy sản phẩm phù hợp:**\n\n" . $this->formatProductList($products) . "\n\n💬 Cần tư vấn thêm? Hãy chat với admin!",
                    'products' => $products,
                    'type' => 'product_search',
                    'ai_powered' => false
                ];
            } else {
                return [
                    'message' => '😔 Không tìm thấy sản phẩm phù hợp.

💡 **Gợi ý:**
• Thử từ khóa khác
• Liên hệ admin để được tư vấn
• Xem danh mục sản phẩm',
                    'type' => 'no_results',
                    'ai_powered' => false
                ];
            }
        }
        
        // Câu hỏi chung
        $responses = [
            '🤔 Tôi chưa hiểu rõ câu hỏi của bạn.

💡 **Tôi có thể giúp:**
• Tìm laptop, điện thoại
• So sánh giá sản phẩm
• Tư vấn lựa chọn

Hãy thử hỏi cụ thể hơn!',
            '📱 Bạn đang quan tâm sản phẩm công nghệ nào?

🔥 **Danh mục hot:**
• Laptop gaming
• Điện thoại cao cấp
• Phụ kiện công nghệ

Hãy cho tôi biết nhu cầu!',
            '💬 Tôi có thể hỗ trợ tìm kiếm và tư vấn sản phẩm.

✨ **Thử hỏi:**
• "Laptop dưới 20 triệu"
• "iPhone mới nhất"
• "Gaming gear tốt"'
        ];
        
        return [
            'message' => $responses[array_rand($responses)],
            'type' => 'general',
            'ai_powered' => false
        ];
    }
}