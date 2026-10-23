<?php

namespace App\Support;

final class FormInput
{
    /** Preserve valid old values without passing arrays to HTML escaping. */
    public static function old(string $key, mixed $default = null): string|int|float|bool|null
    {
        $value = old($key, $default);

        // Do not replace an invalid submitted version with a fresh version token.
        return is_scalar($value) || $value === null ? $value : '';
    }
}
