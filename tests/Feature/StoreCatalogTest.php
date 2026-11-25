<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(string $name, string $slug): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function makeProduct(Category $category, string $name, string $slug, string $price, array $extra = []): Product
    {
        return Product::create(array_replace([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'quantity' => 8,
            'is_active' => true,
            'description' => 'Mô tả '.$name,
        ], $extra));
    }

    public function test_store_can_filter_by_category_and_price_range_together(): void
    {
        $nike = $this->makeCategory('Nike', 'nike');
        $adidas = $this->makeCategory('Adidas', 'adidas');

        $insideRange = $this->makeProduct($nike, 'Nike Air', 'nike-air', '1500000.00');
        $this->makeProduct($nike, 'Nike Budget', 'nike-budget', '700000.00');
        $this->makeProduct($nike, 'Nike Premium', 'nike-premium', '2500000.00');
        $this->makeProduct($adidas, 'Adidas Forum', 'adidas-forum', '1500000.00');

        $response = $this->get(route('store.index', [
            'category_id' => $nike->id,
            'min_price' => 1000000,
            'max_price' => 2000000,
        ]))->assertOk();

        $products = $response->viewData('products');
        $this->assertSame([$insideRange->id], $products->pluck('id')->all());
        $response->assertSee('Nike Air')
            ->assertDontSee('Adidas Forum')
            ->assertDontSee('Nike Budget')
            ->assertDontSee('Nike Premium');
    }

    public function test_store_can_sort_products_by_price_and_name(): void
    {
        $category = $this->makeCategory('Running', 'running');
        $expensive = $this->makeProduct($category, 'Zoom Runner', 'zoom-runner', '2200000.00');
        $cheap = $this->makeProduct($category, 'Air Basic', 'air-basic', '900000.00');
        $middle = $this->makeProduct($category, 'Court Classic', 'court-classic', '1400000.00');

        $ascending = $this->get(route('store.index', ['sort' => 'price_asc']))->assertOk();
        $this->assertSame([$cheap->id, $middle->id, $expensive->id], $ascending->viewData('products')->pluck('id')->all());

        $descending = $this->get(route('store.index', ['sort' => 'price_desc']))->assertOk();
        $this->assertSame([$expensive->id, $middle->id, $cheap->id], $descending->viewData('products')->pluck('id')->all());

        $alphabetical = $this->get(route('store.index', ['sort' => 'name_asc']))->assertOk();
        $this->assertSame([$cheap->id, $middle->id, $expensive->id], $alphabetical->viewData('products')->pluck('id')->all());
    }

    public function test_store_pagination_keeps_keyword_category_price_range_and_sort(): void
    {
        $category = $this->makeCategory('Nike', 'nike');
        $otherCategory = $this->makeCategory('Adidas', 'adidas');

        for ($i = 1; $i <= 13; $i++) {
            $this->makeProduct($category, 'Nike Model '.$i, 'nike-model-'.$i, '1200000.00');
        }
        $this->makeProduct($otherCategory, 'Nike Other Category', 'nike-other-category', '1200000.00');
        $this->makeProduct($category, 'Nike Too Cheap', 'nike-too-cheap', '500000.00');

        $filters = [
            'keyword' => 'Nike',
            'category_id' => $category->id,
            'min_price' => 1000000,
            'max_price' => 1500000,
            'sort' => 'price_asc',
        ];

        $firstPage = $this->get(route('store.index', $filters))->assertOk();
        $this->assertCount(12, $firstPage->viewData('products'));
        $this->assertSame('Nike', $firstPage->viewData('keyword'));

        $pageTwoUrl = $firstPage->viewData('products')->url(2);
        parse_str((string) parse_url($pageTwoUrl, PHP_URL_QUERY), $query);

        $this->assertSame('Nike', $query['keyword'] ?? null);
        $this->assertSame((string) $category->id, (string) ($query['category_id'] ?? ''));
        $this->assertSame('1000000', (string) ($query['min_price'] ?? ''));
        $this->assertSame('1500000', (string) ($query['max_price'] ?? ''));
        $this->assertSame('price_asc', $query['sort'] ?? null);
        $this->assertSame('2', (string) ($query['page'] ?? ''));

        $secondPage = $this->get($pageTwoUrl)->assertOk();
        $this->assertSame(13, $secondPage->viewData('products')->total());
        $this->assertSame(2, $secondPage->viewData('products')->currentPage());
    }

    public function test_store_rejects_a_price_range_where_minimum_exceeds_maximum(): void
    {
        $this->get(route('store.index', [
            'min_price' => 2000000,
            'max_price' => 1000000,
        ]))->assertRedirect()->assertSessionHasErrors('max_price');
    }

    public function test_product_detail_shows_product_information_and_related_products(): void
    {
        $category = $this->makeCategory('Lifestyle', 'lifestyle');
        $product = $this->makeProduct($category, 'Everyday Sneaker', 'everyday-sneaker', '1350000.00', [
            'quantity' => 4,
            'description' => 'Giày phù hợp cho hoạt động hằng ngày.',
        ]);
        $related = $this->makeProduct($category, 'Daily Runner', 'daily-runner', '1450000.00');

        $this->get(route('store.show', ['product' => $product->slug]))
            ->assertOk()
            ->assertSee('Everyday Sneaker')
            ->assertSee('Lifestyle')
            ->assertSee('1.350.000 đ')
            ->assertSee('Còn hàng')
            ->assertSee('Giày phù hợp cho hoạt động hằng ngày.')
            ->assertSee('Daily Runner')
            ->assertSee(route('cart.add', $product->id));

        $this->assertNotSame($product->id, $related->id);
    }

    public function test_product_detail_returns_not_found_for_discontinued_products(): void
    {
        $category = $this->makeCategory('Training', 'training');
        $product = $this->makeProduct($category, 'Old Training Shoe', 'old-training-shoe', '800000.00', [
            'is_active' => false,
        ]);

        $this->get(route('store.show', ['product' => $product->slug]))->assertNotFound();
    }
}
