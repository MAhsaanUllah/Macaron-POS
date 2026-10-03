<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceiptFbrTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::create([
            'name' => 'Owner',
            'email' => 'fbr-owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function openShift(User $user): Shift
    {
        return Shift::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'opening_balance' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);
    }

    private function sellableItem(): Item
    {
        $category = Category::create(['name' => 'Sweets', 'slug' => 'sweets']);

        return Item::create([
            'name' => 'Gulab Jamun',
            'price' => 100,
            'stock_qty' => 10,
            'category_id' => $category->id,
            'hs_code' => '1704.9000',
        ]);
    }

    private function checkout(User $user, Item $item)
    {
        return $this->actingAs($user)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 200,
            'discount' => 0,
        ]);
    }

    public function test_checkout_carries_fbr_invoice_number_and_qr_when_submission_succeeds(): void
    {
        SystemConfig::create([
            'is_setup_completed' => true,
            'tax_rate' => 0,
            'fbr_enabled' => true,
            'fbr_environment' => 'sandbox',
            'fbr_scenario_id' => 'SN001',
            'fbr_bearer_token' => 'sandbox-token',
        ]);
        SystemSetting::create([
            'shop_name' => 'Mithai Mahal',
            'address' => 'Main Bazaar',
            'tax_number' => '1234567',
            'currency_symbol' => 'Rs.',
        ]);
        Http::fake(['gw.fbr.gov.pk/*' => Http::response(['invoiceNumber' => 'FBR-90001'], 200)]);

        $user = $this->owner();
        $this->openShift($user);
        $item = $this->sellableItem();

        $sale = $this->checkout($user, $item);

        $sale->assertCreated()
            ->assertJsonPath('order.fbr_status', 'submitted')
            ->assertJsonPath('order.fbr_invoice_number', 'FBR-90001');

        $qr = $sale->json('fbr_qr');
        $this->assertIsString($qr);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr);
        $this->assertStringContainsString(
            '<svg',
            base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')))
        );

        // The hidden receipt template carries the FBR block for the software print path.
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('receipt-fbr-block', false)
            ->assertSee('FBR INVOICE #', false)
            ->assertSee('Tax Asaan', false);
    }

    public function test_checkout_never_fabricates_fbr_fields_when_disabled(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0, 'fbr_enabled' => false]);
        $user = $this->owner();
        $this->openShift($user);
        $item = $this->sellableItem();

        $sale = $this->checkout($user, $item);

        $sale->assertCreated()
            ->assertJsonPath('order.fbr_status', 'not_configured')
            ->assertJsonPath('order.fbr_invoice_number', null);
        $this->assertNull($sale->json('fbr_qr'));
    }

    public function test_orders_reprint_payload_includes_submitted_fbr_invoice(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $user = $this->owner();
        $shift = $this->openShift($user);

        Order::create([
            'order_number' => 'INV-TEST-0001',
            'order_type' => 'COUNTER',
            'status' => 'completed',
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => 100,
            'payment_method' => 'cash',
            'shift_id' => $shift->id,
            'fbr_status' => 'submitted',
            'fbr_invoice_number' => 'FBR-777',
        ]);

        $html = $this->actingAs($user)->get('/orders')->assertOk()->getContent();

        $this->assertStringContainsString('fbrInvoiceNumber', $html);
        $this->assertStringContainsString('fbrQr', $html);
        $this->assertStringContainsString('FBR-777', $html);
        $this->assertStringContainsString('data:image\/svg+xml;base64,', $html);
    }
}
