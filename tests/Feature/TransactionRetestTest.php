<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class TransactionRetestTest extends TestCase
{
    use RefreshDatabase;

    private function shoe(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'transaction'], ['name' => 'Transaction']);

        return Product::create(array_replace(['name' => 'Shoe '.Product::count(),
            'slug' => 'shoe-'.Product::count(), 'category_id' => $category->id,
            'price' => '10.25', 'quantity' => 5, 'is_active' => true], $attributes));
    }

    private function prepare(array $products): array
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        foreach ($products as $product) {
            $this->post(route('cart.add', $product->id), ['quantity' => 1])->assertRedirect(route('cart.index'));
        }

        return $this->checkoutFields(['customer_name' => 'Transaction buyer', 'phone' => '0900000000',
            'address' => 'Test address', 'payment_method' => 'cod']);
    }

    public function test_failure_writing_second_order_item_rolls_back_first_item_order_and_stock(): void
    {
        $first = $this->shoe();
        $second = $this->shoe();
        $fields = $this->prepare([$first, $second]);
        $writes = 0;
        OrderItem::creating(function () use (&$writes) {
            if (++$writes === 2) {
                throw new RuntimeException('Second item failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('checkout.store'), $fields);
            $this->fail('Failure must propagate');
        } catch (RuntimeException $exception) {
            $this->assertSame('Second item failure', $exception->getMessage());
        } finally {
            OrderItem::flushEventListeners();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame([5, 5], [$first->fresh()->quantity, $second->fresh()->quantity]);
        $this->assertCount(2, session('cart'));
        $this->assertSame($fields['checkout_version'], session('checkout_quote.version'));
    }

    public function test_failure_saving_cancelled_status_rolls_back_restored_stock(): void
    {
        $product = $this->shoe();
        $this->post(route('checkout.store'), $this->prepare([$product]))->assertRedirect(route('checkout.success'));
        $order = Order::firstOrFail();
        Order::updating(function () {
            throw new RuntimeException('Status save failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->patch(route('orders.cancel', $order->id));
            $this->fail('Failure must propagate');
        } catch (RuntimeException $exception) {
            $this->assertSame('Status save failure', $exception->getMessage());
        } finally {
            Order::flushEventListeners();
        }
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_second_product_return_overflow_rolls_back_first_product_return(): void
    {
        $first = $this->shoe();
        $second = $this->shoe();
        $this->post(route('checkout.store'), $this->prepare([$first, $second]))->assertRedirect(route('checkout.success'));
        $order = Order::firstOrFail();
        $second->update(['quantity' => 2147483647]); // Simulate legacy direct SQL corruption.
        $this->patch(route('orders.cancel', $order->id))->assertSessionHasErrors('stock');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame([4, 2147483647], [$first->fresh()->quantity, $second->fresh()->quantity]);
    }

    public function test_category_delete_failure_preserves_products_and_images(): void
    {
        Storage::fake('public')->put('products/retained.png', 'image');
        $product = $this->shoe(['image' => 'products/retained.png']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Category::deleting(function () {
            throw new RuntimeException('Category delete failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->delete(route('admin.categories.destroy', $product->category_id));
            $this->fail('Failure must propagate');
        } catch (RuntimeException $exception) {
            $this->assertSame('Category delete failure', $exception->getMessage());
        } finally {
            Category::flushEventListeners();
        }
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
        Storage::disk('public')->assertExists('products/retained.png');
    }

    public function test_invalid_checkout_arrays_render_again_and_do_not_write_order(): void
    {
        $product = $this->shoe();
        $fields = $this->prepare([$product]);
        foreach (['customer_name', 'phone', 'address', 'note', 'payment_method', 'checkout_version'] as $field) {
            $this->from('/checkout')->post('/checkout', array_replace($fields, [$field => ['invalid']]))
                ->assertSessionHasErrors($field);
            $this->get('/checkout')->assertOk();
            // Every GET issues a fresh quote; use it for the next validation probe.
            $fields['checkout_version'] = session('checkout_quote.version');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertCount(1, session('cart'));
    }

    public function test_passwords_are_not_flashed_after_validation_failure(): void
    {
        $this->from('/register')->post('/register', ['name' => 'Preserved', 'email' => 'bad',
            'password' => 'private-password', 'password_confirmation' => 'different-password'])
            ->assertSessionHasErrors(['email', 'password']);
        $old = session()->getOldInput();
        $this->assertSame('Preserved', $old['name']);
        $this->assertArrayNotHasKey('password', $old);
        $this->assertArrayNotHasKey('password_confirmation', $old);
    }

    public function test_free_product_checkout_has_exact_zero_total_and_normal_stock(): void
    {
        $product = $this->shoe(['price' => '0.00']);
        $this->post(route('checkout.store'), $this->prepare([$product]))->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('orders', ['total_amount' => '0.00', 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['unit_price' => '0.00', 'subtotal' => '0.00', 'quantity' => 1]);
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_product_changes_do_not_rewrite_order_item_snapshot(): void
    {
        $product = $this->shoe(['name' => 'Original shoe']);
        $this->post(route('checkout.store'), $this->prepare([$product]))->assertRedirect(route('checkout.success'));
        $product->update(['name' => 'New shoe', 'price' => '99.99']);
        $this->get(route('cart.index'))->assertOk()->assertSee('Original shoe')->assertDontSee('New shoe');
        $this->assertDatabaseHas('order_items', ['product_name' => 'Original shoe', 'unit_price' => '10.25', 'subtotal' => '10.25']);
        $this->assertDatabaseHas('orders', ['total_amount' => '10.25']);
    }

    public function test_checkout_ignores_forged_money_owner_and_status_fields(): void
    {
        $product = $this->shoe();
        $fields = $this->prepare([$product]);
        $owner = auth()->id();
        $other = User::factory()->create();
        $this->post(route('checkout.store'), $fields + ['total_amount' => '0', 'price' => '0',
            'user_id' => $other->id, 'status' => 'completed', 'quantity' => 999])
            ->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('orders', ['user_id' => $owner, 'total_amount' => '10.25', 'status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['quantity' => 1, 'unit_price' => '10.25']);
        $this->assertSame(4, $product->fresh()->quantity);
    }

    public function test_customer_cannot_update_or_delete_admin_records(): void
    {
        $product = $this->shoe();
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        foreach ([route('admin.products.edit', $product), route('admin.categories.edit', $product->category_id)] as $url) {
            $this->get($url)->assertRedirect('/');
        }
        foreach ([route('admin.products.update', $product), route('admin.categories.update', $product->category_id)] as $url) {
            $this->put($url, ['name' => 'Forged update'])->assertRedirect('/');
            $this->delete($url)->assertRedirect('/');
        }
        $this->assertSame('Shoe 0', $product->fresh()->name);
        $this->assertDatabaseHas('categories', ['id' => $product->category_id, 'name' => 'Transaction']);
    }

    public function test_product_form_ignores_unexposed_slug_and_active_fields(): void
    {
        $product = $this->shoe();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.products.update', $product), ['_version' => $product->editVersion(),
            'category_id' => $product->category_id, 'name' => 'Safe rename', 'price' => '10.25',
            'quantity' => 5, 'slug' => 'forged-slug', 'is_active' => false])
            ->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'slug' => 'safe-rename', 'is_active' => true]);
    }
}
