<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalLogicRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function shoe(array $extra = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'final-test'], ['name' => 'Final test']);

        return Product::create(array_replace(['name' => 'Final shoe', 'slug' => 'final-shoe',
            'category_id' => $category->id, 'price' => '1250.50', 'quantity' => 5,
            'is_active' => true], $extra));
    }

    private function buyer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
    }

    private function receiver(): array
    {
        return ['customer_name' => 'Final buyer', 'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod'];
    }

    public function test_price_change_after_confirmation_requires_a_new_confirmation(): void
    {
        $this->buyer();
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $quote = $this->checkoutFields($this->receiver());
        $product->update(['price' => '1500.25']);
        $this->post(route('checkout.store'), $quote)->assertSessionHasErrors('cart')->assertSessionHas('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->post(route('checkout.store'), $this->checkoutFields($this->receiver()))
            ->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('orders', ['total_amount' => '3000.50']);
    }

    public function test_tampered_quote_and_replay_after_rebuilding_identical_cart_are_rejected(): void
    {
        $this->buyer();
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $quote = $this->checkoutFields($this->receiver());
        $this->post(route('checkout.store'), array_replace($quote, ['checkout_version' => str_repeat('0', 64)]))
            ->assertSessionHasErrors('cart');
        $this->post(route('checkout.store'), $quote)->assertRedirect(route('checkout.success'));
        $this->post(route('cart.add', $product->id));
        $this->post(route('checkout.store'), $quote)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_cart_change_invalidates_quote_even_when_quantity_returns_to_original(): void
    {
        $this->buyer();
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $quote = $this->checkoutFields($this->receiver());
        $this->patch(route('cart.update', $product->id), ['quantity' => 2]);
        $this->patch(route('cart.update', $product->id), ['quantity' => 1]);
        $this->post(route('checkout.store'), $quote)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_database_unique_token_prevents_duplicate_even_with_a_stale_worker_session(): void
    {
        $this->buyer();
        $product = $this->shoe(['quantity' => 1]);
        $this->post(route('cart.add', $product->id));
        $payload = $this->checkoutFields($this->receiver());
        $snapshot = ['cart' => session('cart'), 'checkout_quote' => session('checkout_quote')];
        $this->post(route('checkout.store'), $payload)->assertRedirect(route('checkout.success'));
        $this->withSession($snapshot)->post(route('checkout.store'), $payload)->assertRedirect(route('checkout.success'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_replay_recovers_order_committed_after_initial_lookup_before_stock_validation(): void
    {
        $this->buyer();
        $product = $this->shoe(['quantity' => 1]);
        $this->post(route('cart.add', $product->id));
        $payload = $this->checkoutFields($this->receiver());
        $injected = false;
        $winnerId = null;
        // Inject the winner only after the initial SELECT has returned an empty result.
        // The two-process MariaDB test separately verifies real commit/lock visibility.
        DB::listen(function ($query) use (&$injected, &$winnerId, $product, $payload) {
            if (! $injected && str_contains($query->sql, 'from "orders"') && str_contains($query->sql, 'checkout_token')) {
                $injected = true;
                $winnerId = DB::transaction(function () use ($product, $payload) {
                    $order = Order::create($this->receiver() + ['user_id' => auth()->id(),
                        'checkout_token' => $payload['checkout_version'], 'status' => 'pending', 'total_amount' => '1250.50']);
                    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
                        'quantity' => 1, 'unit_price' => '1250.50', 'subtotal' => '1250.50']);
                    $product->decrement('quantity');

                    return $order->id;
                });
            }
        });
        $this->post(route('checkout.store'), $payload)->assertRedirect(route('checkout.success'))
            ->assertSessionMissing('cart')->assertSessionMissing('checkout_quote');
        $this->assertTrue($injected);
        $this->assertSame($winnerId, session('last_checkout_order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_replay_validation_does_not_recover_another_users_order(): void
    {
        $this->buyer();
        $product = $this->shoe(['quantity' => 1]);
        $this->post(route('cart.add', $product->id));
        $payload = $this->checkoutFields($this->receiver());
        $other = User::factory()->create(['role' => 'customer']);
        Order::create($this->receiver() + ['user_id' => $other->id,
            'checkout_token' => $payload['checkout_version'], 'status' => 'pending', 'total_amount' => '1250.50']);
        $product->update(['quantity' => 0]);
        $this->post(route('checkout.store'), $payload)->assertSessionHasErrors('cart')->assertSessionHas('cart');
        $this->assertNull(session('last_checkout_order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_stock_edit_reserves_room_for_pending_returns(): void
    {
        $this->buyer();
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $this->post(route('checkout.store'), $this->checkoutFields($this->receiver()))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.products.update', $product), $product->fresh()->only(['category_id', 'name', 'price']) + [
            'quantity' => 2147483647, '_version' => $product->fresh()->editVersion(),
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_missing_and_tampered_edit_versions_cannot_write_product(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        $data = $product->only(['category_id', 'name', 'quantity', 'price']);
        $this->put(route('admin.products.update', $product), $data)->assertSessionHasErrors('_version');
        $this->put(route('admin.products.update', $product), array_replace($data, [
            '_version' => str_repeat('0', 64), 'price' => 9999,
        ]))->assertSessionHasErrors('_version');
        $this->assertEquals('1250.50', $product->fresh()->price);
    }

    public function test_retrying_stale_form_after_validation_does_not_receive_a_fresh_version_for_old_values(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        $old = $product->only(['category_id', 'name', 'quantity', 'price']) + ['_version' => $product->fresh()->editVersion()];
        $product->update(['quantity' => 3]);
        $this->from(route('admin.products.edit', $product))->put(route('admin.products.update', $product), $old)
            ->assertSessionHasErrors('_version');
        $this->get(route('admin.products.edit', $product))->assertSee('value="'.$old['_version'].'"', false);
        $this->put(route('admin.products.update', $product), $old)->assertSessionHasErrors('_version');
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_shared_image_replacement_does_not_break_other_product_and_last_delete_cleans_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $disk = Storage::fake('public');
        $disk->put('products/shared.png', 'shared');
        $first = $this->shoe(['image' => 'products/shared.png']);
        $second = $this->shoe(['name' => 'Second shoe', 'slug' => 'second-shoe', 'image' => 'products/shared.png']);
        $image = UploadedFile::fake()->image('replacement.png');
        $this->put(route('admin.products.update', $first), $first->only(['category_id', 'name', 'quantity', 'price']) + [
            '_version' => $first->fresh()->editVersion(), 'image' => $image,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $disk->assertExists('products/shared.png');
        $disk->assertExists($first->fresh()->image);
        $this->delete(route('admin.products.destroy', $second))->assertRedirect();
        $disk->assertMissing('products/shared.png');
        $disk->assertExists($first->fresh()->image);
    }

    public function test_stale_form_with_new_upload_preserves_old_image_and_cleans_rejected_upload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $disk = Storage::fake('public');
        $disk->put('products/old.png', 'old');
        $product = $this->shoe(['image' => 'products/old.png']);
        $data = $product->only(['category_id', 'name', 'quantity', 'price']) + ['_version' => $product->fresh()->editVersion()];
        $product->update(['quantity' => 3]);
        $this->put(route('admin.products.update', $product), $data + ['image' => UploadedFile::fake()->image('new.png')])
            ->assertSessionHasErrors('_version');
        $this->assertSame(['products/old.png'], $disk->allFiles('products'));
        $this->assertSame(3, $product->fresh()->quantity);
    }

    public function test_multibyte_text_limit_is_checked_in_bytes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.categories.store'), ['name' => 'Too long', 'description' => str_repeat('😀', 16384)])
            ->assertSessionHasErrors('description');
        $this->post(route('admin.categories.store'), ['name' => 'Boundary', 'description' => str_repeat('a', 65535)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_phone_must_contain_enough_digits_and_email_rejects_header_injection(): void
    {
        $this->post('/register', ['name' => 'Invalid email', 'email' => "user@example.test\r\nBcc: other@example.test",
            'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('email');
        $this->buyer();
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $this->post(route('checkout.store'), $this->checkoutFields(array_replace($this->receiver(), ['phone' => '0-------0'])))
            ->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_order_filters_and_array_pages_are_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('admin.orders.index', ['status' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('admin.products.index', ['page' => ['bad']]))->assertUnprocessable();
    }

    public function test_product_category_must_be_one_integer_not_an_array(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        $this->post(route('admin.products.store'), ['category_id' => [$product->category_id],
            'name' => 'Bad category', 'price' => 1000, 'quantity' => 1])->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_store_actions_match_role_and_show_quantity_validation_errors(): void
    {
        $product = $this->shoe();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('store.index'))->assertSee(route('admin.products.edit', $product), false)
            ->assertDontSee('action="'.route('cart.add', $product->id).'"', false);
        $this->buyer();
        $this->from(route('store.index'))->post(route('cart.add', $product->id), ['quantity' => ''])
            ->assertSessionHasErrors('quantity');
        $message = session('errors')->first('quantity');
        $this->get(route('store.index'))->assertSee($message);
    }

    public function test_terminal_orders_have_no_edit_form_and_zero_search_keeps_reset_link(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $order = Order::create(['customer_name' => 'Closed', 'phone' => '0900000000', 'address' => 'Test',
            'total_amount' => 1000, 'status' => 'completed', 'payment_method' => 'cod']);
        $this->get(route('admin.orders.index'))->assertSee('Đơn đã kết thúc')
            ->assertDontSee(route('admin.orders.updateStatus', $order), false);
        $this->get(route('admin.products.index', ['keyword' => '0']))->assertSee('Xóa lọc');
    }

    public function test_price_increase_overflow_keeps_cart_editable_instead_of_redirect_loop(): void
    {
        $this->buyer();
        $product = $this->shoe(['price' => '1000.00']);
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $product->update(['price' => '6000000000000.00']);
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))->assertOk()->assertSee('vượt giới hạn')
            ->assertViewHas('total', fn ($total) => $total === null);
        $this->patch(route('cart.update', $product->id), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))->assertOk()->assertViewHas('total', '6000000000000.00');
    }

    public function test_legacy_zero_quantity_does_not_break_cart_or_checkout(): void
    {
        $this->buyer();
        $product = $this->shoe();
        $this->withSession(['cart' => [$product->id => ['quantity' => 0, 'price' => 1250.50]]])
            ->get(route('cart.index'))->assertOk()->assertSessionHas('cart', []);
        $this->get(route('checkout.index'))->assertRedirect(route('cart.index'));
    }

    public function test_cart_total_overflow_is_rejected_without_modifying_existing_cart(): void
    {
        $this->buyer();
        $first = $this->shoe(['price' => '6000000000000.00']);
        $second = $this->shoe(['name' => 'Other', 'slug' => 'other', 'price' => '6000000000000.00']);
        $this->post(route('cart.add', $first->id))->assertSessionHasNoErrors();
        $this->post(route('cart.add', $second->id))->assertSessionHasErrors('cart');
        $this->assertCount(1, session('cart'));
        $this->get(route('cart.index'))->assertOk();
    }
}
