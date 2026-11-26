<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => 'customer']);
        $category = Category::create(['name' => 'Reservations', 'slug' => 'reservations']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Last pair', 'slug' => 'last-pair',
            'price' => '10.25', 'quantity' => 1, 'is_active' => true]);
        return [$user, $product, [$product->id => ['quantity' => 1, 'price' => '10.25']]];
    }

    public function test_reload_reuses_hold_without_extending_expiry(): void
    {
        [$user, $product, $cart] = $this->fixture();
        $service = new StockReservationService;
        $token = str_repeat('a', 64);
        $hold = $service->reserve($user->id, $token, $cart);
        $this->travel(30)->seconds();
        $reused = $service->reserve($user->id, $token, $cart);
        $this->assertSame($hold['expires_at'], $reused['expires_at']);
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_other_customer_cannot_reserve_last_pair(): void
    {
        [$user, $product, $cart] = $this->fixture();
        $service = new StockReservationService;
        $service->reserve($user->id, str_repeat('a', 64), $cart);
        $other = User::factory()->create(['role' => 'customer']);
        $this->expectException(ValidationException::class);
        $service->reserve($other->id, str_repeat('b', 64), $cart);
    }

    public function test_exact_expiry_rejects_consumption_and_releases_availability_without_cleanup(): void
    {
        [$user, $product, $cart] = $this->fixture();
        $service = new StockReservationService;
        $hold = $service->reserve($user->id, str_repeat('a', 64), $cart);
        $this->travelTo(\Illuminate\Support\Carbon::parse($hold['expires_at']));
        try {
            $service->consume($user->id, str_repeat('a', 64), $cart, fn () => $product->decrement('quantity'));
            $this->fail('Expired reservation consumed');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }
        $other = User::factory()->create(['role' => 'customer']);
        $service->reserve($other->id, str_repeat('b', 64), $cart);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_failed_consumption_rolls_back_and_cancel_does_not_restock(): void
    {
        [$user, $product, $cart] = $this->fixture();
        $service = new StockReservationService;
        $token = str_repeat('a', 64);
        $service->reserve($user->id, $token, $cart);
        try {
            $service->consume($user->id, $token, $cart, function () use ($product) {
                $product->decrement('quantity');
                throw new \RuntimeException('Injected failure');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        }
        $this->assertSame(1, $product->fresh()->quantity);
        $service->cancel($user->id, $token);
        $service->cancel($user->id, $token);
        $this->assertSame('cancelled', DB::table('stock_reservations')->value('status'));
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_checkout_consumes_hold_and_expiry_never_restocks_cancelled_order(): void
    {
        [$user, $product] = $this->fixture();
        $this->actingAs($user);
        $this->post('/gio-hang/them/'.$product->id, ['quantity' => 1]);
        $fields = $this->checkoutFields(['customer_name' => 'Reservation QA', 'phone' => '0900000000',
            'address' => 'Test address', 'payment_method' => 'cod']);
        $this->post('/checkout', $fields)->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('stock_reservations', ['token' => $fields['checkout_version'], 'status' => 'consumed']);
        $this->assertSame(0, $product->fresh()->quantity);
        $order = \App\Models\Order::firstOrFail();
        $this->patch('/don-hang/'.$order->id.'/huy')->assertRedirect();
        $this->assertSame(1, $product->fresh()->quantity);
        $this->travel(6)->minutes();
        $this->assertSame(0, (new StockReservationService)->expire());
        $this->assertSame(1, $product->fresh()->quantity);
        $this->patch('/don-hang/'.$order->id.'/huy')->assertStatus(422);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_expired_checkout_post_cannot_create_an_order(): void
    {
        [$user, $product] = $this->fixture();
        $this->actingAs($user);
        $this->post('/gio-hang/them/'.$product->id, ['quantity' => 1]);
        $fields = $this->checkoutFields(['customer_name' => 'Expiry QA', 'phone' => '0900000000',
            'address' => 'Test address', 'payment_method' => 'cod']);
        $this->travel(5)->minutes();
        $this->post('/checkout', $fields)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->quantity);
    }
    public function test_multiple_products_roll_back_entire_hold_when_one_is_unavailable(): void
    {
        [$user, $product, $cart] = $this->fixture();
        $second = Product::create(['category_id' => $product->category_id, 'name' => 'Empty', 'slug' => 'empty',
            'price' => '20.00', 'quantity' => 0, 'is_active' => true]);
        $cart[$second->id] = ['quantity' => 1, 'price' => '20.00'];
        try {
            (new StockReservationService)->reserve($user->id, str_repeat('a', 64), $cart);
            $this->fail('Unavailable cart held');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_other_user_cannot_consume_or_cancel_a_hold(): void
    {
        [$user, $product, $cart] = $this->fixture();
        $other = User::factory()->create(['role' => 'customer']);
        $service = new StockReservationService;
        $token = str_repeat('a', 64);
        $service->reserve($user->id, $token, $cart);
        $service->cancel($other->id, $token);
        $this->assertDatabaseHas('stock_reservations', ['token' => $token, 'status' => 'active']);
        try {
            $service->consume($other->id, $token, $cart, fn () => $product->decrement('quantity'));
            $this->fail('Other customer consumed hold');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }
        $this->assertSame(1, $product->fresh()->quantity);
    }}
