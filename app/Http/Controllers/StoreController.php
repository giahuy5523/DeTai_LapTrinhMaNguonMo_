<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    /**
     * Hiển thị cửa hàng với tìm kiếm, lọc danh mục/khoảng giá và sắp xếp.
     * Toàn bộ điều kiện được giữ lại trên URL phân trang.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'keyword' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc,name_asc'],
        ], [
            'category_id.exists' => 'Danh mục được chọn không còn tồn tại.',
            'sort.in' => 'Kiểu sắp xếp không hợp lệ.',
        ]);

        $validator->after(function ($validator) {
            $values = $validator->getData();
            $minPrice = $values['min_price'] ?? null;
            $maxPrice = $values['max_price'] ?? null;

            if ($minPrice !== null && $minPrice !== '' && $maxPrice !== null && $maxPrice !== ''
                && is_numeric($minPrice) && is_numeric($maxPrice)
                && (float) $minPrice > (float) $maxPrice) {
                $validator->errors()->add('max_price', 'Giá tối đa phải lớn hơn hoặc bằng giá tối thiểu.');
            }
        });

        $validated = $validator->validate();

        // Chuẩn hóa từ khóa, tránh trường hợp chỉ nhập khoảng trắng.
        $keyword = trim($validated['keyword'] ?? '');
        $categoryId = $validated['category_id'] ?? null;
        $minPrice = $validated['min_price'] ?? null;
        $maxPrice = $validated['max_price'] ?? null;
        $sort = $validated['sort'] ?? 'newest';

        $query = Product::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->when($keyword !== '', function ($builder) use ($keyword) {
                $builder->where('name', 'like', '%'.$keyword.'%');
            })
            ->when($categoryId, function ($builder) use ($categoryId) {
                $builder->where('category_id', $categoryId);
            })
            ->when($minPrice !== null && $minPrice !== '', function ($builder) use ($minPrice) {
                $builder->where('price', '>=', $minPrice);
            })
            ->when($maxPrice !== null && $maxPrice !== '', function ($builder) use ($maxPrice) {
                $builder->where('price', '<=', $maxPrice);
            });

        switch ($sort) {
            case 'price_asc':
                $query->orderBy('price')->orderBy('id');
                break;
            case 'price_desc':
                $query->orderByDesc('price')->orderByDesc('id');
                break;
            case 'name_asc':
                $query->orderBy('name')->orderBy('id');
                break;
            default:
                $query->orderByDesc('created_at')->orderByDesc('id');
                break;
        }

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::query()->orderBy('name')->get();

        return view('store.index', compact(
            'products',
            'categories',
            'keyword',
            'categoryId',
            'minPrice',
            'maxPrice',
            'sort'
        ));
    }

    /** Hiển thị chi tiết một sản phẩm đang được kinh doanh. */
    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load('category');

        $relatedProducts = Product::with('category')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(4)
            ->get();

        return view('store.show', compact('product', 'relatedProducts'));
    }
}
