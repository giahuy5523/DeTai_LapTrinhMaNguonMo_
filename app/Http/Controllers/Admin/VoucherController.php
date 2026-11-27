<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::query()->orderByDesc('id')->paginate(15);

        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('admin.vouchers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        try {
            Voucher::create($data);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCode($exception)) {
                throw $exception;
            }

            return back()->withErrors(['code' => 'Mã voucher này đã được sử dụng.'])->withInput();
        }

        return redirect()->route('admin.vouchers.index')->with('success', 'Đã tạo voucher.');
    }

    public function edit(Voucher $voucher)
    {
        return view('admin.vouchers.edit', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $data = $this->validatedData($request, $voucher);

        try {
            $voucher->update($data);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCode($exception)) {
                throw $exception;
            }

            return back()->withErrors(['code' => 'Mã voucher này đã được sử dụng.'])->withInput();
        }

        return redirect()->route('admin.vouchers.index')->with('success', 'Đã cập nhật voucher.');
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return redirect()->route('admin.vouchers.index')->with('success', 'Đã xóa voucher.');
    }

    private function validatedData(Request $request, ?Voucher $voucher = null): array
    {
        $rawCode = $request->input('code');
        $request->merge(['code' => is_string($rawCode) ? mb_strtoupper(trim($rawCode)) : $rawCode]);
        $percentage = $request->input('discount_type') === 'percentage';
        $maxDiscountRules = $percentage
            ? ['required', 'numeric', 'gt:0', 'decimal:0,2', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/D']
            : ['exclude_unless:discount_type,percentage'];
        $discountValueRules = ['required', 'numeric', 'gt:0', 'decimal:0,2', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/D'];
        $usageLimitRules = ['nullable', 'integer', 'min:1', 'max:2147483647'];
        if ($percentage) {
            $discountValueRules[] = 'lte:100';
        }
        if ($voucher) {
            $usageLimitRules[] = function ($attribute, $value, $fail) use ($voucher) {
                if ($value !== null && is_numeric($value) && (int) $value < $voucher->used_count) {
                    $fail('Giới hạn lượt không thể nhỏ hơn số lượt đã dùng ('.$voucher->used_count.').');
                }
            };
        }

        $codeUnique = Rule::unique('vouchers', 'code');
        if ($voucher) {
            $codeUnique->ignore($voucher->id);
        }

        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9_-]{2,31}$/',
                $codeUnique,
            ],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => $discountValueRules,
            'max_discount_amount' => $maxDiscountRules,
            'minimum_order_amount' => ['required', 'numeric', 'gte:0', 'decimal:0,2', 'regex:/^\d{1,13}(?:\.\d{1,2})?$/D'],
            'usage_limit' => $usageLimitRules,
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'Mã voucher này đã được sử dụng.',
            'max_discount_amount.required' => 'Voucher giảm theo phần trăm cần có mức giảm tối đa.',
            'ends_at.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
        ]);

        $validated['is_active'] = (bool) $validated['is_active'];
        if (! $percentage) {
            $validated['max_discount_amount'] = null;
        }

        return $validated;
    }

    private function isDuplicateCode(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint failed: vouchers.code')
            || str_contains($message, 'vouchers_code_unique')
            || (str_contains($message, 'duplicate entry') && str_contains($message, 'code'));
    }
}
