<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create();
    }

    public function test_user_can_add_product_to_wishlist()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/wishlist/toggle', [
                'product_id' => $this->product->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Đã thêm vào danh sách yêu thích',
                'is_favorited' => true
            ]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);
    }

    public function test_user_can_remove_product_from_wishlist()
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/wishlist/toggle', [
                'product_id' => $this->product->id
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Đã xóa khỏi danh sách yêu thích',
                'is_favorited' => false
            ]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);
    }

    public function test_user_can_get_wishlist()
    {
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/wishlist');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'product_id',
                        'product' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ]);
    }

    public function test_unauthenticated_user_cannot_access_wishlist()
    {
        $response = $this->getJson('/api/wishlist');
        $response->assertStatus(401);
    }
}