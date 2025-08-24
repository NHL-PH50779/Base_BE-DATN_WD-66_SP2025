<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $apiKey = env('GOOGLE_AI_API_KEY');
        if (!$apiKey) {
            Log::error('GOOGLE_AI_API_KEY chưa thiết lập.');
            return response()->json(['error' => 'Dịch vụ AI chưa được cấu hình.'], 500);
        }

        $userMessage = $request->input('message');
        $apiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key={$apiKey}";

      // ===== XỬ LÝ SẢN PHẨM =====
$productsArr = collect();
$productsList = '';
$responseFlag = "exact";

// --- Trường hợp khách hỏi laptop theo brand ---
if (preg_match('/laptop\s*(Apple|Dell|HP|Asus|Lenovo|Acer|HI|he)/i', $userMessage, $matches)) {
    $brandName = $matches[1];

    // Tìm brand theo tên
    $brand = DB::table('brands')
        ->where('name', 'like', '%' . $brandName . '%')
        ->first();

    if ($brand) {
        $productsArr = DB::table('products')
            ->where('brand_id', $brand->id)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->select('id', 'name', 'price')
            ->limit(10)
            ->get();

        if ($productsArr->isEmpty()) {
            $productsList = "Hiện tại không có sản phẩm laptop thương hiệu {$brandName} phù hợp.";
        }
    } else {
        $productsList = "Hiện tại chưa có thương hiệu {$brandName} trong hệ thống.";
    }

// --- Trường hợp khách hỏi laptop chơi game hoặc gaming ---
} elseif (preg_match('/laptop\s*(chơi game|gaming)/i', $userMessage)) {
    // Tìm category "Laptop chơi game" hoặc "Laptop Gaming"
    $category = DB::table('categories')
        ->where(function($q) {
            $q->where('name', 'like', '%chơi game%')
              ->orWhere('name', 'like', '%gaming%');
        })
        ->first();

    if ($category) {
        $productsArr = DB::table('products')
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->select('id', 'name', 'price')
            ->limit(10)
            ->get();

        if ($productsArr->isEmpty()) {
            $productsList = "Hiện tại không có sản phẩm laptop chơi game phù hợp.";
        }
    } else {
        $productsList = "Hiện tại chưa có danh mục laptop chơi game trong hệ thống.";
    }

// --- Trường hợp khách hỏi laptop theo category ---
} elseif (preg_match('/laptop\s*(gaming|chơi game|đồ họa|học tập|sinh viên|văn phòng|kỹ thuật)/iu', $userMessage, $matches)) {
    $keyword = strtolower($matches[1]);
    $category = DB::table('categories')
        ->where(function($q) use ($keyword) {
            $q->where('name', 'like', "%$keyword%");
        })
        ->first();

    if ($category) {
        $productsArr = DB::table('products')
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->select('id', 'name', 'price')
            ->limit(10)
            ->get();

        if ($productsArr->isEmpty()) {
            $productsList = "Hiện tại không có sản phẩm laptop thuộc danh mục {$category->name} phù hợp.";
        }
    } else {
        $productsList = "Hiện tại chưa có danh mục laptop {$keyword} trong hệ thống.";
    }

// --- Các trường hợp xử lý giá như cũ ---
} elseif (preg_match('/(\d+)\s?(?:-|đến|tới)\s?(\d+)(\s?triệu|tr|k|\.000\.000)?/i', $userMessage, $matches)) {
    $minInput = (int) $matches[1];
    $maxInput = (int) $matches[2];

    // Chuẩn hóa đơn vị
    if (!empty($matches[3]) && preg_match('/triệu|tr/i', $matches[3])) {
        $minPrice = $minInput * 1000000;
        $maxPrice = $maxInput * 1000000;
    } elseif (!empty($matches[3]) && preg_match('/k/i', $matches[3])) {
        $minPrice = $minInput * 1000;
        $maxPrice = $maxInput * 1000;
    } elseif ($maxInput < 1000) {
        $minPrice = $minInput * 1000000;
        $maxPrice = $maxInput * 1000000;
    } else {
        $minPrice = $minInput;
        $maxPrice = $maxInput;
    }

    $productsArr = DB::table('products')
        ->whereBetween('price', [$minPrice, $maxPrice])
        ->whereNull('deleted_at')
        ->where('is_active', 1)
        ->select('id', 'name', 'price')
        ->limit(10)
        ->get();

    if ($productsArr->isEmpty()) {
        // $responseFlag = "nearby";
        // $productsArr = DB::table('products')
        //     ->where('is_active', 1)
        //     ->whereNull('deleted_at')
        //     ->orderByRaw("ABS(price - ?)", [$maxPrice])
        //     ->limit(3)
        //     ->select('id', 'name', 'price')
        //     ->get();
           $productsList = "Hiện tại không có sản phẩm phù hợp với yêu cầu của bạn.";
    }

// --- Trường hợp "dưới X triệu" ---
} elseif (preg_match('/dưới\s?(\d+)(\s?triệu|tr|k|\.000\.000)?/i', $userMessage, $matches)) {
    $input = (int) $matches[1];
    if (!empty($matches[2]) && preg_match('/triệu|tr/i', $matches[2])) {
        $maxPrice = $input * 1000000;
    } elseif (!empty($matches[2]) && preg_match('/k/i', $matches[2])) {
        $maxPrice = $input * 1000;
    } elseif ($input < 1000) {
        $maxPrice = $input * 1000000;
    } else {
        $maxPrice = $input;
    }

    $productsArr = DB::table('products')
        ->where('price', '<=', $maxPrice)
        ->whereNull('deleted_at')
        ->where('is_active', 1)
        ->select('id', 'name', 'price')
        ->limit(10)
        ->get();

    if ($productsArr->isEmpty()) {
        // $responseFlag = "nearby";
        // $productsArr = DB::table('products')
        //     ->where('is_active', 1)
        //     ->whereNull('deleted_at')
        //     ->orderByRaw("ABS(price - ?)", [$maxPrice])
        //     ->limit(3)
        //     ->select('id', 'name', 'price')
        //     ->get();
         $productsList = "Hiện tại không có sản phẩm phù hợp với yêu cầu của bạn.";
        
    }

// --- Trường hợp "trên X triệu" ---
} elseif (preg_match('/trên\s?(\d+)(\s?triệu|tr|k|\.000\.000)?/i', $userMessage, $matches)) {
    $input = (int) $matches[1];
    if (!empty($matches[2]) && preg_match('/triệu|tr/i', $matches[2])) {
        $minPrice = $input * 1000000;
    } elseif (!empty($matches[2]) && preg_match('/k/i', $matches[2])) {
        $minPrice = $input * 1000;
    } elseif ($input < 1000) {
        $minPrice = $input * 1000000;
    } else {
        $minPrice = $input;
    }

    $productsArr = DB::table('products')
        ->where('price', '>=', $minPrice)
        ->whereNull('deleted_at')
        ->where('is_active', 1)
        ->select('id', 'name', 'price')
        ->limit(10)
        ->get();

    if ($productsArr->isEmpty()) {
        // $responseFlag = "nearby";
        // $productsArr = DB::table('products')
        //     ->where('is_active', 1)
        //     ->whereNull('deleted_at')
        //     ->orderByRaw("ABS(price - ?)", [$minPrice])
        //     ->limit(3)
        //     ->select('id', 'name', 'price')
        //     ->get();
           $productsList = "Hiện tại không có sản phẩm phù hợp với yêu cầu của bạn.";
    }

// --- Trường hợp chỉ nhập 1 số: "15tr", "15 triệu", "15000000" ---
} elseif (preg_match('/(\d+)(\s?triệu|tr|k|\.000\.000)?/i', $userMessage, $matches)) {
    $input = (int) $matches[1];
    if (!empty($matches[2]) && preg_match('/triệu|tr/i', $matches[2])) {
        $price = $input * 1000000;
    } elseif (!empty($matches[2]) && preg_match('/k/i', $matches[2])) {
        $price = $input * 1000;
    } elseif ($input < 1000) {
        $price = $input * 1000000;
    } else {
        $price = $input;
    }

    $min = $price - 1000000;
    $max = $price + 1000000;

    $productsArr = DB::table('products')
        ->whereBetween('price', [$min, $max])
        ->whereNull('deleted_at')
        ->where('is_active', 1)
        ->select('id', 'name', 'price')
        ->limit(10)
        ->get();

    if ($productsArr->isEmpty()) {
        // $responseFlag = "nearby";
        // $productsArr = DB::table('products')
        //     ->where('is_active', 1)
        //     ->whereNull('deleted_at')
        //     ->orderByRaw("ABS(price - ?)", [$price])
        //     ->limit(3)
        //     ->select('id', 'name', 'price')
        //     ->get();
           $productsList = "Hiện tại không có sản phẩm phù hợp với yêu cầu của bạn.";
    }
}
        foreach ($productsArr as $p) {
            $productUrl = url("/products/{$p->id}");
            $productsList .= "- [{$p->name}]({$productUrl}): "
                           . number_format($p->price, 0, ',', '.') . " VND\n";
        }

        // ===== XỬ LÝ ĐƠN HÀNG =====
        $ordersArr = collect();
        $ordersList = '';

        if (preg_match('/đơn hàng\s?#?(\d+)/i', $userMessage, $matches)) {
            $orderId = (int) $matches[1];
            $ordersArr = DB::table('orders')
                ->where('id', $orderId)
                ->where('user_id', $request->user()->id ?? null)
                ->select('id', 'status', 'total', 'created_at')
                ->get();
        } elseif (preg_match('/đơn hàng|order/i', $userMessage)) {
            if ($request->user()) {
                $ordersArr = DB::table('orders')
                    ->where('user_id', $request->user()->id)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->select('id', 'status', 'total', 'created_at')
                    ->get();
            }
        }

        foreach ($ordersArr as $o) {
            $ordersList .= "- Đơn #{$o->id}, Trạng thái: {$o->status}, Tổng: "
                         . number_format($o->total, 0, ',', '.') . " VND, Ngày: {$o->created_at}\n";
        }

        // ===== PROMPT =====
        $systemPrompt = <<<EOT
