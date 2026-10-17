<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GiaHuyPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_repair_migration_works_for_existing_and_fresh_schema(): void
    {
        $migration = require database_path('migrations/2026_10_11_150000_ensure_role_exists_on_users_table.php');
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('users', 'role'));
        \Illuminate\Support\Facades\Schema::table('users', function ($table) {
            $table->dropColumn('role');
        });
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('users', 'role'));
        $user = User::factory()->create();
        $this->assertSame('customer', $user->fresh()->role);
    }
    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'test-shoes'], ['name' => 'Test shoes']);
        return Product::create(array_merge([
            'category_id' => $category->id, 'name' => 'Visible shoe',
            'slug' => 'visible-shoe', 'price' => 1200000,
            'quantity' => 3, 'is_active' => true,
        ], $attributes));
    }

    public function test_home_handles_empty_catalog_and_guest_navigation(): void
    {
        $this->get('/')->assertOk()->assertSee('Bộ sưu tập đang được cập nhật')
            ->assertSee('Đăng nhập')->assertDontSee('Khu vực quản trị');
    }

    public function test_home_hides_inactive_products_and_uses_real_cart_action(): void
    {
        $product = $this->product();
        $this->product(['name' => 'Hidden shoe', 'slug' => 'hidden-shoe', 'is_active' => false]);
        $this->product(['name' => 'Sold out shoe', 'slug' => 'sold-out', 'quantity' => 0]);
        $this->get('/')->assertOk()->assertSee('Visible shoe')->assertDontSee('Hidden shoe')
            ->assertSee('Hết hàng')->assertSee(route('cart.add', $product->id), false);
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->post(route('cart.add', $product->id), ['quantity' => 1])
            ->assertRedirect(route('cart.index'))->assertSessionHas('cart.'.$product->id.'.quantity', 1);
    }

    public function test_dashboard_requires_admin_and_reports_database_totals(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get('/admin/dashboard')->assertRedirect('/');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->product();
        foreach (['completed' => 1200000, 'pending' => 900000] as $status => $total) {
            Order::create([
                'user_id' => $customer->id, 'customer_name' => 'Test customer',
                'phone' => '0900000000', 'address' => 'Test address',
                'total_amount' => $total, 'status' => $status,
            ]);
        }
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertViewHas('productCount', 1)->assertViewHas('categoryCount', 1)
            ->assertViewHas('orderCount', 2)->assertViewHas('pendingOrderCount', 1)
            ->assertViewHas('completedRevenue', fn ($value) => (float) $value === 1200000.0);
    }

    public function test_admin_pages_render_and_product_pagination_keeps_filter_once(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        for ($i = 0; $i < 6; $i++) {
            $product = $this->product(['name' => 'Visible shoe '.$i, 'slug' => 'shoe-'.$i]);
        }
        foreach ([
            '/admin/categories', '/admin/categories/create',
            '/admin/categories/'.$product->category_id.'/edit',
            '/admin/products/create', '/admin/products/'.$product->id.'/edit', '/admin/orders',
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('Khu vực quản trị');
        }
        $response = $this->get('/admin/products?keyword=Visible');
        $response->assertOk()->assertSee('keyword=Visible', false);
        $this->assertSame(1, substr_count($response->getContent(), '<nav class="d-flex justify-items-center justify-content-between"'));
    }

    public function test_fresh_migrations_and_seeder_support_auth_and_checkout(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->assertTrue(Hash::check('12345678', $admin->password));
        $this->assertDatabaseCount('categories', 2);
        $this->post('/register', [
            'name' => 'Test customer', 'email' => 'test@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));
        $this->post('/login', [
            'email' => 'test@example.com', 'password' => 'password123',
        ])->assertRedirect('/');
        $customer = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($customer);
        $product = $this->product();
        $this->post(route('cart.add', $product->id), ['quantity' => 2]);
        $this->post('/checkout', [
            'customer_name' => 'Test customer', 'phone' => '0900000000', 'address' => 'Test address',
            'payment_method' => 'cod',
        ])->assertRedirect(route('checkout.success'))->assertSessionMissing('cart');
        $this->assertDatabaseHas('orders', ['user_id' => $customer->id, 'total_amount' => 2400000]);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->assertSame(1, $product->fresh()->quantity);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
