<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    // Lấy danh sách tất cả tin tức, có phân trang (mặc định 10 tin)
    public function index()
    {
        $newsList = News::orderBy('created_at', 'desc')->paginate(10);
        return response()->json([
            'status' => 'success',
            'data' => $newsList
        ]);
    }

    // Tạo mới tin tức
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:news,title',
            'content' => 'required|string',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // ảnh <= 2MB
        ]);

        try {
            if ($request->hasFile('thumbnail')) {
                $path = $request->file('thumbnail')->store('news_thumbnails', 'public');
                $validated['thumbnail'] = $path;
            }

            $news = News::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'News created successfully',
                'data' => $news
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create news',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Xem chi tiết một tin tức
    public function show($id)
    {
        try {
            $news = News::findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'News not found'
            ], 404);
        }
    }

    // Cập nhật tin tức
    public function update(Request $request, $id)
    {
        try {
            $news = News::findOrFail($id);

            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255|unique:news,title,' . $news->id,
                'content' => 'sometimes|required|string',
                'thumbnail' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($request->hasFile('thumbnail')) {
                // Xóa ảnh cũ nếu có
                if ($news->thumbnail && Storage::disk('public')->exists($news->thumbnail)) {
                    Storage::disk('public')->delete($news->thumbnail);
                }

                $path = $request->file('thumbnail')->store('news_thumbnails', 'public');
                $validated['thumbnail'] = $path;
            }

            $news->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'News updated successfully',
                'data' => $news
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update news',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Xóa tin tức
    public function destroy($id)
    {
        try {
            $news = News::findOrFail($id);

            // Xóa ảnh thumbnail nếu có
            if ($news->thumbnail && Storage::disk('public')->exists($news->thumbnail)) {
                Storage::disk('public')->delete($news->thumbnail);
            }

            $news->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Tin được xóa thành công',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Xóa tin không thành công',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
