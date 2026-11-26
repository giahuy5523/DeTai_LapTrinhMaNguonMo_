<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundled_product_images_and_credits_exist_and_are_valid_jpegs(): void
    {
        $this->assertFileExists(public_path('images/CREDITS.md'));

        $files = ProductImages::demoFiles();
        $this->assertCount(8, $files);

        foreach ($files as $file) {
            $path = public_path('images/products/'.$file);
            $this->assertFileExists($path);
            $info = getimagesize($path);
            $this->assertIsArray($info);
            $this->assertSame(IMAGETYPE_JPEG, $info[2]);
            $this->assertLessThanOrEqual(640, $info[0]);
        }
    }

    public function test_product_images_url_resolves_bundled_paths_and_storage_fallback(): void
    {
        $expectedBundled = asset('images/products/nike-af1.jpg');

        $this->assertNull(ProductImages::url(null));
        $this->assertNull(ProductImages::url('   '));
        $this->assertSame($expectedBundled, ProductImages::url('Images/nike-af1.jpg'));
        $this->assertSame($expectedBundled, ProductImages::url('images/products/nike-af1.jpg'));
        $this->assertSame($expectedBundled, ProductImages::url('/images/products/nike-af1.jpg'));

        $expectedStorageFallback = is_file(public_path('storage/products/nike-af1.jpg'))
            ? asset('storage/products/nike-af1.jpg')
            : $expectedBundled;
        $this->assertSame($expectedStorageFallback, ProductImages::url('products/nike-af1.jpg'));

        $this->assertSame(
            asset('storage/products/custom-nonexistent-upload.png'),
            ProductImages::url('products/custom-nonexistent-upload.png')
        );
    }

    public function test_delete_unused_preserves_bundled_images_folder(): void
    {
        ProductImages::deleteUnused('Images/nike-af1.jpg');
        ProductImages::deleteUnused('images/products/nike-af1.jpg');

        $this->assertFileExists(public_path('images/products/nike-af1.jpg'));
    }

    public function test_public_cart_and_admin_views_render_resolved_bundled_image_urls(): void
    {
        $category = Category::create(['name' => 'Nike', 'slug' => 'nike']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Nike Air Force 1 Bundled',
            'slug' => 'nike-air-force-1-bundled',
            'price' => '2900000.00',
            'quantity' => 5,
            'image' => 'Images/nike-af1.jpg',
            'is_active' => true,
        ]);

        $expectedUrl = asset('images/products/nike-af1.jpg');

        $this->get(route('home'))->assertOk()->assertSee($expectedUrl, false);
        $this->get(route('store.index'))->assertOk()->assertSee($expectedUrl, false);

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->post(route('cart.add', $product->id), ['quantity' => 1]);
        $this->get(route('cart.index'))->assertOk()->assertSee($expectedUrl, false);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->get(route('admin.products.index'))->assertOk()->assertSee($expectedUrl, false);
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee($expectedUrl, false);
    }
}
