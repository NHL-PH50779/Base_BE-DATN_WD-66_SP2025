<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use App\Services\WishlistCacheService;
use App\Services\AnalyticsService;
use App\Http\Requests\WishlistRequest;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    protected $cacheService;
    protected $analyticsService;

    public function __construct(
        WishlistCacheService $cacheService,
        AnalyticsService $analyticsService
    ) {
        $this->cacheService = $cacheService;
        $this->analyticsService = $analyticsService;
    }
    // Lấy danh sách sản phẩm yêu thích của user
    public function index()
    {
        $wishlists = $this->cacheService->getUserWishlist(auth()->id());

        return response()->json([
            'message' => 'Danh sách sản phẩm yêu thích',
            'data' => $wishlists
        ]);
    }

    // Thêm/xóa sản phẩm khỏi wishlist (toggle)
    public function toggle(WishlistRequest $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $userId = auth()->id();
        $productId = $request->product_id;

        $wishlist = Wishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($wishlist) {
            // Nếu đã tồn tại thì xóa
            $wishlist->delete();
            $this->cacheService->invalidateUserWishlist($userId);
            $this->analyticsService->trackWishlistAction($userId, $productId, 'remove');
            return response()->json([
                'message' => 'Đã xóa khỏi danh sách yêu thích',
                'is_favorited' => false
            ]);
        } else {
            // Nếu chưa tồn tại thì thêm
            Wishlist::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);
            $this->cacheService->invalidateUserWishlist($userId);
            $this->analyticsService->trackWishlistAction($userId, $productId, 'add');
            return response()->json([
                'message' => 'Đã thêm vào danh sách yêu thích',
                'is_favorited' => true
            ]);
        }
    }

    // Kiểm tra sản phẩm có được yêu thích không
    public function check(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $isFavorited = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->exists();

        return response()->json([
            'is_favorited' => $isFavorited
        ]);
    }

    // Xóa sản phẩm khỏi wishlist
    public function destroy($id)
    {
        $wishlist = Wishlist::where('user_id', auth()->id())
            ->where('id', $id)
            ->first();

        if (!$wishlist) {
            return response()->json([
                'message' => 'Không tìm thấy sản phẩm trong danh sách yêu thích'
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'message' => 'Đã xóa khỏi danh sách yêu thích'
        ]);
    }
}
