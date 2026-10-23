<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Regressions reproduced during the deep audit, now part of normal CI. */
class DeepLogicRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'deep-audit'], ['name' => 'Deep audit']);

        return Product::create(array_replace([
            'name' => 'Audit shoe', 'slug' => 'audit-shoe', 'category_id' => $category->id,
            'price' => 100000, 'quantity' => 5, 'is_active' => true,
        ], $overrides));
    }

    private function customer(): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        return $user;
    }

    private function receiver(): array
    {
        return $this->checkoutFields(['customer_name' => 'Audit buyer', 'phone' => '0900000000',
            'address' => 'Audit address', 'payment_method' => 'cod']);
    }

    public function test_admin_stale_form_must_not_restore_stock_sold_after_form_was_opened(): void
    {
        $product = $this->product();
        $oldForm = $product->only(['category_id', 'name', 'price', 'quantity']) + ['_version' => $product->fresh()->editVersion()];
        $this->customer();
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $this->post(route('checkout.store'), $this->receiver())->assertRedirect();
        $this->assertSame(3, $product->fresh()->quantity);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.products.update', $product), $oldForm + ['description' => 'Only editing description'])
            ->assertRedirect()->assertSessionHasErrors('_version');
        $this->assertSame(3, $product->fresh()->quantity, 'Stale admin form restores two sold units.');
    }

    public function test_checkout_must_show_current_database_price_before_confirmation(): void
    {
        $this->customer();
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $product->update(['price' => 150000]);
        $this->get(route('checkout.index'))->assertOk()
            ->assertViewHas('total', fn ($total) => (float) $total === 300000.0);
    }

    public function test_category_cascade_must_clean_product_images(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $disk = Storage::fake('public');
        $disk->put('products/audit.png', 'image');
        $product = $this->product(['image' => 'products/audit.png']);
        $this->delete(route('admin.categories.destroy', $product->category_id))->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $disk->assertMissing('products/audit.png');
    }

    public function test_delete_one_demo_product_must_preserve_image_shared_by_other_products(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $disk = Storage::fake('public');
        $disk->put('products/gd3-demo.svg', 'shared image');
        $first = $this->product(['image' => 'products/gd3-demo.svg']);
        $second = $this->product(['name' => 'Other', 'slug' => 'other', 'image' => 'products/gd3-demo.svg']);
        $this->delete(route('admin.products.destroy', $first))->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $second->id]);
        $disk->assertExists('products/gd3-demo.svg');
    }

    public function test_blank_quantity_must_not_create_a_zero_quantity_cart_item(): void
    {
        $this->customer();
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => ''])->assertRedirect();
        $cart = session('cart', []);
        $this->assertTrue(! isset($cart[$product->id]) || $cart[$product->id]['quantity'] >= 1);
    }

    public function test_keyword_zero_must_filter_product_names(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->product(['name' => 'No zero in this name']);
        $this->get(route('admin.products.index', ['keyword' => '0']))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    public function test_array_keyword_must_not_crash_admin_search(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('admin.products.index', ['keyword' => ['bad']]))->assertStatus(422);
    }

    public function test_array_status_must_not_crash_admin_order_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('admin.orders.index', ['status' => ['pending']]))->assertStatus(422);
    }

    public function test_checkout_must_reject_an_alphabetic_phone_number(): void
    {
        $this->customer();
        $product = $this->product();
        $this->post(route('cart.add', $product->id));
        $this->post(route('checkout.store'), array_replace($this->receiver(), ['phone' => 'abc']))
            ->assertSessionHasErrors('phone');
    }

    public function test_registration_cannot_choose_admin_role(): void
    {
        $this->post('/register', ['name' => 'Audit registration', 'email' => 'audit@example.test',
            'password' => 'audit-password', 'password_confirmation' => 'audit-password', 'role' => 'admin'])
            ->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['email' => 'audit@example.test', 'role' => 'customer']);
    }

    public function test_accepted_fractional_prices_must_not_lose_precision_on_home(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Fractional price', 'slug' => 'fractional-price']);
        $response = $this->post(route('admin.products.store'), [
            'category_id' => $category->id, 'name' => 'Fractional shoe',
            'price' => '1250.50', 'quantity' => 5,
        ]);
        if (session('errors')?->has('price')) {
            $this->assertDatabaseCount('products', 0);

            return;
        }
        $response->assertRedirect(route('admin.products.index'));
        $this->get(route('home'))->assertSee('1.250,50');
    }

    public function test_all_admin_pages_reject_customer_and_all_customer_pages_reject_admin(): void
    {
        $this->customer();
        foreach (['admin.dashboard', 'admin.products.index', 'admin.products.create',
            'admin.categories.index', 'admin.categories.create', 'admin.orders.index'] as $route) {
            $this->get(route($route))->assertRedirect('/');
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['cart.index', 'checkout.index', 'checkout.success'] as $route) {
            $this->get(route($route))->assertRedirect('/');
        }
    }

    public function test_remember_checkbox_must_issue_a_recaller_cookie(): void
    {
        $user = User::factory()->create(['password' => bcrypt('audit-password')]);
        $cookie = auth()->guard()->getRecallerName();
        $this->post('/login', ['email' => $user->email, 'password' => 'audit-password', 'remember' => 'on'])
            ->assertRedirect()->assertCookie($cookie);
    }

    public function test_error_flash_must_only_render_once_in_cart(): void
    {
        $this->customer();
        $response = $this->withSession(['error' => 'Unique audit error'])->get(route('cart.index'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Unique audit error'));
    }

    public function test_error_flash_must_only_render_once_in_checkout(): void
    {
        $this->customer();
        $product = $this->product();
        $this->post(route('cart.add', $product->id));
        $response = $this->withSession(['error' => 'Unique audit error'])->get(route('checkout.index'))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Unique audit error'));
    }

    public function test_checkout_validation_failure_does_not_partially_decrement_other_products(): void
    {
        $this->customer();
        $first = $this->product();
        $second = $this->product(['name' => 'Second', 'slug' => 'second']);
        foreach ([$first, $second] as $product) {
            $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        }
        $second->update(['quantity' => 1]);
        $this->post(route('checkout.store'), $this->receiver())->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(5, $first->fresh()->quantity);
    }

    public function test_cancellation_after_product_deleted_preserves_order_history(): void
    {
        $this->customer();
        $product = $this->product();
        $this->post(route('cart.add', $product->id));
        $this->post(route('checkout.store'), $this->receiver());
        $order = Order::firstOrFail();
        $product->delete();
        $this->patch(route('orders.cancel', $order->id))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($order->items()->firstOrFail()->product_id);
        $this->assertSame('Audit shoe', $order->items()->firstOrFail()->product_name);
    }
}
