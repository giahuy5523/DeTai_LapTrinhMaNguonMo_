<?php

namespace Tests\Unit;

use App\Support\CartSnapshot;
use PHPUnit\Framework\TestCase;

class CartNormalizationTest extends TestCase
{
    public function test_normalization_is_idempotent_and_conserves_quantities_across_alias_permutations(): void
    {
        for ($round = 1; $round <= 30; $round++) {
            $raw = [];
            $totals = [];
            for ($id = 1; $id <= 5; $id++) {
                for ($alias = 0; $alias < 4; $alias++) {
                    $quantity = ($round + $id + $alias) % 7 + 1;
                    $raw[str_repeat('0', $alias).$id] = ['quantity' => $quantity];
                    $totals[$id] = ($totals[$id] ?? 0) + $quantity;
                }
            }
            if ($round % 2 === 0) {
                $raw = array_reverse($raw, true);
            }
            $normalized = CartSnapshot::normalize($raw);
            $this->assertSame($normalized, CartSnapshot::normalize($normalized));
            $this->assertCount(5, $normalized);
            foreach ($totals as $id => $quantity) {
                $this->assertSame($quantity, $normalized[$id]['quantity']);
            }
        }
    }

    public function test_id_parser_rejects_coercion_and_overflow_without_rounding(): void
    {
        foreach (['1e0', '1.0', '+1', '-1', '1abc', ' 1', '0', '000', '9223372036854775808'] as $id) {
            $this->assertNull(CartSnapshot::productId($id));
        }
        $this->assertSame(1, CartSnapshot::productId('0001'));
        $this->assertSame(PHP_INT_MAX, CartSnapshot::productId((string) PHP_INT_MAX));
    }
}
