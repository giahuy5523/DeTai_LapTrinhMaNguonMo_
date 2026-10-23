<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ProductImages
{
    public static function deleteUnused(?string $path): void
    {
        if (! $path || Product::where('image', $path)->exists()) {
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
