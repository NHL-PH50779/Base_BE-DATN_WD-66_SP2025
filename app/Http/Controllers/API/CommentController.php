<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $productId = $request->get('product_id');
        
        $query = Comment::with('user:id,name')
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc');
            
        if ($productId) {
            $query->where('product_id', $productId);
        }
        
        $comments = $query->get();
        
        return response()->json([
            'data' => $comments
        ]);
    }

    public function getProductRatingStats($productId)
    {
        $comments = Comment::where('product_id', $productId)
            ->where('status', 'approved')
            ->get();
            
        $totalReviews = $comments->count();
        
        if ($totalReviews === 0) {
            return response()->json([
                'data' => [
                    'average_rating' => 0,
                    'total_reviews' => 0,
                    'rating_breakdown' => [
                        5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0
                    ],
                    'percentage_breakdown' => [
                        5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0
                    ]
                ]
            ]);
        }
        
        $averageRating = $comments->avg('rating');
        
        $ratingBreakdown = [
            5 => $comments->where('rating', 5)->count(),
            4 => $comments->where('rating', 4)->count(),
            3 => $comments->where('rating', 3)->count(),
            2 => $comments->where('rating', 2)->count(),
            1 => $comments->where('rating', 1)->count(),
        ];
        
        $percentageBreakdown = [];
        foreach ($ratingBreakdown as $star => $count) {
            $percentageBreakdown[$star] = round(($count / $totalReviews) * 100, 1);
        }
        
        return response()->json([
            'data' => [
                'average_rating' => round($averageRating, 1),
                'total_reviews' => $totalReviews,
                'rating_breakdown' => $ratingBreakdown,
                'percentage_breakdown' => $percentageBreakdown
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'content' => 'required|string|max:1000',
            'rating' => 'required|integer|min:0|max:5'
        ]);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'product_id' => $request->product_id,
            'content' => $request->content,
            'rating' => $request->rating,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Bình luận đã được gửi và đang chờ duyệt',
            'data' => $comment->load('user:id,name')
        ], 201);
    }

    // Admin methods
    public function adminIndex()
    {
        $comments = Comment::with(['user:id,name', 'product:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'data' => $comments
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected'
        ]);

        $comment = Comment::findOrFail($id);
        $comment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'data' => $comment->load(['user:id,name', 'product:id,name'])
        ]);
    }

    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return response()->json([
            'message' => 'Xóa bình luận thành công'
        ]);
    }
}