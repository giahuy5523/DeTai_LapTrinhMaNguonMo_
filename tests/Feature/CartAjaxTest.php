<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAjaxTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create([
            'role' => 'customer',
        ]);

        $category = Category::create([
            'name' => 'Sneakers',
            'slug' => 'sneakers',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Nike Air Force 1',
            'slug' => 'nike-air-force-1',
            'price' => 1200000,
            'quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_ajax_add_to_cart_success_returns_json(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/gio-hang/them/' . $this->product->id, [
                'quantity' => 2,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'cart_count' => 2,
                'product_name' => 'Nike Air Force 1',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'cart_count',
                'cart_total',
                'product_name',
            ]);

        $cart = session('cart');
        $this->assertIsArray($cart);
        $this->assertEquals(2, $cart[$this->product->id]['quantity']);
    }

    public function test_ajax_add_to_cart_accumulates_quantity(): void
    {
        $this->actingAs($this->customer)
            ->postJson('/gio-hang/them/' . $this->product->id, ['quantity' => 1]);

        $secondResponse = $this->actingAs($this->customer)
            ->postJson('/gio-hang/them/' . $this->product->id, ['quantity' => 2]);

        $secondResponse->assertOk()
            ->assertJson([
                'success' => true,
                'cart_count' => 3,
            ]);

        $cart = session('cart');
        $this->assertEquals(3, $cart[$this->product->id]['quantity']);
    }

    public function test_ajax_add_to_cart_exceeds_stock_returns_422(): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson('/gio-hang/them/' . $this->product->id, [
                'quantity' => 99,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Số lượng vượt quá tồn kho.',
            ]);
    }

    public function test_ajax_cumulative_quantity_exceeding_stock_returns_422(): void
    {
        $this->actingAs($this->customer)
            ->postJson('/gio-hang/them/' . $this->product->id, ['quantity' => 4])
            ->assertOk();

        $response = $this->actingAs($this->customer)
            ->postJson('/gio-hang/them/' . $this->product->id, ['quantity' => 2]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Giỏ hàng vượt quá số lượng tồn kho.',
            ]);

        $this->assertEquals(4, session('cart')[$this->product->id]['quantity']);
    }

    public function test_layout_renders_ajax_toast_without_duplicate_form_submit_fallback(): void
    {
        $response = $this->actingAs($this->customer)->get('/san-pham');

        $response->assertOk()
            ->assertSee('id="navCartBadge"', false)
            ->assertSee('id="cartToast"', false)
            ->assertDontSee('form.submit()', false);
    }

    public function test_ajax_add_to_cart_requires_authentication(): void
    {
        $response = $this->postJson('/gio-hang/them/' . $this->product->id, [
            'quantity' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_standard_form_post_still_redirects_for_backward_compatibility(): void
    {
        $response = $this->actingAs($this->customer)
            ->post('/gio-hang/them/' . $this->product->id, [
                'quantity' => 1,
            ]);

        $response->assertRedirect('/gio-hang');
        $response->assertSessionHas('success');
    }
}
