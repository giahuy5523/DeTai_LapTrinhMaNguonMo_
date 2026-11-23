<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $users = User::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'keyword'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function show(User $user)
    {
        abort_unless(auth()->check() && auth()->user()->role === 'admin', 403);

        $orders = Order::query()->where('user_id', $user->id)->with('items')->latest()->paginate(10);
        $orderCount = Order::where('user_id', $user->id)->count();
        $validOrderTotal = Order::where('user_id', $user->id)
            ->where('status', 'completed')->sum('total_amount');

        return view('admin.users.show', compact('user', 'orders', 'orderCount', 'validOrderTotal'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->check() && auth()->user()->role === 'admin', 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['customer', 'admin'])],
        ]);

        if ($user->is(auth()->user()) && $validated['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Bạn không thể tự hạ quyền Admin của mình.'])->withInput();
        }

        if ($user->role === 'admin' && $validated['role'] !== 'admin'
            && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['role' => 'Không thể hạ quyền Admin cuối cùng.'])->withInput();
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'Đã cập nhật tài khoản.');
    }

    public function destroy(User $user)
    {
        abort_unless(auth()->check() && auth()->user()->role === 'admin', 403);
        if ($user->is(auth()->user())) {
            return back()->with('error', 'Bạn không thể xóa tài khoản đang đăng nhập.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Không thể xóa Admin cuối cùng.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Đã xóa tài khoản.');
    }
}
