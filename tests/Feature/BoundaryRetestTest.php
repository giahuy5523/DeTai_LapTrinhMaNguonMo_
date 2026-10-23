<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoundaryRetestTest extends TestCase
{
    use RefreshDatabase;

    private function shoe(array $extra = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'boundary'], ['name' => 'Boundary']);

        return Product::create(array_replace(['category_id' => $category->id, 'name' => 'Boundary shoe',
            'slug' => 'boundary-shoe', 'price' => '12.50', 'quantity' => 3, 'is_active' => true], $extra));
    }

    private function receiver(array $extra = []): array
    {
        return array_replace(['customer_name' => 'Boundary buyer', 'phone' => '0900000000',
            'address' => 'Test address', 'payment_method' => 'cod'], $extra);
    }

    public function test_phone_longer_than_sql_column_is_rejected_before_order_creation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $phone = '+0'.'12345678----------'.'9'; // 21 characters, 11 digits; SQL VARCHAR(20).
        $this->assertSame(21, strlen($phone));
        $this->postJson('/checkout', $this->checkoutFields($this->receiver(['phone' => $phone])))
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $product->fresh()->quantity);
        $this->assertCount(1, session('cart'));
    }

    public function test_phone_exactly_sql_column_length_can_be_checked_out(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $phone = '+0'.'12345678---------'.'9';
        $this->assertSame(20, strlen($phone));
        $this->post('/checkout', $this->checkoutFields($this->receiver(['phone' => $phone])))
            ->assertRedirect(route('checkout.success'));
        $this->assertDatabaseHas('orders', ['phone' => $phone, 'total_amount' => '12.50']);
        $this->assertSame(2, $product->fresh()->quantity);
    }

    public function test_category_cascade_keeps_order_snapshot_and_cancellation_works(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);
        $product = $this->shoe();
        $this->post(route('cart.add', $product->id));
        $this->post('/checkout', $this->checkoutFields($this->receiver()))->assertRedirect(route('checkout.success'));
        $order = Order::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->delete(route('admin.categories.destroy', $product->category_id))->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => null,
            'product_name' => 'Boundary shoe', 'subtotal' => '12.50']);
        $this->actingAs($customer)->get('/gio-hang')->assertOk()->assertSee('Boundary shoe');
        $this->patch(route('orders.cancel', $order->id))->assertRedirect(route('cart.index'));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_failed_file_cleanup_keeps_successful_database_delete_and_warns(): void
    {
        $disk = Storage::fake('public');
        $disk->put('products/undeletable.png', 'image');
        $product = $this->shoe(['image' => 'products/undeletable.png']);
        $mock = \Mockery::mock($disk);
        $mock->shouldReceive('delete')->once()->with('products/undeletable.png')->andReturn(false);
        Storage::set('public', $mock);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success')->assertSessionHas('warning');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $disk->assertExists('products/undeletable.png');
    }

    public function test_fake_png_and_svg_uploads_are_rejected_without_files_or_records(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        foreach ([UploadedFile::fake()->createWithContent('code.png', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('code.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')] as $image) {
            // Fake File reports MIME by filename; wrap it to exercise real content sniffing.
            $image = new UploadedFile($image->getPathname(), $image->getClientOriginalName(), null, null, true);
            $this->post(route('admin.products.store'), ['name' => 'Bad image', 'category_id' => $product->category_id,
                'price' => '10.00', 'quantity' => 1, 'image' => $image])->assertSessionHasErrors('image');
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_search_ties_have_stable_nonoverlapping_pages_and_escaped_keyword(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        for ($index = 0; $index < 11; $index++) {
            $this->shoe(['name' => 'Shoe '.$index, 'slug' => 'shoe-'.$index, 'created_at' => '2026-01-01 00:00:00']);
        }
        $first = $this->get('/admin/products?keyword=Shoe')->assertOk()->viewData('products');
        $second = $this->get('/admin/products?keyword=Shoe&page=2')->assertOk()->viewData('products');
        $this->assertSame([11, 10, 9, 8, 7], $first->pluck('id')->all());
        $this->assertSame([6, 5, 4, 3, 2], $second->pluck('id')->all());
        $this->get('/admin/products?keyword='.urlencode('"><script>alert(1)</script>'))->assertOk()
            ->assertDontSee('"><script>alert(1)</script>', false);
    }

    public function test_deleted_product_update_route_does_not_accept_a_stale_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->shoe();
        $payload = $product->only(['name', 'category_id', 'price', 'quantity']) + ['_version' => $product->editVersion()];
        $product->delete();
        $this->put(route('admin.products.update', $product), $payload)->assertNotFound();
        $this->assertDatabaseCount('products', 0);
    }
}
