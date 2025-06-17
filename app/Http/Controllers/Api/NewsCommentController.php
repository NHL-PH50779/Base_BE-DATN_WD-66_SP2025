<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NewsComment;

class NewsCommentController extends Controller
{
    public function index()
    {
        return response()->json(NewsComment::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'news_id' => 'required|exists:news,id',
            'content' => 'required|string',
        ]);

        $newsComment = NewsComment::create($data);
        return response()->json($newsComment, 201);
    }

    public function show($id)
    {
        return response()->json(NewsComment::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $newsComment = NewsComment::findOrFail($id);
        $newsComment->update($request->only('content'));
        return response()->json($newsComment);
    }

    public function destroy($id)
{
    $deleted = NewsComment::destroy($id);

    if ($deleted) {
        return response()->json([
            'message' => 'Xóa bình luận tin tức thành công.'
        ], 200);
    } else {
        return response()->json([
            'message' => 'Không tìm thấy bình luận tin tức để xóa.'
        ], 404);
    }
}

}
