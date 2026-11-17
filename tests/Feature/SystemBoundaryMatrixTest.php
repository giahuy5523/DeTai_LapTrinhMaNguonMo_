<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemBoundaryMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function shoe(): Product
    {
        $category = Category::create(['name' => 'Matrix', 'slug' => 'matrix']);

        return Product::create(['category_id' => $category->id, 'name' => 'Matrix shoe', 'slug' => 'matrix-shoe',
            'price' => '12.50', 'quantity' => 5, 'is_active' => true]);
    }

    private function receiver(): array
    {
        return ['customer_name' => 'Matrix buyer', 'phone' => '0900000000', 'address' => 'Test address', 'payment_method' => 'cod'];
    }

    public function test_product_create_and_update_reject_invalid_field_matrix_without_writes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        $cases = ['price' => ['1e3', 'NaN', 'INF', '-0.01', '0.001', '10000000000000', '1,25', [], null],
            'quantity' => [-1, '1.5', '2147483648', 'abc', [], null], 'category_id' => [[], null, -1, '1abc'],
            'description' => [[], str_repeat('x', 65536)], 'name' => [[], null, str_repeat('x', 256)]];
        foreach ($cases as $field => $values) {
            foreach ($values as $value) {
                $base = ['name' => 'New matrix shoe', 'category_id' => $product->category_id, 'price' => '12.50', 'quantity' => 5];
                $this->postJson('/admin/products', array_replace($base, [$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
                $base['name'] = $product->name;
                $base['_version'] = $product->editVersion();
                $this->putJson('/admin/products/'.$product->id, array_replace($base, [$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
                $this->assertSame('Matrix shoe', $product->fresh()->name);
                $this->assertSame(5, $product->fresh()->quantity);
                $this->assertDatabaseCount('products', 1);
            }
        }
    }

    public function test_checkout_invalid_field_matrix_has_no_partial_writes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->post('/gio-hang/them/'.$product->id);
        $base = $this->checkoutFields($this->receiver());
        $cases = ['customer_name' => [[], null, str_repeat('a', 256)],
            'phone' => [[], null, '12345678', str_repeat('1', 16), '+012345678----------9', 'abc123456789'],
            'address' => [[], null, str_repeat('x', 1001)], 'note' => [[], str_repeat('x', 2001)],
            'payment_method' => [[], null, 'online'], 'checkout_version' => [[], null, str_repeat('a', 63), str_repeat('a', 65)]];
        foreach ($cases as $field => $values) {
            foreach ($values as $value) {
                $this->postJson('/checkout', array_replace($base, [$field => $value]))
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
                $this->assertSame(5, $product->fresh()->quantity);
                $this->assertDatabaseCount('orders', 0);
                $this->assertDatabaseCount('order_items', 0);
                $this->assertCount(1, session('cart'));
            }
        }
    }

    public function test_every_admin_order_status_transition_obeys_state_and_stock_rules(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        $states = ['pending', 'processing', 'completed', 'cancelled'];
        $allowed = ['pending' => ['pending', 'processing', 'cancelled'], 'processing' => ['processing', 'completed', 'cancelled']];
        foreach ($states as $before) {
            foreach ($states as $after) {
                $initial = $before === 'cancelled' ? 5 : 4;
                $product->refresh()->update(['quantity' => $initial]);
                $order = Order::create($this->receiver() + ['user_id' => auth()->id(), 'status' => $before, 'total_amount' => '12.50']);
                $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
                    'quantity' => 1, 'unit_price' => '12.50', 'subtotal' => '12.50']);
                $response = $this->patchJson(route('admin.orders.updateStatus', $order), ['status' => $after]);
                $accepted = in_array($after, $allowed[$before] ?? [], true);
                if ($accepted) {
                    $response->assertRedirect();
                } else {
                    $response->assertUnprocessable()->assertJsonValidationErrors('status');
                }
                $this->assertSame($accepted ? $after : $before, $order->fresh()->status);
                $this->assertSame($accepted && $after === 'cancelled' ? 5 : $initial, $product->fresh()->quantity);
            }
        }
    }

    public function test_each_customer_cancel_state_and_other_owner_are_enforced(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);
        $product = $this->shoe();
        foreach (['pending', 'processing', 'completed', 'cancelled'] as $state) {
            foreach ([$customer->id, $other->id] as $owner) {
                $initial = $state === 'cancelled' ? 5 : 4;
                $product->refresh()->update(['quantity' => $initial]);
                $order = Order::create($this->receiver() + ['user_id' => $owner, 'status' => $state, 'total_amount' => '12.50']);
                $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
                    'quantity' => 1, 'unit_price' => '12.50', 'subtotal' => '12.50']);
                $response = $this->patchJson(route('orders.cancel', $order->id));
                if ($owner !== $customer->id) {
                    $response->assertNotFound();
                } elseif ($state !== 'pending') {
                    $response->assertUnprocessable();
                } else {
                    $response->assertRedirect('/gio-hang');
                }
                $accepted = $owner === $customer->id && $state === 'pending';
                $this->assertSame($accepted ? 'cancelled' : $state, $order->fresh()->status);
                $this->assertSame($accepted ? 5 : $initial, $product->fresh()->quantity);
            }
        }
    }

    public function test_category_text_storage_boundaries_are_validated_for_create_and_update(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $name = str_repeat('a', 255);
        $this->post('/admin/categories', ['name' => $name, 'description' => str_repeat('x', 65535)])
            ->assertRedirect('/admin/categories');
        $category = Category::firstOrFail();
        $this->assertSame(65535, strlen($category->description));
        foreach ([['description' => str_repeat('x', 65536)], ['description' => []], ['name' => str_repeat('x', 256)]] as $bad) {
            $field = array_key_first($bad);
            $this->putJson('/admin/categories/'.$category->id, array_replace(['name' => $name], $bad))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(65535, strlen($category->fresh()->description));
    }

    public function test_guest_mutations_are_blocked_without_any_database_writes(): void
    {
        $product = $this->shoe();
        foreach (['/gio-hang/them/'.$product->id, '/checkout', '/admin/products', '/admin/categories'] as $url) {
            $this->post($url, [])->assertRedirect('/login');
        }
        foreach (['/admin/products/'.$product->id, '/admin/categories/'.$product->category_id] as $url) {
            $this->put($url, [])->assertRedirect('/login');
            $this->delete($url)->assertRedirect('/login');
        }
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('orders', 0);
    }
}
