<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Gd2IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'audit'], ['name' => 'Audit']);

        return Product::create(['category_id' => $category->id, 'name' => 'Shoe '.Product::count(), 'slug' => 'audit-'.Product::count(), 'price' => 100000, 'quantity' => 5, 'is_active' => true]);
    }

    private function checkout(string $method = 'cod'): array
    {
        $user = User::factory()->create(['role' => 'customer']);
        $product = $this->product();
        $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 2])->assertRedirect(route('cart.index'));
        $this->post(route('checkout.store'), $this->checkoutFields(['customer_name' => 'Audit', 'phone' => '0900000000', 'address' => 'Audit address', 'payment_method' => $method]))
            ->assertRedirect(route('checkout.success'))->assertSessionMissing('cart');
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame($method, $order->payment_method);
        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertEquals(200000, $order->total_amount);

        return [$user, $product, $order];
    }

    public function test_cod_and_transfer_checkout_and_success_views(): void
    {
        foreach (['cod', 'bank_transfer'] as $method) {
            [$user,$product,$order] = $this->checkout($method);
            $this->get(route('checkout.success'))->assertOk()->assertSee((string) $order->id);
            $this->get(route('cart.index'))->assertOk()->assertViewHas('recentOrders', fn ($orders) => $orders->contains('id', $order->id));
        }
    }

    public function test_admin_valid_transitions_and_terminal_states(): void
    {
        [$user,$product,$order] = $this->checkout();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $url = route('admin.orders.updateStatus', $order);
        $this->patch($url, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);
        $this->patch($url, ['status' => 'processing'])->assertRedirect();
        $this->patch($url, ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->assertSame('processing', $order->fresh()->status);
        $this->patch($url, ['status' => 'completed'])->assertRedirect();
        $this->assertSame('completed', $order->fresh()->status);
        $this->patch($url, ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_admin_cancellation_restores_stock_once_and_prevents_reopening(): void
    {
        [$user,$product,$order] = $this->checkout();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $url = route('admin.orders.updateStatus', $order);
        $this->patch($url, ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(5, $product->fresh()->quantity);
        foreach (['cancelled', 'pending', 'processing', 'completed'] as $status) {
            $this->patch($url, ['status' => $status])->assertSessionHasErrors('status');
            $this->assertSame(5, $product->fresh()->quantity);
            $this->assertSame('cancelled', $order->fresh()->status);
        }
    }

    public function test_customer_cancellation_is_owner_only_and_stock_is_restored_once(): void
    {
        [$user,$product,$order] = $this->checkout();
        $url = route('orders.cancel', $order->id);
        $this->actingAs(User::factory()->create(['role' => 'customer']))->patch($url)->assertNotFound();
        $this->actingAs($user)->patch($url)->assertRedirect(route('cart.index'));
        $this->assertSame(5, $product->fresh()->quantity);
        $this->patch($url)->assertStatus(422);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->get(route('cart.index'))->assertViewHas('recentOrders', fn ($orders) => ! $orders->contains('id', $order->id));
    }

    public function test_out_of_stock_checkout_does_not_create_order_or_clear_cart(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $product->update(['quantity' => 1]);
        $this->post(route('checkout.store'), $this->checkoutFields(['customer_name' => 'Audit', 'phone' => '0900000000', 'address' => 'Audit address', 'payment_method' => 'cod']))
            ->assertSessionHasErrors('cart')->assertSessionHas('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_success_page_reflects_latest_order_status(): void
    {
        [$user,$product,$order] = $this->checkout();
        $this->patch(route('orders.cancel', $order->id))->assertRedirect();
        foreach ([
            'cancelled' => 'Đã hủy',
            'processing' => 'Đang xử lý',
            'completed' => 'Đã hoàn thành',
            'pending' => 'Chờ xử lý',
        ] as $status => $label) {
            $order->update(['status' => $status]);
            $this->get(route('checkout.success'))->assertOk()->assertSee($label);
        }
    }

    public function test_success_flash_is_displayed_once_after_layout_integration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['admin.dashboard', 'admin.products.index', 'admin.categories.index', 'admin.orders.index'] as $route) {
            $response = $this->actingAs($admin)->withSession(['success' => 'Unique flash audit'])->get(route($route));
            $response->assertOk();
            $this->assertSame(1, substr_count($response->getContent(), 'Unique flash audit'));
        }
        $customer = User::factory()->create(['role' => 'customer']);
        foreach (['cart.index', 'store.index'] as $route) {
            $response = $this->actingAs($customer)->withSession(['success' => 'Unique flash audit'])->get(route($route));
            $response->assertOk();
            $this->assertSame(1, substr_count($response->getContent(), 'Unique flash audit'));
        }
    }
}