Bạn là trợ lý ảo của cửa hàng TechStore.
- Bạn chỉ được giới thiệu sản phẩm từ danh sách dưới đây.
- Tuyệt đối KHÔNG được bịa thêm sản phẩm ngoài danh sách.
- Nếu danh sách trống, hãy nói rằng hiện tại không có sản phẩm phù hợp.
- Khi giới thiệu sản phẩm hoặc đơn hàng, dùng markdown link hoặc danh sách rõ ràng.
- Nếu ai hỏi bạn là ai, luôn trả lời: "Tôi là trợ lý ảo của cửa hàng TechStore, sẵn sàng hỗ trợ bạn!"

Danh sách sản phẩm gợi ý (lấy từ cơ sở dữ liệu):
$productsList

Đơn hàng của khách:
$ordersList

Câu hỏi của khách:
EOT;

        $finalPrompt = $systemPrompt . "\n" . $userMessage;

        // ===== GỌI GOOGLE AI =====
        try {
            $response = Http::timeout(30)->post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $finalPrompt]
                        ]
                    ]
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Lỗi từ Google AI API:', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json(['error' => 'AI tạm thời không phản hồi.'], 502);
            }

            $reply = data_get($response->json(), 'candidates.0.content.parts.0.text')
                ?? data_get($response->json(), 'candidates.0.output_text');

            if ($reply) {
                return response()->json(['reply' => $reply]);
            } else {
                Log::warning('Phản hồi AI không hợp lệ.', [
                    'response_body' => $response->body()
                ]);
                return response()->json(['error' => 'AI trả về phản hồi không hợp lệ.'], 500);
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Lỗi kết nối đến Google AI API: ' . $e->getMessage());
            return response()->json(['error' => 'Không thể kết nối đến dịch vụ AI.'], 504);
        } catch (\Exception $e) {
            Log::error('Lỗi không xác định trong ChatController: ' . $e->getMessage());
            return response()->json(['error' => 'Đã có lỗi hệ thống xảy ra.'], 500);
        }
    }
}
