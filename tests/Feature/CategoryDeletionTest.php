<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_nonempty_category_keeps_even_inactive_out_of_stock_products_and_images(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $disk = Storage::fake('public');
        $disk->put('products/retained.png', 'image');
        $category = Category::create(['name' => 'Keep', 'slug' => 'keep']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Keep shoe',
            'slug' => 'keep-shoe', 'price' => 100, 'quantity' => 0,
            'is_active' => false, 'image' => 'products/retained.png']);

        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'Không thể xóa danh mục đang có sản phẩm. Vui lòng chuyển hoặc xóa các sản phẩm trước.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
        $disk->assertExists('products/retained.png');
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Không thể xóa danh mục đang có sản phẩm.');
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Empty', 'slug' => 'empty']);

        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
