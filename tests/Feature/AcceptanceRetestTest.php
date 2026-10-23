<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Regression coverage for the independent acceptance findings R01–R04. */
class AcceptanceRetestTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_beyond_bcrypt_byte_limit_is_rejected(): void
    {
        $password = str_repeat('a', 72).'x';
        $this->postJson('/register', ['name' => 'Boundary', 'email' => 'boundary@example.test',
            'password' => $password, 'password_confirmation' => $password])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_containing_nul_is_a_validation_error(): void
    {
        $password = "secret\0password";
        $this->postJson('/register', ['name' => 'Boundary', 'email' => 'nul@example.test',
            'password' => $password, 'password_confirmation' => $password])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_different_password_suffix_cannot_authenticate(): void
    {
        $prefix = str_repeat('a', 72);
        // Simulate a pre-fix account so registration rejection cannot mask login bypass.
        $user = User::factory()->create(['email' => 'suffix@example.test',
            'password' => Hash::make($prefix.'original')]);
        $hash = $user->getRawOriginal('password');
        $this->postJson('/login', ['email' => $user->email, 'password' => $prefix.'different'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
        $this->assertSame($hash, $user->fresh()->getRawOriginal('password'));
    }

    public function test_registration_cannot_assign_admin_role(): void
    {
        $this->post('/register', ['name' => 'Customer', 'email' => 'role@example.test',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'admin'])
            ->assertRedirect('/login');
        $this->assertDatabaseHas('users', ['email' => 'role@example.test', 'role' => 'customer']);
    }

    public function test_product_name_is_escaped_on_public_pages(): void
    {
        $category = Category::create(['name' => 'Acceptance', 'slug' => 'acceptance']);
        Product::create(['name' => '<script>alert(1)</script>', 'slug' => 'escaped',
            'category_id' => $category->id, 'price' => '10.25', 'quantity' => 2, 'is_active' => true]);
        foreach (['/', '/san-pham'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('<script>alert(1)</script>', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
    }

    public function test_customer_cannot_access_any_admin_crud_endpoint(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        foreach (['/admin/dashboard', '/admin/categories', '/admin/categories/create',
            '/admin/products', '/admin/products/create', '/admin/orders'] as $url) {
            $this->get($url)->assertRedirect('/')->assertSessionHas('error');
        }
        $this->post('/admin/categories', ['name' => 'Forbidden'])->assertRedirect('/');
        $this->post('/admin/products', [])->assertRedirect('/');
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_array_category_name_can_render_validation_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->from('/admin/categories/create')->post('/admin/categories', ['name' => ['invalid']])
            ->assertSessionHasErrors('name');
        $this->get('/admin/categories/create')->assertOk();
    }

    public function test_invalid_array_product_name_can_render_validation_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->from('/admin/products/create')->post('/admin/products', ['name' => ['invalid']])
            ->assertSessionHasErrors('name');
        $this->get('/admin/products/create')->assertOk();
    }

    public function test_invalid_array_email_can_render_registration_form(): void
    {
        $this->from('/register')->post('/register', ['email' => ['invalid']])
            ->assertSessionHasErrors('email');
        $this->get('/register')->assertOk();
    }

    public function test_category_edit_does_not_render_a_stray_angle_bracket(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Markup', 'slug' => 'markup']);
        $this->get('/admin/categories/'.$category->id.'/edit')->assertOk()
            ->assertDontSee('required>>', false);
    }

    public function test_exactly_72_byte_passwords_register_and_login(): void
    {
        foreach ([str_repeat('a', 72), str_repeat('ắ', 24)] as $index => $password) {
            $email = 'limit'.$index.'@example.test';
            $this->post('/register', ['name' => 'Byte boundary', 'email' => $email,
                'password' => $password, 'password_confirmation' => $password])->assertRedirect('/login');
            $this->post('/login', ['email' => $email, 'password' => $password])->assertRedirect('/');
            $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_multibyte_password_limit_counts_bytes_not_characters(): void
    {
        $password = str_repeat('ắ', 25); // 25 characters, 75 UTF-8 bytes.
        $this->postJson('/register', ['name' => 'Too many bytes', 'email' => 'utf8@example.test',
            'password' => $password, 'password_confirmation' => $password])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rejects_nul_without_authenticating(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->postJson('/login', ['email' => $user->email, 'password' => "secret123\0suffix"])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
    }

    public function test_all_forms_handle_array_old_input_without_losing_valid_values(): void
    {
        $bad = array_fill_keys(['name', 'email', 'description', 'price', 'quantity', 'category_id',
            '_version', 'customer_name', 'phone', 'address', 'note', 'payment_method'], ['invalid']);
        foreach (['/login', '/register'] as $url) {
            $this->withSession(['_old_input' => $bad])->get($url)->assertOk();
        }
        $category = Category::create(['name' => 'Form category', 'slug' => 'form-category']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Form shoe',
            'slug' => 'form-shoe', 'price' => '12.50', 'quantity' => 3, 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['/admin/categories/create', '/admin/categories/'.$category->id.'/edit',
            '/admin/products/create', '/admin/products/'.$product->id.'/edit'] as $url) {
            $response = $this->withSession(['_old_input' => $bad])->get($url)->assertOk();
            if (str_contains($url, '/products/') && str_ends_with($url, '/edit')) {
                $response->assertSee('name="_version" value=""', false);
            }
        }
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->post('/gio-hang/them/'.$product->id);
        $this->withSession(['_old_input' => $bad])->get('/checkout')->assertOk();
        $this->withSession(['_old_input' => ['customer_name' => 'Keep this name', 'phone' => '0900000000']])
            ->get('/checkout')->assertOk()->assertSee('Keep this name')->assertSee('0900000000');
    }
}
