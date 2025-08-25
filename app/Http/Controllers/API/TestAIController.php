<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TestAIController extends Controller
{
    public function testAI(Request $request)
    {
        $message = $request->input('message', 'Xin chào');
        
        $aiService = new AIService();
        $response = $aiService->chat($message);
        
        return response()->json([
            'test_message' => $message,
            'ai_response' => $response,
            'config' => [
                'api_key_set' => !empty(config('services.google_ai.api_key')),
                'ai_enabled' => config('services.google_ai.enabled'),
                'model' => config('services.google_ai.model')
            ]
        ]);
    }
    
    public function testGeminiDirect(Request $request)
    {
        $apiKey = config('services.google_ai.api_key');
        $message = $request->input('message', 'Hello, how are you?');
        
        if (!$apiKey || $apiKey === 'AIzaSyDummy_Key_For_Testing_Replace_With_Real_Key') {
            return response()->json([
                'error' => 'No valid API key configured',
                'message' => 'Please set GOOGLE_AI_API_KEY in .env file'
            ], 400);
        }
        
        try {
            $response = Http::timeout(10)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}",
                [
                    'contents' => [['parts' => [['text' => $message]]]]
                ]
            );
            
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Gemini API failed',
                    'status' => $response->status(),
                    'body' => $response->body()
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'input' => $message,
                'response' => $response->json()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Exception occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}