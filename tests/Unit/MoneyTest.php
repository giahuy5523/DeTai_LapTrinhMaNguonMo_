<?php

namespace Tests\Unit;

use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_values_and_maximum_are_exact_without_floats(): void
    {
        foreach (['0.00' => 0, '0.01' => 1, '1250.50' => 125050, '9999999999999.99' => 999999999999999] as $amount => $expected) {
            $this->assertSame($expected, Money::cents($amount));
            $this->assertSame($amount, Money::decimal($expected));
        }
        $this->assertSame(375150, Money::line('1250.50', 3));
        $this->assertSame('2.501', Money::format('2501.00'));
        $this->assertSame('1.250,50', Money::format('1250.50'));
        $this->assertSame('9.999.999.999.999,99', Money::format('9999999999999.99'));
    }

    public function test_multiplication_overflow_is_rejected_before_php_integer_overflow(): void
    {
        $this->expectException(ValidationException::class);
        Money::line('9999999999999.99', 2147483647);
    }

    public function test_cart_sum_overflow_is_rejected_even_when_each_line_fits(): void
    {
        $this->expectException(ValidationException::class);
        Money::total([['price' => '6000000000000.00', 'quantity' => 1], ['price' => '6000000000000.00', 'quantity' => 1]]);
    }
}
