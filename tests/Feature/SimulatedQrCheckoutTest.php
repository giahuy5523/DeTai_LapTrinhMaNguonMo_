<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\SimulatedQr;
use App\Support\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimulatedQrCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $quantity = 3, string $price = '1250000.00'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'qr-test'], ['name' => 'QR Test']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'QR Test Sneaker '.Product::count(),
            'slug' => 'qr-test-sneaker-'.Product::count(),
            'price' => $price,
            'quantity' => $quantity,
            'is_active' => true,
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function receiverPayload(string $paymentMethod = 'bank_transfer', array $extra = []): array
    {
        return array_replace([
            'customer_name' => 'Phạm Minh Trọng',
            'phone' => '0901234567',
            'address' => '123 Nguyễn Trãi, Quận 5, TP.HCM',
            'note' => 'Giao giờ hành chính',
            'payment_method' => $paymentMethod,
        ], $extra);
    }

    public function test_qr_checkout_displays_simulated_qr_amount_reference_expiry_and_single_confirm_button(): void
    {
        $user = $this->customer();
        $product = $this->createProduct(3, '1250000.00');

        $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 2]);

        $response = $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))
            ->assertOk();

        $token = $response->viewData('checkoutVersion');
        $expiresLabel = $response->viewData('reservationExpiresLabel');
        $expectedReference = SimulatedQr::reference($token);

        $response->assertSee('<svg xmlns="http://www.w3.org/2000/svg"', false)
            ->assertSee('class="qr-demo-svg"', false)
            ->assertSee(Money::format('2500000.00').' đ')
            ->assertSee($expectedReference)
            ->assertSee($expiresLabel)
            ->assertSee('id="checkoutSubmitBtn"', false)
            ->assertSee('Xác nhận thanh toán mô phỏng')
            ->assertSee('MÔ PHỎNG HỌC TẬP — KHÔNG CHUYỂN TIỀN THẬT');

        $html = $response->getContent();
        $checkoutFormStart = strpos($html, 'action="'.route('checkout.store').'"');
        $this->assertNotFalse($checkoutFormStart);
        $checkoutFormEnd = strpos($html, '</form>', $checkoutFormStart);
        $checkoutFormHtml = substr($html, $checkoutFormStart, $checkoutFormEnd - $checkoutFormStart);
        $this->assertSame(1, substr_count($checkoutFormHtml, 'type="submit"'));
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_reload_preserves_reservation_token_reference_and_server_expiry_without_duplicate_holds(): void
    {
        $user = $this->customer();
        $product = $this->createProduct(2, '900000.00');

        $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 1]);

        $first = $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();
        $firstToken = $first->viewData('checkoutVersion');
        $firstExpiry = $first->viewData('reservationExpiresAt');
        $firstRef = $first->viewData('qrReference');

        $this->travel(90)->seconds();

        $second = $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();
        $this->assertSame($firstToken, $second->viewData('checkoutVersion'));
        $this->assertSame($firstExpiry, $second->viewData('reservationExpiresAt'));
        $this->assertSame($firstRef, $second->viewData('qrReference'));
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertDatabaseHas('stock_reservations', [
            'user_id' => $user->id,
            'token' => $firstToken,
            'status' => 'active',
        ]);
        $this->assertSame(2, $product->fresh()->quantity);
    }

    public function test_simulated_qr_confirmation_consumes_hold_creates_pending_order_and_separates_simulated_payment_status(): void
    {
        $user = $this->customer();
        $product = $this->createProduct(4, '1500000.00');

        $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 2]);

        $fields = $this->checkoutFields($this->receiverPayload('bank_transfer'));

        $this->post(route('checkout.store'), $fields)
            ->assertRedirect(route('checkout.success'))
            ->assertSessionMissing('cart')
            ->assertSessionMissing('checkout_quote');

        $order = Order::firstOrFail();
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame('pending', $order->status);
        $this->assertSame(300000000, Money::cents($order->total_amount));
        $this->assertSame(300000000, Money::cents($order->subtotal_amount));
        $this->assertSame(0, Money::cents($order->discount_amount));
        $this->assertSame($fields['checkout_version'], $order->checkout_token);

        $this->assertSame(2, $product->fresh()->quantity);
        $this->assertDatabaseHas('stock_reservations', [
            'user_id' => $user->id,
            'token' => $fields['checkout_version'],
            'status' => 'consumed',
        ]);

        $successPage = $this->get(route('checkout.success'))->assertOk();
        $successPage->assertSee('#'.$order->id)
            ->assertSee('Chờ xử lý')
            ->assertSee('Chuyển khoản QR (mô phỏng)')
            ->assertSee(SimulatedQr::reference($order->checkout_token, $order->id))
            ->assertSee('Đã ghi nhận xác nhận mô phỏng — Chưa thu tiền thật');
    }

    public function test_expired_qr_cannot_create_order_even_with_forged_client_clock_and_allows_retry_when_stock_available(): void
    {
        $firstCustomer = $this->customer();
        $secondCustomer = $this->customer();
        $product = $this->createProduct(1, '850000.00');

        $this->actingAs($firstCustomer)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $expiredFields = $this->checkoutFields($this->receiverPayload('bank_transfer'));

        $this->travel(5)->minutes();

        // Forge client-side fields (future expiry, paid flag, completed status) — server must reject.
        $this->post(route('checkout.store'), $expiredFields + [
            'expires_at' => now()->addHour()->toIso8601String(),
            'paid' => '1',
            'status' => 'completed',
        ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart')
            ->assertSessionHas('cart')
            ->assertSessionMissing('checkout_quote');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->quantity);

        // Once expired, stock availability is released so another buyer could reserve if first hasn't retried yet;
        // verify first buyer can also reopen checkout for a brand new token when stock remains.
        $retryCheckout = $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();
        $newToken = $retryCheckout->viewData('checkoutVersion');
        $this->assertNotSame($expiredFields['checkout_version'], $newToken);

        $this->post(route('checkout.store'), $this->receiverPayload('bank_transfer', [
            'checkout_version' => $newToken,
        ]))->assertRedirect(route('checkout.success'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, $product->fresh()->quantity);

        // Second customer cannot now buy the sold-out item.
        $this->actingAs($secondCustomer);
        $this->assertSame(0, (int) DB::table('stock_reservations')->where('status', 'active')->count());
    }

    public function test_expired_qr_releases_last_pair_to_another_customer_before_retry(): void
    {
        $firstCustomer = $this->customer();
        $secondCustomer = $this->customer();
        $product = $this->createProduct(1, '850000.00');

        $this->actingAs($firstCustomer)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();

        $this->travel(5)->minutes();

        // Simulate a completely separate browser session for the second customer.
        $this->post('/logout');
        $this->actingAs($secondCustomer)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $secondCheckout = $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();
        $this->post(route('checkout.store'), $this->receiverPayload('bank_transfer', [
            'checkout_version' => $secondCheckout->viewData('checkoutVersion'),
        ]))->assertRedirect(route('checkout.success'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame($secondCustomer->id, Order::firstOrFail()->user_id);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_reload_after_qr_expiry_redirects_to_cart_and_releases_hold(): void
    {
        $user = $this->customer();
        $product = $this->createProduct(2, '750000.00');

        $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();

        $this->travel(5)->minutes();

        $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors('cart')
            ->assertSessionMissing('checkout_quote');

        $this->assertSame(2, $product->fresh()->quantity);
    }

    public function test_replaying_qr_and_cod_checkout_requests_is_idempotent_across_empty_cart_and_stale_sessions(): void
    {
        foreach (['bank_transfer', 'cod'] as $method) {
            $user = $this->customer();
            $otherUser = $this->customer();
            $product = $this->createProduct(5, '1100000.00');

            $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 2]);
            $fields = $this->checkoutFields($this->receiverPayload($method));
            $snapshot = [
                'cart' => session('cart'),
                'checkout_quote' => session('checkout_quote'),
            ];

            // First submission
            $this->post(route('checkout.store'), $fields)
                ->assertRedirect(route('checkout.success'))
                ->assertSessionMissing('cart');

            $order = Order::latest('id')->firstOrFail();

            // Second submission immediately after (empty session cart, e.g. double-click or second tab)
            $this->post(route('checkout.store'), $fields)
                ->assertRedirect(route('checkout.success'));
            $this->assertSame($order->id, session('last_checkout_order_id'));

            // Third submission using stale session snapshot (concurrent tab replay)
            $this->withSession($snapshot)
                ->post(route('checkout.store'), $fields)
                ->assertRedirect(route('checkout.success'));
            $this->assertSame($order->id, session('last_checkout_order_id'));

            $this->assertSame(1, Order::where('checkout_token', $fields['checkout_version'])->count());
            $this->assertSame(1, $order->items()->count());
            $this->assertSame(3, $product->fresh()->quantity);

            // Another customer cannot replay the first customer's token
            $this->actingAs($otherUser)
                ->post(route('checkout.store'), $fields)
                ->assertRedirect(route('cart.index'));
        }
    }

    public function test_cod_consumes_hold_immediately_does_not_expire_after_five_minutes_and_restocks_once_on_cancel(): void
    {
        $user = $this->customer();
        $product = $this->createProduct(3, '950000.00');

        $this->actingAs($user)->post(route('cart.add', $product->id), ['quantity' => 2]);
        $fields = $this->checkoutFields($this->receiverPayload('cod'));

        $this->post(route('checkout.store'), $fields)->assertRedirect(route('checkout.success'));

        $order = Order::firstOrFail();
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('pending', $order->status);
        $this->assertSame(1, $product->fresh()->quantity);
        $this->assertDatabaseHas('stock_reservations', [
            'token' => $fields['checkout_version'],
            'status' => 'consumed',
        ]);

        // COD pending orders must NOT be cancelled or expire after 5-10 minutes
        $this->travel(10)->minutes();
        $this->assertSame(0, (new StockReservationService)->expire());
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->quantity);

        // Cancelling the COD order restores stock exactly once
        $this->patch(route('orders.cancel', $order->id))->assertRedirect(route('cart.index'));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->quantity);

        // Subsequent expire or duplicate cancel never restocks again
        $this->assertSame(0, (new StockReservationService)->expire());
        $this->patch(route('orders.cancel', $order->id))->assertStatus(422);
        $this->assertSame(3, $product->fresh()->quantity);
    }
}
