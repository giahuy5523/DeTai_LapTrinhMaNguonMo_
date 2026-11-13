<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Rules\SqlText;
use App\Support\ProductImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    // 1. Hiển thị danh sách Sản phẩm (kèm Tìm kiếm & Phân trang)
    public function index(Request $request)
    {
        $request->validate(['keyword' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1']]);
        $keyword = $request->input('keyword');

        // Lấy danh sách sản phẩm kèm danh mục, lọc theo tên nếu có từ khóa
        $products = Product::with('category')
            ->when($keyword !== null && $keyword !== '', function ($query) use ($keyword) {
                return $query->where('name', 'like', '%'.$keyword.'%');
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(5); // Bạn có thể chỉnh 5 thành số sản phẩm hiển thị trên 1 trang (ví dụ 10)

        return view('admin.products.index', compact('products', 'keyword'));
    }

    // 2. Trả về form Thêm mới Sản phẩm
    public function create()
    {
        // Lấy tất cả danh mục để truyền vào thẻ <select>
        $categories = Category::all();

        return view('admin.products.create', compact('categories'));
    }

    // 3. Xử lý lưu Sản phẩm và Upload ảnh
    public function store(Request $request)
    {
        // Kiểm tra dữ liệu đầu vào (Validation)
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'description' => ['nullable', 'string', new SqlText],
            'name' => 'required|string|max:255|unique:products,name',
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2', 'regex:/^\d{1,13}(\.\d{1,2})?$/D'],
            'quantity' => 'required|integer|min:0|max:2147483647',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Bắt buộc là file ảnh, tối đa 2MB
        ], [
            'name.unique' => 'Tên sản phẩm này đã tồn tại.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'image.image' => 'File tải lên phải là hình ảnh.',
        ]);

        $request->merge(['slug' => Str::slug($request->name)]);
        $request->validate([
            'slug' => ['required', 'max:255', Rule::unique('products', 'slug')],
        ], [
            'slug.required' => 'Tên phải chứa ít nhất một chữ hoặc số để tạo đường dẫn.',
            'slug.unique' => 'Tên này tạo đường dẫn trùng với dữ liệu đã có. Vui lòng chọn tên khác.',
        ]);

        $imagePath = null;
        // Kiểm tra xem người dùng có chọn upload ảnh không
        if ($request->hasFile('image')) {
            // Lưu ảnh vào thư mục storage/app/public/products và lấy ra đường dẫn
            $imagePath = $request->file('image')->store('products', 'public');
            if ($imagePath === false) {
                throw ValidationException::withMessages([
                    'image' => 'Không lưu được ảnh. Vui lòng thử lại.',
                ]);
            }
        }

        // Lưu vào Database
        try {
            DB::transaction(function () use ($request, $imagePath) {
                if (! Category::whereKey($request->category_id)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['category_id' => 'Danh mục đã thay đổi. Vui lòng chọn lại.']);
                }
                Product::create([
                    'category_id' => $request->category_id,
                    'name' => $request->name,
                    'slug' => Str::slug($request->name),
                    'price' => $request->price,
                    'quantity' => $request->quantity,
                    'image' => $imagePath,
                    'description' => $request->description,
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            if ($imagePath) {
                ProductImages::deleteUnused($imagePath);
            }
            throw $exception;
        }

        return redirect()->route('admin.products.index')->with('success', 'Thêm sản phẩm thành công!');
    }

    // 4. Trả về form Sửa
    public function edit(Product $product)
    {
        $categories = Category::all();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    // 5. Cập nhật dữ liệu
    public function update(Request $request, Product $product)
    {
        $request->validate([
            '_version' => ['required', 'string', 'size:64'],
            'category_id' => 'required|integer|exists:categories,id',
            'description' => ['nullable', 'string', new SqlText],
            'name' => 'required|string|max:255|unique:products,name,'.$product->id,
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2', 'regex:/^\d{1,13}(\.\d{1,2})?$/D'],
            'quantity' => 'required|integer|min:0|max:2147483647',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $request->merge(['slug' => Str::slug($request->name)]);
        $request->validate([
            'slug' => ['required', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
        ], [
            'slug.required' => 'Tên phải chứa ít nhất một chữ hoặc số để tạo đường dẫn.',
            'slug.unique' => 'Tên này tạo đường dẫn trùng với dữ liệu đã có. Vui lòng chọn tên khác.',
        ]);

        $imagePath = $product->image;

        $oldImagePath = $product->image;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            if ($imagePath === false) {
                throw ValidationException::withMessages([
                    'image' => 'Không lưu được ảnh. Vui lòng thử lại.',
                ]);
            }
        }

        try {
            DB::transaction(function () use ($request, $product, $imagePath) {
                if (! Category::whereKey($request->category_id)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['category_id' => 'Danh mục đã thay đổi. Vui lòng chọn lại.']);
                }
                $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                if (! hash_equals($locked->editVersion(), $request->input('_version'))) {
                    throw ValidationException::withMessages(['_version' => 'Sản phẩm hoặc tồn kho đã thay đổi. Vui lòng tải lại trang sửa trước khi lưu.']);
                }
                $returnable = OrderItem::where('product_id', $locked->id)
                    ->whereHas('order', fn ($query) => $query->whereIn('status', ['pending', 'processing']))->sum('quantity');
                if ((int) $request->quantity > 2147483647 - (int) $returnable) {
                    throw ValidationException::withMessages(['quantity' => 'Tồn kho phải chừa đủ chỗ để hoàn hàng cho các đơn chưa kết thúc.']);
                }
                $locked->update([
                    'category_id' => $request->category_id,
                    'name' => $request->name,
                    'slug' => Str::slug($request->name),
                    'price' => $request->price,
                    'quantity' => $request->quantity,
                    'image' => $imagePath,
                    'description' => $request->description,
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            if ($imagePath && $imagePath !== $oldImagePath) {
                ProductImages::deleteUnused($imagePath);
            }
            throw $exception;
        }

        if ($oldImagePath && $oldImagePath !== $imagePath) {
            ProductImages::deleteUnused($oldImagePath);
        }

        return redirect()->route('admin.products.index')->with('success', 'Cập nhật sản phẩm thành công!');
    }

    // 6. Xóa sản phẩm
    public function destroy(Product $product)
    {
        // Xóa bản ghi trước, giữ ảnh nếu thao tác database thất bại.
        $image = DB::transaction(function () use ($product) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $image = $locked->image;
            $locked->delete();

            return $image;
        }, 3);
        ProductImages::deleteUnused($image);

        return redirect()->route('admin.products.index')->with('success', 'Đã xóa sản phẩm!');
    }
}
