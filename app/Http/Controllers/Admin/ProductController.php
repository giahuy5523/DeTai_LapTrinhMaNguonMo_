<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category; // Nhớ import Model Category
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // 1. Hiển thị danh sách Sản phẩm
    public function index()
    {
        // Dùng with('category') để lấy luôn tên danh mục, tránh lỗi N+1 query
        $products = Product::with('category')->latest()->paginate(10);
        return view('admin.products.index', compact('products'));
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
            'name' => 'required|string|max:255|unique:products,name',
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Bắt buộc là file ảnh, tối đa 2MB
        ], [
            'name.unique' => 'Tên sản phẩm này đã tồn tại.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'image.image' => 'File tải lên phải là hình ảnh.',
        ]);

        $imagePath = null;
        // Kiểm tra xem người dùng có chọn upload ảnh không
        if ($request->hasFile('image')) {
            // Lưu ảnh vào thư mục storage/app/public/products và lấy ra đường dẫn
            $imagePath = $request->file('image')->store('products', 'public');
        }

        // Lưu vào Database
        Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
            'price' => $request->price,
            'quantity' => $request->quantity,
            'image' => $imagePath,
            'description' => $request->description,
        ]);

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
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $imagePath = $product->image;
        
        // Nếu có upload ảnh mới
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ khỏi ổ cứng (nếu tồn tại)
            if ($imagePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
            }
            // Lưu ảnh mới
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
            'price' => $request->price,
            'quantity' => $request->quantity,
            'image' => $imagePath,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.products.index')->with('success', 'Cập nhật sản phẩm thành công!');
    }

    // 6. Xóa sản phẩm
    public function destroy(Product $product)
    {
        // Xóa ảnh gốc trước khi xóa data
        if ($product->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
        }
        
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Đã xóa sản phẩm!');
    }
}