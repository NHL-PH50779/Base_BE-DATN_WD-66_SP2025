<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;

class CommentController extends Controller
{
    public function index()
    {
        return response()->json(Comment::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'content' => 'required|string',
        ]);

        $comment = Comment::create($data);
        return response()->json($comment, 201);
    }

    public function show($id)
    {
        return response()->json(Comment::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $comment->update($request->only('content'));
        return response()->json($comment);
    }

    public function destroy($id)
{
    $deleted = Comment::destroy($id);

    if ($deleted) {
        return response()->json([
            'message' => 'Xóa bình luận thành công.'
        ], 200);
    } else {
        return response()->json([
            'message' => 'Không tìm thấy bình luận để xóa.'
        ], 404);
    }
}

}
