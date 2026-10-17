<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category; // Nhớ import Model Category
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{

    // 1. Hiển thị danh sách Sản phẩm (kèm Tìm kiếm & Phân trang)
    public function index(Request $request)
    {
        $keyword = $request->input('keyword');

        // Lấy danh sách sản phẩm kèm danh mục, lọc theo tên nếu có từ khóa
        $products = Product::with('category')
            ->when($keyword, function ($query, $keyword) {
                return $query->where('name', 'like', '%' . $keyword . '%');
            })
            ->latest()
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
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'name' => 'required|string|max:255|unique:products,name',
            'price' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'quantity' => 'required|integer|min:0|max:2147483647',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Bắt buộc là file ảnh, tối đa 2MB
        ], [
            'name.unique' => 'Tên sản phẩm này đã tồn tại.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'image.image' => 'File tải lên phải là hình ảnh.',
        ]);

        $request->merge(['slug' => \Illuminate\Support\Str::slug($request->name)]);
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
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image' => 'Không lưu được ảnh. Vui lòng thử lại.',
                ]);
            }
        }

        // Lưu vào Database
        try {
            Product::create([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name),
                'price' => $request->price,
                'quantity' => $request->quantity,
                'image' => $imagePath,
                'description' => $request->description,
            ]);
        } catch (\Throwable $exception) {
            if ($imagePath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
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
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'price' => 'required|numeric|min:0|max:9999999999999.99|decimal:0,2',
            'quantity' => 'required|integer|min:0|max:2147483647',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $request->merge(['slug' => \Illuminate\Support\Str::slug($request->name)]);
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
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image' => 'Không lưu được ảnh. Vui lòng thử lại.',
                ]);
            }
        }

        try {
            $product->update([
                'category_id' => $request->category_id,
                'name' => $request->name,
                'slug' => \Illuminate\Support\Str::slug($request->name),
                'price' => $request->price,
                'quantity' => $request->quantity,
                'image' => $imagePath,
                'description' => $request->description,
            ]);
        } catch (\Throwable $exception) {
            if ($imagePath && $imagePath !== $oldImagePath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
            }
            throw $exception;
        }

        if ($oldImagePath && $oldImagePath !== $imagePath) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('admin.products.index')->with('success', 'Cập nhật sản phẩm thành công!');
    }

    // 6. Xóa sản phẩm
    public function destroy(Product $product)
    {
        // Xóa bản ghi trước, giữ ảnh nếu thao tác database thất bại.
        $product->delete();
        if ($product->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
        }

        return redirect()->route('admin.products.index')->with('success', 'Đã xóa sản phẩm!');
    }
}
