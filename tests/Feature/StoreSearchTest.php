<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createShoe(string $name, string $slug, array $extra = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'store-search'], ['name' => 'Store Search']);

        return Product::create(array_replace([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price' => '1200000.00',
            'quantity' => 5,
            'is_active' => true,
        ], $extra));
    }

    public function test_store_search_filters_available_products_by_name(): void
    {
        $matching = $this->createShoe('Nike Air Max', 'nike-air-max');
        $this->createShoe('Adidas Forum', 'adidas-forum');
        $this->createShoe('Nike sold out', 'nike-sold-out', ['quantity' => 0]);
        $this->createShoe('Nike hidden', 'nike-hidden', ['is_active' => false]);

        $response = $this->get(route('store.index', ['keyword' => 'Nike']))->assertOk();
        $products = $response->viewData('products');

        $this->assertSame([$matching->id], $products->pluck('id')->all());
        $response->assertSee('value="Nike"', false)
            ->assertSee('Tìm thấy 1 sản phẩm phù hợp')
            ->assertSee('Xóa tìm kiếm');
    }

    public function test_store_search_pagination_keeps_the_keyword(): void
    {
        for ($index = 1; $index <= 13; $index++) {
            $this->createShoe('Nike Model '.$index, 'nike-model-'.$index);
        }

        $firstPage = $this->get('/san-pham?keyword=Nike')->assertOk();
        $this->assertCount(12, $firstPage->viewData('products'));
        $firstPage->assertSee('keyword=Nike&amp;page=2', false);

        $secondPage = $this->get('/san-pham?keyword=Nike&page=2')->assertOk();
        $this->assertSame(2, $secondPage->viewData('products')->currentPage());
        $this->assertSame(13, $secondPage->viewData('products')->total());
    }

    public function test_store_search_shows_a_clear_message_when_no_product_matches(): void
    {
        $this->createShoe('Adidas Forum', 'adidas-forum');

        $this->get('/san-pham?keyword=Converse')
            ->assertOk()
            ->assertSee('Không tìm thấy sản phẩm phù hợp với từ khóa')
            ->assertSee('Xem tất cả sản phẩm');
    }

    public function test_store_search_rejects_non_string_keywords(): void
    {
        $this->get('/san-pham?keyword%5B%5D=Nike')
            ->assertSessionHasErrors('keyword');
    }
}
