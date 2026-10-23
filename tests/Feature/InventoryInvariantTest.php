<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_alias_cart_checkout_cancel_sequence_conserves_inventory(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $category = Category::create(['name' => 'Invariant', 'slug' => 'invariant']);
        $products = [];
        for ($index = 0; $index < 3; $index++) {
            $products[] = Product::create(['category_id' => $category->id, 'name' => 'Invariant '.$index,
                'slug' => 'invariant-'.$index, 'price' => '10.00', 'quantity' => 20, 'is_active' => true]);
        }
        for ($step = 0; $step < 60; $step++) {
            $product = $products[($step * 7) % 3];
            $alias = str_repeat('0', $step % 4).$product->id;
            switch ($step % 5) {
                case 0:
                    $this->post('/gio-hang/them/'.$alias, ['quantity' => $step % 3 + 1])->assertRedirect();
                    break;
                case 1:
                    if (isset(session('cart', [])[$product->id])) {
                        $this->patch('/gio-hang/cap-nhat/'.$alias, ['quantity' => 2])->assertRedirect();
                    }
                    break;
                case 2:
                    $this->delete('/gio-hang/xoa/'.$alias)->assertRedirect();
                    break;
                case 3:
                    if (session('cart')) {
                        $fields = $this->checkoutFields(['customer_name' => 'Invariant buyer', 'phone' => '0900000000',
                            'address' => 'Test address', 'payment_method' => $step % 2 ? 'cod' : 'bank_transfer']);
                        $this->post('/checkout', $fields)->assertRedirect();
                    }
                    break;
                case 4:
                    // Keep some orders reserved and cancel others to exercise both balances.
                    if ($step % 2 === 0 && ($order = Order::where('status', 'pending')->first())) {
                        $this->patch(route('orders.cancel', $order->id))->assertRedirect('/gio-hang');
                    }
                    break;
            }
            foreach ($products as $shoe) {
                $reserved = (int) OrderItem::where('product_id', $shoe->id)
                    ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))->sum('quantity');
                $stock = $shoe->fresh()->quantity;
                $this->assertGreaterThanOrEqual(0, $stock);
                $this->assertSame(20, $stock + $reserved, 'Inventory must be conserved after every operation');
            }
            foreach (Order::with('items')->get() as $order) {
                $this->assertCount($order->items->count(), $order->items->pluck('product_id')->unique());
                $this->assertEquals((int) $order->items->sum('quantity') * 10, $order->total_amount);
            }
        }
        $this->assertGreaterThan(0, Order::where('status', 'pending')->count());
        $this->assertGreaterThan(0, Order::where('status', 'cancelled')->count());
    }
}
