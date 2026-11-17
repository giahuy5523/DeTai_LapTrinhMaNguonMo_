<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GiaHuyImageSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Test category', 'slug' => 'test-category']);

        return ['category_id' => $category->id, 'name' => 'Test shoe', 'price' => 1250000, 'quantity' => 3];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('new.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ9sAAAAASUVORK5CYII='
        ));
    }

    public function test_failed_upload_keeps_existing_image_and_database_record(): void
    {
        $data = $this->data();
        $disk = Storage::fake('public');
        $disk->put('products/old.png', 'old-image');
        $product = Product::create($data + ['slug' => 'test-shoe', 'image' => 'products/old.png']);
        $failingDisk = \Mockery::mock($disk);
        $failingDisk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('public', $failingDisk);

        $this->put(route('admin.products.update', $product), $data + ['_version' => $product->fresh()->editVersion(), 'image' => $this->image()])
            ->assertSessionHasErrors('image');
        $disk->assertExists('products/old.png');
        $this->assertSame('products/old.png', $product->fresh()->image);
        $this->assertSame(['products/old.png'], $disk->allFiles('products'));
    }

    public function test_database_failure_during_replacement_preserves_old_image_and_cleans_new_upload(): void
    {
        $data = $this->data();
        $disk = Storage::fake('public');
        $disk->put('products/old.png', 'old-image');
        $product = Product::create($data + ['slug' => 'test-shoe', 'image' => 'products/old.png']);
        Product::updating(function () {
            throw new \RuntimeException('Simulated database failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->put(route('admin.products.update', $product), $data + ['_version' => $product->fresh()->editVersion(), 'image' => $this->image()]);
            $this->fail('Expected simulated database failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated database failure', $exception->getMessage());
        } finally {
            Product::flushEventListeners();
        }
        $this->assertSame('products/old.png', $product->fresh()->image);
        $disk->assertExists('products/old.png');
        $this->assertSame(['products/old.png'], $disk->allFiles('products'));
    }

    public function test_create_database_failure_cleans_uploaded_file(): void
    {
        $data = $this->data();
        $disk = Storage::fake('public');
        Product::creating(function () {
            throw new \RuntimeException('Simulated insert failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('admin.products.store'), $data + ['image' => $this->image()]);
            $this->fail('Expected simulated insert failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated insert failure', $exception->getMessage());
        } finally {
            Product::flushEventListeners();
        }
        $this->assertSame([], $disk->allFiles());
        $this->assertDatabaseCount('products', 0);
    }

    public function test_numeric_limits_match_mysql_columns_and_decimal_values_are_accepted(): void
    {
        $data = $this->data();
        foreach ([
            ['price' => '10000000000000', 'quantity' => '2147483648'],
            ['price' => '1.234', 'quantity' => '1.5'],
        ] as $bad) {
            $this->post(route('admin.products.store'), array_replace($data, $bad))
                ->assertSessionHasErrors(['price', 'quantity']);
        }
        $this->post(route('admin.products.store'), array_replace($data, ['price' => '1250.50']))
            ->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['name' => 'Test shoe', 'price' => '1250.50']);
    }
}
