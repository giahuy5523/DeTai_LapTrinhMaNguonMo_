<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // 1. Hiển thị danh sách danh mục
    public function index()
    {
        $categories = Category::latest()->paginate(10);
        return view('admin.categories.index', compact('categories'));
    }

    // 2. Trả về form thêm mới
    public function create()
    {
        return view('admin.categories.create');
    }

    // 3. Xử lý lưu dữ liệu thêm mới vào database
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ], [
            'name.required' => 'Tên danh mục không được để trống.',
            'name.unique' => 'Tên danh mục này đã tồn tại.',
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name), // Tự động tạo slug chuẩn URL (VD: Giày Nike -> giay-nike)
            'description' => $request->description,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Thêm danh mục thành công!');
    }
// 4. Trả về form chỉnh sửa danh mục
    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    // 5. Xử lý cập nhật dữ liệu vào database
    public function update(Request $request, Category $category)
    {
        $request->validate([
            // unique:categories,name,{$category->id} giúp bỏ qua check trùng lặp với chính nó
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
        ], [
            'name.required' => 'Tên danh mục không được để trống.',
            'name.unique' => 'Tên danh mục này đã tồn tại.',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
            'description' => $request->description,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Cập nhật danh mục thành công!');
    }

    // 6. Xử lý xóa danh mục
    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Đã xóa danh mục!');
    }
    // Các hàm edit, update, destroy sẽ làm tiếp theo...
}