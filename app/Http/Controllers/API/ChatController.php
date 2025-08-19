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

        if (preg_match('/(\d+)\s?triệu\s?(?:-|đến|tới)\s?(\d+)\s?triệu/i', $userMessage, $matches)) {
            $minPrice = (int) $matches[1] * 1000000;
            $maxPrice = (int) $matches[2] * 1000000;
            $productsArr = DB::table('products')
                ->whereBetween('price', [$minPrice, $maxPrice])
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->select('id', 'name', 'price')
                ->limit(10)
                ->get();
        } elseif (preg_match('/dưới\s?(\d+)\s?triệu/i', $userMessage, $matches)) {
            $maxPrice = (int) $matches[1] * 1000000;
            $productsArr = DB::table('products')
                ->where('price', '<=', $maxPrice)
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->select('id', 'name', 'price')
                ->limit(10)
                ->get();
        } elseif (preg_match('/trên\s?(\d+)\s?triệu/i', $userMessage, $matches)) {
            $minPrice = (int) $matches[1] * 1000000;
            $productsArr = DB::table('products')
                ->where('price', '>=', $minPrice)
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->select('id', 'name', 'price')
                ->limit(10)
                ->get();
        }

        $productsList = '';
        foreach ($productsArr as $p) {
            $productsList .= "- [{$p->name}](http://your-domain.com/api/products/{$p->id}) - Giá: " . number_format($p->price, 0, ',', '.') . " VND\n";
        }

        // ===== XỬ LÝ ĐƠN HÀNG =====
        $ordersArr = collect();
        $ordersList = '';

        if (preg_match('/đơn hàng\s?#?(\d+)/i', $userMessage, $matches)) {
            // Nếu user hỏi kèm mã đơn
            $orderId = (int) $matches[1];
            $ordersArr = DB::table('orders')
                ->where('id', $orderId)
                ->where('user_id', $request->user()->id ?? null)
                ->select('id', 'status', 'total_price', 'created_at')
                ->get();
        } elseif (preg_match('/đơn hàng|order/i', $userMessage)) {
            // Nếu chỉ hỏi "đơn hàng của tôi"
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
            $ordersList .= "- Đơn #{$o->id}, Trạng thái: {$o->status}, Tổng: " . number_format($o->total, 0, ',', '.') . " VND, Ngày: {$o->created_at}\n";
        }

        // ===== PROMPT =====
        $systemPrompt = <<<EOT
Bạn là trợ lý ảo của cửa hàng TechStore.
- Hỗ trợ khách hàng về sản phẩm và đơn hàng.
- Nếu ai hỏi bạn là ai, luôn trả lời: "Tôi là trợ lý ảo của cửa hàng TechStore, sẵn sàng hỗ trợ bạn!"
- Không trả lời các câu hỏi nhạy cảm ngoài phạm vi.
- Khi giới thiệu sản phẩm hoặc đơn hàng, dùng markdown link hoặc danh sách rõ ràng.

Sản phẩm gợi ý:
$productsList

Đơn hàng của khách:
$ordersList

Câu hỏi của khách:
EOT;

        $finalPrompt = $systemPrompt . "\n" . $userMessage;

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
