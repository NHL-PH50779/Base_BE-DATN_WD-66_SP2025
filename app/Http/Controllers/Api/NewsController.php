<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index()
    {
        try {
            $news = News::where('is_active', true)
                ->orderBy('published_at', 'desc')
                ->select('id', 'title', 'description', 'thumbnail', 'published_at')
                ->paginate(10);
            
            return response()->json([
                'success' => true,
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy danh sách tin tức'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $news = News::where('is_active', true)->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tin tức'
            ], 404);
        }
    }

    public function adminIndex()
    {
        try {
            $news = News::orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'message' => 'Danh sách tin tức admin',
                'data' => [
                    'data' => $news,
                    'total' => $news->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách tin tức',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $news = News::create([
                'title' => $request->title,
                'description' => $request->description,
                'content' => $request->content,
                'thumbnail' => $request->thumbnail ?? '',
                'is_active' => $request->is_active ?? true,
                'published_at' => now()
            ]);

            return response()->json([
                'message' => 'Tạo tin tức thành công',
                'data' => $news
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo tin tức',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $news = News::findOrFail($id);
            
            $news->update([
                'title' => $request->title,
                'description' => $request->description,
                'content' => $request->content,
                'thumbnail' => $request->thumbnail ?? '',
                'is_active' => $request->is_active ?? true
            ]);

            return response()->json([
                'message' => 'Cập nhật tin tức thành công',
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật tin tức',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $news = News::findOrFail($id);
            $news->delete();

            return response()->json([
                'message' => 'Xóa tin tức thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa tin tức',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
