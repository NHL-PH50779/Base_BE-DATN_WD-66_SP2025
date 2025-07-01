<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_belongs_to_user()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $wishlist = Wishlist::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id
        ]);

        $this->assertInstanceOf(User::class, $wishlist->user);
        $this->assertEquals($user->id, $wishlist->user->id);
    }

    public function test_wishlist_belongs_to_product()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $wishlist = Wishlist::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id
        ]);

        $this->assertInstanceOf(Product::class, $wishlist->product);
        $this->assertEquals($product->id, $wishlist->product->id);
    }

    public function test_user_can_have_multiple_wishlist_items()
    {
        $user = User::factory()->create();
        $products = Product::factory()->count(3)->create();
        
        foreach ($products as $product) {
            Wishlist::factory()->create([
                'user_id' => $user->id,
                'product_id' => $product->id
            ]);
        }

        $this->assertEquals(3, $user->wishlists()->count());
    }
}