<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ProductImages
{
    /**
     * Bundled product image filenames stored under public/images/products.
     *
     * @return list<string>
     */
    public static function demoFiles(): array
    {
        return [
            'ananas-urbas.jpg',
            'ananas-track6.jpg',
            'ananas-vintas.jpg',
            'nike-af1.jpg',
            'nike-jordan1.jpg',
            'nike-pegasus.jpg',
            'adidas-ultraboost.jpg',
            'adidas-samba.jpg',
        ];
    }

    public static function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');

        if (preg_match('#^images/(.+)$#i', $normalized, $matches)) {
            if (is_file(public_path('images/'.$matches[1]))) {
                return asset('images/'.$matches[1]);
            }

            $bundledFile = basename($matches[1]);
            if ($bundledFile !== '' && is_file(public_path('images/products/'.$bundledFile))) {
                return asset('images/products/'.$bundledFile);
            }

            return asset('images/'.$matches[1]);
        }

        if (is_file(public_path('storage/'.$normalized))) {
            return asset('storage/'.$normalized);
        }

        $bundledFile = basename($normalized);
        if ($bundledFile !== '' && is_file(public_path('images/products/'.$bundledFile))) {
            return asset('images/products/'.$bundledFile);
        }

        return asset('storage/'.$normalized);
    }

    public static function deleteUnused(?string $path): void
    {
        if (! $path) {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (preg_match('#^images/#i', $normalized) || Product::where('image', $path)->exists()) {
            return;
        }

        try {
            $disk = Storage::disk('public');
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new \RuntimeException('Image deletion failed');
            }
        } catch (\Throwable $exception) {
            Log::warning('Không dọn được ảnh sản phẩm.', ['path' => $path]);
            session()->flash('warning', 'Dữ liệu đã lưu, nhưng chưa dọn được ảnh cũ. Vui lòng kiểm tra quyền thư mục storage.');
        }
    }
}
