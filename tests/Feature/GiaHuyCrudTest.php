<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GiaHuyCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ9sAAAAASUVORK5CYII='
        ));
    }

    public function test_category_crud_and_slug_collisions_return_form_errors(): void
    {
        $this->admin();
        $this->post('/admin/categories', ['name' => 'Giày Nike'])
            ->assertRedirect(route('admin.categories.index'));
        $category = Category::firstOrFail();
        $this->post('/admin/categories', ['name' => 'Giay Nike'])->assertSessionHasErrors('slug');
        $this->post('/admin/categories', ['name' => '!!!'])->assertSessionHasErrors('slug');
        $this->post('/admin/categories', ['name' => 'Adidas'])
            ->assertRedirect(route('admin.categories.index'));
        $other = Category::where('slug', 'adidas')->firstOrFail();
        $this->put('/admin/categories/'.$other->id, ['name' => 'Giay Nike'])
            ->assertSessionHasErrors('slug');
        $this->put('/admin/categories/'.$category->id, ['name' => 'Giày Nike'])
            ->assertRedirect(route('admin.categories.index'));
        $this->put('/admin/categories/'.$category->id, ['name' => 'Nike mới'])
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'nike-moi']);
        $this->delete('/admin/categories/'.$other->id)
            ->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $other->id]);
    }

    public function test_product_crud_validates_input_and_upload_replacement_and_delete(): void
    {
        $this->admin();
        Storage::fake('public');
        $category = Category::create(['name' => 'Nike', 'slug' => 'nike']);
        $data = ['category_id' => $category->id, 'name' => 'Giày chạy', 'price' => 1250000, 'quantity' => 5];
        $this->post('/admin/products', array_merge($data, ['price' => -1, 'quantity' => -1]))
            ->assertSessionHasErrors(['price', 'quantity']);
        $this->post('/admin/products', array_merge($data, ['image' => UploadedFile::fake()->create('bad.txt', 1)]))
            ->assertSessionHasErrors('image');
        $this->post('/admin/products', array_merge($data, ['image' => $this->image('shoe.png')]))
            ->assertRedirect(route('admin.products.index'));
        $product = Product::firstOrFail();
        $oldImage = $product->image;
        Storage::disk('public')->assertExists($oldImage);
        $this->post('/admin/products', array_merge($data, ['name' => 'Giay chay']))
            ->assertSessionHasErrors('slug');
        $this->post('/admin/products', array_merge($data, ['name' => '!!!']))
            ->assertSessionHasErrors('slug');
        $this->put('/admin/products/'.$product->id, $data)
            ->assertRedirect(route('admin.products.index'));
        $this->assertSame($oldImage, $product->fresh()->image);
        $this->put('/admin/products/'.$product->id, array_merge($data, ['image' => $this->image('new.png'), 'price' => 1500000]))
            ->assertRedirect(route('admin.products.index'));
        $newImage = $product->fresh()->image;
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($newImage);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 1500000]);
        $this->delete('/admin/products/'.$product->id)->assertRedirect(route('admin.products.index'));
        Storage::disk('public')->assertMissing($newImage);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_seeder_can_run_twice_without_overwriting_existing_accounts(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        $admin->update(['name' => 'Changed admin', 'password' => bcrypt('different-password')]);
        $password = $admin->fresh()->password;
        $this->seed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('categories', 2);
        $this->assertSame('Changed admin', $admin->fresh()->name);
        $this->assertSame($password, $admin->fresh()->password);
    }

    public function test_unsupported_show_routes_are_not_registered(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.categories.show'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.products.show'));
    }

    public function test_description_must_be_text_and_invalid_data_is_not_saved(): void
    {
        $this->admin();
        $this->post('/admin/categories', ['name' => 'Invalid category', 'description' => ['bad']])
            ->assertSessionHasErrors('description');
        $category = Category::create(['name' => 'Valid category', 'slug' => 'valid-category']);
        $this->post('/admin/products', [
            'name' => 'Invalid product', 'category_id' => $category->id,
            'price' => 1000, 'quantity' => 1, 'description' => ['bad'],
        ])->assertSessionHasErrors('description');
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_customer_cannot_use_admin_crud(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->post('/admin/categories', ['name' => 'Forbidden'])->assertRedirect('/login');
        $this->assertDatabaseCount('categories', 0);
    }
}
