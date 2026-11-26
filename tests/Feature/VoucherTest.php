<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_delete_vouchers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.vouchers.index'))->assertOk()->assertSee('Quản lý voucher');

        $payload = [
            'code' => ' spring-10 ',
            'discount_type' => 'percentage',
            'discount_value' => '10.00',
            'max_discount_amount' => '50000.00',
            'minimum_order_amount' => '300000.00',
            'usage_limit' => '20',
            'starts_at' => now()->subHour()->format('Y-m-d\\TH:i'),
            'ends_at' => now()->addWeek()->format('Y-m-d\\TH:i'),
            'is_active' => '1',
        ];

        $this->post(route('admin.vouchers.store'), $payload)->assertRedirect(route('admin.vouchers.index'));
        $voucher = Voucher::where('code', 'SPRING-10')->firstOrFail();
        $this->assertSame(0, $voucher->used_count);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'discount_type' => 'percentage']);

        Voucher::whereKey($voucher->id)->update(['used_count' => 5]);
        $this->from(route('admin.vouchers.edit', $voucher))
            ->put(route('admin.vouchers.update', $voucher), array_replace($payload, ['usage_limit' => '4']))
            ->assertRedirect(route('admin.vouchers.edit', $voucher))->assertSessionHasErrors('usage_limit');

        $this->put(route('admin.vouchers.update', $voucher), array_replace($payload, [
            'code' => 'SPRING-20', 'discount_type' => 'fixed', 'discount_value' => '20000.00',
            'max_discount_amount' => '',
        ]))->assertRedirect(route('admin.vouchers.index'));
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'code' => 'SPRING-20', 'discount_type' => 'fixed']);

        $this->delete(route('admin.vouchers.destroy', $voucher))->assertRedirect(route('admin.vouchers.index'));
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_customer_cannot_access_voucher_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.vouchers.index'))->assertRedirect('/');
    }

    public function test_admin_validation_rejects_duplicate_codes_and_invalid_percentage_caps(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = [
            'code' => 'SAVE10', 'discount_type' => 'fixed', 'discount_value' => '10000.00',
            'max_discount_amount' => '', 'minimum_order_amount' => '0.00', 'usage_limit' => '',
            'starts_at' => now()->format('Y-m-d\\TH:i'), 'ends_at' => now()->addDay()->format('Y-m-d\\TH:i'),
            'is_active' => '1',
        ];
        $this->post(route('admin.vouchers.store'), $payload)->assertRedirect(route('admin.vouchers.index'));
        $this->from(route('admin.vouchers.create'))
            ->post(route('admin.vouchers.store'), array_replace($payload, ['code' => 'save10']))
            ->assertRedirect(route('admin.vouchers.create'))->assertSessionHasErrors('code');
        $this->from(route('admin.vouchers.create'))
            ->post(route('admin.vouchers.store'), array_replace($payload, [
                'code' => 'NEEDCAP', 'discount_type' => 'percentage', 'discount_value' => '15.00',
            ]))->assertRedirect(route('admin.vouchers.create'))->assertSessionHasErrors('max_discount_amount');
        $this->from(route('admin.vouchers.create'))
            ->post(route('admin.vouchers.store'), array_replace($payload, [
                'code' => 'BADPCT', 'discount_type' => 'percentage', 'discount_value' => '100.01',
                'max_discount_amount' => '20000.00',
            ]))->assertRedirect(route('admin.vouchers.create'))->assertSessionHasErrors('discount_value');
    }
}
