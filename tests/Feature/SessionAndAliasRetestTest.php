<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\CartSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionAndAliasRetestTest extends TestCase
{
    use RefreshDatabase;

    private function shoe(): Product
    {
        $category = Category::create(['name' => 'Alias test', 'slug' => 'alias-test']);

        return Product::create(['name' => 'Alias shoe', 'slug' => 'alias-shoe', 'category_id' => $category->id,
            'price' => '10.00', 'quantity' => 5, 'is_active' => true]);
    }

    private function receiver(): array
    {
        return ['customer_name' => 'Alias buyer', 'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod'];
    }

    public function test_alternative_numeric_route_id_cannot_sell_more_than_product_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->post('/gio-hang/them/'.$product->id, ['quantity' => 3])->assertRedirect('/gio-hang');
        $this->post('/gio-hang/them/0'.$product->id, ['quantity' => 3]);
        $this->post('/checkout', $this->checkoutFields($this->receiver()));
        $this->assertGreaterThanOrEqual(0, $product->fresh()->quantity, 'A single product must not be validated twice against the same initial stock');
        $this->assertLessThanOrEqual(5, (int) OrderItem::sum('quantity'));
    }

    public function test_logout_removes_cart_quote_and_last_order_before_another_user_login(): void
    {
        $first = User::factory()->create(['role' => 'customer']);
        $second = User::factory()->create(['role' => 'customer', 'password' => Hash::make('password123')]);
        $this->actingAs($first)->withSession(['cart' => [1 => ['quantity' => 2]],
            'checkout_quote' => ['version' => 'old'], 'last_checkout_order_id' => 999])->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $second->email, 'password' => 'password123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($second);
        foreach (['cart', 'checkout_quote', 'last_checkout_order_id'] as $key) {
            $this->assertNull(session($key));
        }
    }

    public function test_cancelled_order_replay_does_not_reopen_order_or_reserve_stock_again(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->post('/gio-hang/them/'.$product->id);
        $fields = $this->checkoutFields($this->receiver());
        $snapshot = ['cart' => session('cart'), 'checkout_quote' => session('checkout_quote')];
        $this->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $order = Order::firstOrFail();
        $this->patch(route('orders.cancel', $order->id))->assertRedirect('/gio-hang');
        $this->withSession($snapshot)->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->get(route('checkout.success'))->assertOk()->assertSee('Đã hủy');
    }

    public function test_csrf_rejection_prevents_cart_and_order_mutations(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->app->instance('env', 'local'); // Enable real CSRF middleware in this test request.
        $this->post('/gio-hang/them/'.$product->id, ['quantity' => 1])->assertStatus(419);
        $this->post('/checkout', $this->receiver())->assertStatus(419);
        $this->assertNull(session('cart'));
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_numeric_alias_add_update_and_remove_use_one_canonical_key(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        foreach (['', '0', '000'] as $prefix) {
            $this->post('/gio-hang/them/'.$prefix.$product->id, ['quantity' => 1])->assertRedirect('/gio-hang');
        }
        $this->assertSame([$product->id], array_keys(session('cart')));
        $this->assertSame(3, session('cart.'.$product->id.'.quantity'));
        $this->patch('/gio-hang/cap-nhat/00'.$product->id, ['quantity' => 2])->assertRedirect('/gio-hang');
        $this->assertSame(2, session('cart.'.$product->id.'.quantity'));
        $this->delete('/gio-hang/xoa/000'.$product->id)->assertRedirect('/gio-hang');
        $this->assertSame([], session('cart'));
    }

    public function test_malformed_ids_cannot_use_database_numeric_coercion(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        foreach ([$product->id.'e0', $product->id.'.0', '+'.$product->id, $product->id.'abc',
            '-'.$product->id, '0', '9223372036854775808', '0x1'] as $id) {
            $encoded = rawurlencode($id);
            $this->post('/gio-hang/them/'.$encoded, ['quantity' => 1])->assertNotFound();
            $this->patch('/gio-hang/cap-nhat/'.$encoded, ['quantity' => 1])->assertNotFound();
            $this->delete('/gio-hang/xoa/'.$encoded)->assertNotFound();
        }
        $this->assertNull(session('cart'));
        $this->assertSame(5, $product->fresh()->quantity);
    }

    public function test_legacy_duplicate_cart_merges_into_one_order_item(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->withSession(['cart' => [$product->id => ['quantity' => 2], '0'.$product->id => ['quantity' => 2]]]);
        $fields = $this->checkoutFields($this->receiver());
        $this->assertCount(1, session('cart'));
        $this->assertSame(4, session('cart.'.$product->id.'.quantity'));
        $this->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['quantity' => 4, 'subtotal' => '40.00']);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_legacy_cart_overstock_can_be_reduced_before_checkout(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->withSession(['cart' => [$product->id => ['quantity' => 3], '0'.$product->id => ['quantity' => 3]]]);
        $this->post('/checkout', $this->checkoutFields($this->receiver()))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->patch('/gio-hang/cap-nhat/0'.$product->id, ['quantity' => 5])->assertRedirect('/gio-hang');
        $this->post('/checkout', $this->checkoutFields($this->receiver()))->assertRedirect(route('checkout.success'));
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_direct_post_of_legacy_duplicate_quote_requires_new_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $cart = [$product->id => ['quantity' => 3, 'price' => '10.00'],
            '0'.$product->id => ['quantity' => 3, 'price' => '10.00']];
        $token = str_repeat('c', 64);
        $this->withSession(['cart' => $cart, 'checkout_quote' => ['version' => $token,
            'snapshot' => CartSnapshot::version($cart)]])->post('/checkout', $this->receiver() + ['checkout_version' => $token])
            ->assertSessionHasErrors('cart')->assertSessionMissing('checkout_quote');
        $this->assertSame([$product->id], array_keys(session('cart')));
        $this->assertSame(6, session('cart.'.$product->id.'.quantity'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
    }

    public function test_oversized_legacy_quantity_is_editable_without_integer_overflow(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->withSession(['cart' => [$product->id => ['quantity' => PHP_INT_MAX],
            '0'.$product->id => ['quantity' => PHP_INT_MAX]]])->get('/gio-hang')->assertOk();
        $this->assertSame(PHP_INT_MAX, session('cart.'.$product->id.'.quantity'));
        $this->get('/checkout')->assertRedirect('/gio-hang');
        $this->patch('/gio-hang/cap-nhat/'.$product->id, ['quantity' => 1])->assertRedirect('/gio-hang');
        $this->post('/checkout', $this->checkoutFields($this->receiver()))->assertRedirect(route('checkout.success'));
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_legacy_deleted_item_with_missing_metadata_can_be_removed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->withSession(['cart' => ['000999' => ['quantity' => 1]]])->get('/gio-hang')->assertOk();
        $this->delete('/gio-hang/xoa/0999')->assertRedirect('/gio-hang');
        $this->assertSame([], session('cart'));
    }
}
