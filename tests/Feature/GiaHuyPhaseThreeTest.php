<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\Gd3DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiaHuyPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_refuses_the_default_sqlite_test_database(): void
    {
        try {
            $this->seed(Gd3DemoSeeder::class);
            $this->fail('Demo seeder must refuse any database other than the isolated GD3 MySQL database.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sneakershop_gd3_test', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('products', 0);
        }
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gd3'], ['name' => 'GD3']);

        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'GD3 shoe',
            'slug' => 'gd3-shoe', 'price' => 100000, 'quantity' => 5,
            'is_active' => true,
        ], $attributes));
    }

    private function receiver(): array
    {
        return $this->checkoutFields([
            'customer_name' => 'GD3 customer', 'phone' => '0900000000',
            'address' => 'Dia chi kiem thu', 'payment_method' => 'cod',
        ]);
    }

    public function test_checkout_uses_latest_database_price_instead_of_session_price(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $product->update(['price' => 150000]);

        $this->post(route('checkout.store'), $this->receiver())
            ->assertRedirect(route('checkout.success'))->assertSessionMissing('cart');

        $this->assertDatabaseHas('orders', ['total_amount' => 300000]);
        $this->assertDatabaseHas('order_items', ['unit_price' => 150000, 'subtotal' => 300000]);
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_unavailable_second_product_rolls_back_entire_checkout(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $first = $this->product();
        $second = $this->product(['name' => 'Second', 'slug' => 'second']);
        foreach ([$first, $second] as $product) {
            $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        }
        $second->update(['is_active' => false]);

        $this->post(route('checkout.store'), $this->receiver())
            ->assertSessionHasErrors('cart')->assertSessionHas('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(5, $first->fresh()->quantity);
        $this->assertSame(5, $second->fresh()->quantity);
    }

    public function test_deleted_product_cannot_be_checked_out(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 1]);
        $product->delete();

        $this->post(route('checkout.store'), $this->receiver())
            ->assertSessionHasErrors('cart')->assertSessionHas('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_success_page_and_recent_orders_do_not_expose_another_customers_order(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $this->actingAs($owner);
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->post(route('checkout.store'), $this->receiver())->assertRedirect();
        $order = Order::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->withSession(['last_checkout_order_id' => $order->id])
            ->get(route('checkout.success'))->assertRedirect(route('store.index'));
        $this->get(route('cart.index'))->assertViewHas('recentOrders', fn ($orders) => $orders->isEmpty());
    }

    public function test_invalid_checkout_preserves_stock_and_cart(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['payment_method' => 'online'])
            ->assertSessionHasErrors(['customer_name', 'phone', 'address', 'payment_method'])
            ->assertSessionHas('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
    }
}
