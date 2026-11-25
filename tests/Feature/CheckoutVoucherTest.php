<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use App\Support\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutVoucherTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $quantity = 5, string $price = '1000000.00'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'voucher-shoes'], ['name' => 'Voucher Shoes']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Voucher Sneaker '.Product::count(),
            'slug' => 'voucher-sneaker-'.Product::count(),
            'price' => $price,
            'quantity' => $quantity,
            'is_active' => true,
        ]);
    }

    private function createVoucher(array $overrides = []): Voucher
    {
        return Voucher::create(array_replace([
            'code' => 'DEMO10',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'max_discount_amount' => '150000.00',
            'minimum_order_amount' => '500000.00',
            'usage_limit' => 5,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(7),
            'is_active' => true,
        ], $overrides));
    }

    private function receiverPayload(string $paymentMethod = 'cod', array $extra = []): array
    {
        return array_replace([
            'customer_name' => 'Phạm Minh Trọng',
            'phone' => '0901234567',
            'address' => '123 Nguyễn Trãi, Quận 5, TP.HCM',
            'payment_method' => $paymentMethod,
        ], $extra);
    }

    public function test_voucher_service_normalizes_case_rounds_percentage_down_and_caps_or_clamps_discounts(): void
    {
        $service = new VoucherService;

        // Percentage rounds down to cents: 10% of 100.05 = 10.005 => 10.00 (1000 cents)
        $this->createVoucher([
            'code' => 'PCT10',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'max_discount_amount' => '50000.00',
            'minimum_order_amount' => '0.00',
            'usage_limit' => null,
        ]);

        $roundedQuote = $service->quote('  pct10  ', Money::cents('100.05'));
        $this->assertSame('PCT10', $roundedQuote['code']);
        $this->assertSame(1000, $roundedQuote['discount_cents']);
        $this->assertSame('10.00', $roundedQuote['discount_amount']);
        $this->assertSame('90.05', $roundedQuote['payable_amount']);

        // Percentage hits max_discount_amount cap: 10% of 2,000,000 = 200,000 => capped at 50,000
        $cappedQuote = $service->quote('pct10', Money::cents('2000000.00'));
        $this->assertSame('50000.00', $cappedQuote['discount_amount']);
        $this->assertSame('1950000.00', $cappedQuote['payable_amount']);

        // Fixed discount clamps to subtotal (cannot go below 0)
        $this->createVoucher([
            'code' => 'BIGFIX',
            'discount_type' => 'fixed',
            'discount_value' => '500000.00',
            'max_discount_amount' => null,
            'minimum_order_amount' => '0.00',
            'usage_limit' => null,
        ]);

        $clampedQuote = $service->quote('bigfix', Money::cents('300000.00'));
        $this->assertSame('300000.00', $clampedQuote['discount_amount']);
        $this->assertSame('0.00', $clampedQuote['payable_amount']);
    }

    public function test_voucher_service_rejects_inactive_future_expired_under_minimum_and_exhausted_vouchers(): void
    {
        $service = new VoucherService;

        $this->createVoucher(['code' => 'INACTIVE', 'is_active' => false]);
        $this->createVoucher(['code' => 'FUTURE', 'starts_at' => now()->addHour(), 'ends_at' => now()->addDay()]);
        $this->createVoucher(['code' => 'EXPIRED', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subMinute()]);
        $this->createVoucher(['code' => 'MINHIGH', 'minimum_order_amount' => '1000000.00']);
        $exhausted = $this->createVoucher(['code' => 'EXHAUSTED', 'usage_limit' => 1]);
        Voucher::whereKey($exhausted->id)->update(['used_count' => 1]);

        foreach (['NOPE', 'INACTIVE', 'FUTURE', 'EXPIRED', 'MINHIGH', 'EXHAUSTED'] as $code) {
            try {
                $service->quote($code, Money::cents('600000.00'));
                $this->fail("Voucher {$code} should have been rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('voucher_code', $exception->errors());
            }
        }
    }

    public function test_preview_does_not_increment_usage_and_successful_checkout_saves_snapshot_and_updates_qr_amount(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct(5, '1000000.00');
        $voucher = $this->createVoucher([
            'code' => 'DEMO10',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'max_discount_amount' => '150000.00',
            'minimum_order_amount' => '500000.00',
            'usage_limit' => 2,
        ]);

        $this->actingAs($customer)->post(route('cart.add', $product->id), ['quantity' => 2]);

        // Preview voucher via POST /checkout/voucher (lowercase input)
        $this->post(route('checkout.voucher.apply'), [
            'voucher_code' => ' demo10 ',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect(route('checkout.index', ['payment_method' => 'bank_transfer']));

        $this->assertSame(0, $voucher->fresh()->used_count);

        // Checkout page reflects 2,000,000 subtotal, 150,000 capped discount, 1,850,000 payable + QR payload
        $checkoutPage = $this->get(route('checkout.index', ['payment_method' => 'bank_transfer']))->assertOk();
        $this->assertSame('2000000.00', $checkoutPage->viewData('subtotal'));
        $this->assertSame('150000.00', $checkoutPage->viewData('discountAmount'));
        $this->assertSame('1850000.00', $checkoutPage->viewData('total'));
        $this->assertStringContainsString('So tien: 1.850.000 VND', $checkoutPage->viewData('qrPayload'));
        $this->assertStringContainsString('Voucher: DEMO10', $checkoutPage->viewData('qrPayload'));

        $checkoutPage->assertSee('DEMO10')
            ->assertSee('150.000 đ')
            ->assertSee('1.850.000 đ');

        // Place order via QR simulated checkout
        $this->post(route('checkout.store'), $this->receiverPayload('bank_transfer', [
            'checkout_version' => $checkoutPage->viewData('checkoutVersion'),
        ]))->assertRedirect(route('checkout.success'))
            ->assertSessionMissing('checkout_voucher');

        $order = Order::firstOrFail();
        $this->assertSame($voucher->id, $order->voucher_id);
        $this->assertSame('DEMO10', $order->voucher_code);
        $this->assertSame(200000000, Money::cents($order->subtotal_amount));
        $this->assertSame(15000000, Money::cents($order->discount_amount));
        $this->assertSame(185000000, Money::cents($order->total_amount));
        $this->assertSame(1, $voucher->fresh()->used_count);

        // Success page displays voucher code, discount, and payable total
        $this->get(route('checkout.success'))->assertOk()
            ->assertSee('DEMO10')
            ->assertSee('150.000 đ')
            ->assertSee('1.850.000 đ');

        // Cancelling the order restores stock but does NOT restore voucher used_count
        $this->patch(route('orders.cancel', $order->id))->assertRedirect(route('cart.index'));
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(1, $voucher->fresh()->used_count);

        // Deleting voucher keeps order snapshot intact with null voucher_id
        $voucher->delete();
        $order->refresh();
        $this->assertNull($order->voucher_id);
        $this->assertSame('DEMO10', $order->voucher_code);
        $this->assertSame(15000000, Money::cents($order->discount_amount));
        $this->assertSame(185000000, Money::cents($order->total_amount));
    }

    public function test_failed_order_creation_rolls_back_voucher_usage_increment(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $first = $this->createProduct(5, '600000.00');
        $second = $this->createProduct(5, '400000.00');
        $voucher = $this->createVoucher([
            'code' => 'ROLLBACK50K',
            'discount_type' => 'fixed',
            'discount_value' => '50000.00',
            'max_discount_amount' => null,
            'minimum_order_amount' => '0.00',
            'usage_limit' => 1,
        ]);

        $this->actingAs($customer);
        $this->post(route('cart.add', $first->id), ['quantity' => 1]);
        $this->post(route('cart.add', $second->id), ['quantity' => 1]);
        $this->post(route('checkout.voucher.apply'), ['voucher_code' => 'ROLLBACK50K'])->assertRedirect();
        $fields = $this->checkoutFields($this->receiverPayload('cod'));

        // Deactivate second product after reservation so order creation fails inside transaction
        $second->update(['is_active' => false]);

        $this->post(route('checkout.store'), $fields)
            ->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertSame(5, $first->fresh()->quantity);
    }

    public function test_single_use_voucher_cannot_be_consumed_by_two_customers(): void
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $product = $this->createProduct(10, '1000000.00');
        $voucher = $this->createVoucher([
            'code' => 'LASTUSE',
            'discount_type' => 'fixed',
            'discount_value' => '100000.00',
            'max_discount_amount' => null,
            'minimum_order_amount' => '0.00',
            'usage_limit' => 1,
        ]);

        // Customer 1 previews and places order successfully
        $this->actingAs($customer1)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->post(route('checkout.voucher.apply'), ['voucher_code' => 'LASTUSE'])->assertRedirect();
        $c1Fields = $this->checkoutFields($this->receiverPayload('cod'));
        $this->post(route('checkout.store'), $c1Fields)->assertRedirect(route('checkout.success'));

        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame(9, $product->fresh()->quantity);

        // Customer 2 tries to checkout with the same single-use voucher (e.g. had it selected earlier)
        $this->post('/logout');
        $this->actingAs($customer2)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $c2Fields = $this->checkoutFields($this->receiverPayload('cod', ['voucher_code' => 'LASTUSE']));
        $this->post(route('checkout.store'), $c2Fields)
            ->assertSessionHasErrors('voucher_code');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame(9, $product->fresh()->quantity);
    }

    public function test_remove_voucher_and_role_protection_work_as_expected(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createProduct(5, '800000.00');
        $this->createVoucher([
            'code' => 'DEMO50K',
            'discount_type' => 'fixed',
            'discount_value' => '50000.00',
            'max_discount_amount' => null,
            'minimum_order_amount' => '0.00',
        ]);

        // Guest redirected to login
        $this->post(route('checkout.voucher.apply'), ['voucher_code' => 'DEMO50K'])
            ->assertRedirect(route('login'));

        // Admin blocked from customer checkout voucher endpoints
        $this->actingAs($admin)->post(route('checkout.voucher.apply'), ['voucher_code' => 'DEMO50K'])
            ->assertRedirect('/');

        // Customer applies and then removes voucher
        $this->actingAs($customer)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->post(route('checkout.voucher.apply'), ['voucher_code' => 'DEMO50K'])
            ->assertRedirect(route('checkout.index'));
        $this->get(route('checkout.index'))->assertOk()
            ->assertViewHas('discountAmount', '50000.00')
            ->assertViewHas('total', '750000.00');

        $this->delete(route('checkout.voucher.remove'))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionMissing('checkout_voucher');
        $this->get(route('checkout.index'))->assertOk()
            ->assertViewHas('discountAmount', '0.00')
            ->assertViewHas('total', '800000.00');
    }
}
