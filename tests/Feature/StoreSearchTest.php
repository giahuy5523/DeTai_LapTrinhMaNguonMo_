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
        $category = Category::firstOrCreate(
            ['slug' => 'store-search'],
            ['name' => 'Store Search']
        );

        return Product::create(array_replace([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price' => '1200000.00',
            'quantity' => 5,
            'is_active' => true,
        ], $extra));
    }

    public function test_store_search_filters_by_name_and_excludes_unavailable_products(): void
    {
        $nikeAirMax = $this->createShoe('Nike Air Max', 'nike-air-max');
        $this->createShoe('Adidas Forum', 'adidas-forum');
        $nikeZoom = $this->createShoe('Nike Zoom', 'nike-zoom');
        $this->createShoe('Nike sold out', 'nike-sold-out', ['quantity' => 0]);
        $this->createShoe('Nike discontinued', 'nike-discontinued', ['is_active' => false]);

        $response = $this->get(route('store.index', ['keyword' => 'Nike']))
            ->assertOk()
            ->assertSee('value="Nike"', false)
            ->assertSee('Tìm thấy 2 sản phẩm phù hợp')
            ->assertSee('Nike Air Max')
            ->assertSee('Nike Zoom')
            ->assertDontSee('Adidas Forum')
            ->assertDontSee('Nike sold out')
            ->assertDontSee('Nike discontinued');

        $products = $response->viewData('products');
        $this->assertSame([$nikeZoom->id, $nikeAirMax->id], $products->pluck('id')->all());
    }

    public function test_store_search_pagination_keeps_the_normalized_keyword(): void
    {
        for ($index = 1; $index <= 13; $index++) {
            $this->createShoe('Nike Model '.$index, 'nike-model-'.$index);
        }

        $firstPage = $this->get(route('store.index', ['keyword' => '  Nike  ']))->assertOk();
        $this->assertSame('Nike', $firstPage->viewData('keyword'));
        $this->assertCount(12, $firstPage->viewData('products'));
        $firstPage->assertSee('keyword=Nike&amp;page=2', false);

        $secondPage = $this->get(route('store.index', ['keyword' => 'Nike', 'page' => 2]))->assertOk();
        $this->assertSame(2, $secondPage->viewData('products')->currentPage());
        $this->assertSame(13, $secondPage->viewData('products')->total());
    }

    public function test_store_search_shows_a_clear_message_when_no_product_matches(): void
    {
        $this->createShoe('Adidas Forum', 'adidas-forum');

        $this->get(route('store.index', ['keyword' => 'Converse']))
            ->assertOk()
            ->assertSee('Không tìm thấy sản phẩm phù hợp với từ khóa')
            ->assertSee('Xem tất cả sản phẩm');
    }

    public function test_store_search_treats_whitespace_only_keyword_as_no_search(): void
    {
        $available = $this->createShoe('Nike Air Max', 'nike-air-max');
        $this->createShoe('Adidas Forum', 'adidas-forum');
        $soldOut = $this->createShoe('Nike sold out', 'nike-sold-out', ['quantity' => 0]);
        $discontinued = $this->createShoe('Nike discontinued', 'nike-discontinued', ['is_active' => false]);

        $response = $this->get(route('store.index', ['keyword' => '   ']))->assertOk();
        $products = $response->viewData('products');
        $productIds = $products->pluck('id')->all();

        $this->assertSame('', $response->viewData('keyword'));
        $this->assertSame(2, $products->total());
        $response->assertSee('Nike Air Max')->assertSee('Adidas Forum');
        $response->assertDontSee('Tìm thấy 2 sản phẩm phù hợp');
        $this->assertContains($available->id, $productIds);
        $this->assertNotContains($soldOut->id, $productIds);
        $this->assertNotContains($discontinued->id, $productIds);
    }

    public function test_store_search_rejects_non_string_keywords(): void
    {
        $this->get('/san-pham?keyword%5B%5D=Nike')
            ->assertSessionHasErrors('keyword');
    }
}
