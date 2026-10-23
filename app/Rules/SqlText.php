<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SqlText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > 65535) {
            $fail('Mô tả vượt giới hạn lưu trữ 65.535 byte. Vui lòng rút ngắn nội dung.');
        }
    }
}
