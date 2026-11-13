<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BcryptPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return; // The string rule reports invalid types.
        }

        if (strlen($value) > 72) {
            $fail('Mật khẩu không được vượt quá 72 byte (ký tự có dấu có thể chiếm nhiều byte).');
        }
        if (str_contains($value, "\0")) {
            $fail('Mật khẩu không được chứa ký tự NUL.');
        }
    }
}
