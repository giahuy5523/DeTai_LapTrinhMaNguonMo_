<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionLifecycleRetestTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): Product
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $category = Category::create(['name' => 'Lifecycle', 'slug' => 'lifecycle']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Lifecycle shoe',
            'slug' => 'lifecycle-shoe', 'price' => '12.50', 'quantity' => 5, 'is_active' => true]);
        $this->post('/gio-hang/them/'.$product->id);

        return $product;
    }

    private function receiver(): array
    {
        return ['customer_name' => 'Lifecycle buyer', 'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod'];
    }

    public function test_login_rotates_session_id_and_csrf_token(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => Hash::make('password123')]);
        $this->withSession(['guest_marker' => 'retained'])->get('/login')->assertOk();
        $sessionId = session()->getId();
        $csrf = session()->token();
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($csrf, session()->token());
        $this->assertSame('retained', session('guest_marker'));
    }

    public function test_logout_rotates_remember_token_and_clears_authentication(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => Hash::make('password123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password123', 'remember' => 'on'])
            ->assertRedirect('/')->assertCookie(auth()->guard()->getRecallerName());
        $before = $user->fresh()->remember_token;
        $this->assertNotEmpty($before);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNotSame($before, $user->fresh()->remember_token);
        $this->get('/gio-hang')->assertRedirect('/login');
    }

    public function test_two_checkout_tabs_reject_stale_confirmation_without_consuming_latest(): void
    {
        $product = $this->prepare();
        $first = $this->checkoutFields($this->receiver());
        $second = $this->checkoutFields($this->receiver());
        $this->assertNotSame($first['checkout_version'], $second['checkout_version']);
        $this->post('/checkout', $first)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame($second['checkout_version'], session('checkout_quote.version'));
        $this->post('/checkout', $second)->assertRedirect(route('checkout.success'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_cart_change_in_another_tab_invalidates_both_old_forms(): void
    {
        $product = $this->prepare();
        $first = $this->checkoutFields($this->receiver());
        $second = $this->checkoutFields($this->receiver());
        $this->patch('/gio-hang/cap-nhat/'.$product->id, ['quantity' => 2])->assertRedirect('/gio-hang');
        foreach ([$first, $second] as $fields) {
            $this->post('/checkout', $fields)->assertSessionHasErrors('cart');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->post('/checkout', $this->checkoutFields($this->receiver()))->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('order_items', ['quantity' => 2, 'subtotal' => '25.00']);
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_replay_after_product_deletion_returns_existing_order_snapshot(): void
    {
        $product = $this->prepare();
        $fields = $this->checkoutFields($this->receiver());
        $session = ['cart' => session('cart'), 'checkout_quote' => session('checkout_quote')];
        $this->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $orderId = session('last_checkout_order_id');
        $product->delete();
        $this->withSession($session)->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $this->assertSame($orderId, session('last_checkout_order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['product_id' => null, 'product_name' => 'Lifecycle shoe']);
        $this->get(route('checkout.success'))->assertOk();
    }

    public function test_product_disabled_after_quote_cannot_be_reenabled_by_checkout_payload(): void
    {
        $product = $this->prepare();
        $fields = $this->checkoutFields($this->receiver());
        $product->update(['is_active' => false]);
        $this->post('/checkout', $fields + ['is_active' => true])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertFalse((bool) $product->fresh()->is_active);
    }

    public function test_stock_exhaustion_then_restock_requires_valid_stock_before_purchase(): void
    {
        $product = $this->prepare();
        $fields = $this->checkoutFields($this->receiver());
        $product->update(['quantity' => 0]);
        $this->post('/checkout', $fields)->assertSessionHasErrors('cart');
        $this->get('/gio-hang')->assertOk()->assertDontSee('Tiến hành thanh toán');
        $this->assertDatabaseCount('orders', 0);
        $product->refresh()->update(['quantity' => 1]);
        $this->post('/checkout', $this->checkoutFields($this->receiver()))->assertRedirect(route('checkout.success'));
        $this->assertSame(0, $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_receiver_text_is_escaped_after_order_persistence(): void
    {
        $this->prepare();
        $payload = '<script>alert(1)</script>';
        $fields = $this->checkoutFields(array_replace($this->receiver(), ['customer_name' => $payload,
            'address' => $payload, 'note' => $payload]));
        $this->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('orders', ['customer_name' => $payload, 'note' => $payload]);
        foreach (['/gio-hang', route('checkout.success')] as $url) {
            $this->get($url)->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/orders')->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
    }
}
