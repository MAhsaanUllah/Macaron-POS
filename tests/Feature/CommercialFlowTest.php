<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\ProductionBatch;
use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\PrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommercialFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_reports(): void
    {
        SystemConfig::create(['is_setup_completed' => true]);

        $this->get('/reports')->assertRedirect('/');
    }

    public function test_server_calculates_sale_and_cancellation_reverses_stock_and_cash(): void
    {
        $config = SystemConfig::create([
            'is_setup_completed' => true,
            'tax_rate' => 10,
            'fbr_enabled' => false,
        ]);
        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_MANAGER,
        ]);
        $shift = Shift::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'opening_balance' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);
        $category = Category::create(['name' => 'Bakery', 'slug' => 'bakery']);
        $item = Item::create([
            'name' => 'Cake Slice',
            'price' => 100,
            'stock_qty' => 10,
            'category_id' => $category->id,
        ]);

        $sale = $this->actingAs($user)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 200,
            'discount' => 0,
        ]);

        $sale->assertCreated()
            ->assertJsonPath('order.subtotal', '100.00')
            ->assertJsonPath('order.tax', '10.00')
            ->assertJsonPath('order.grand_total', '110.00')
            ->assertJsonPath('order.change_amount', '90.00');
        $this->assertSame('9.000', $item->fresh()->stock_qty);
        $this->assertEquals(110, $shift->fresh()->expected_cash);

        $order = Order::firstOrFail();
        $this->actingAs($user)->postJson("/api/orders/{$order->id}/cancel", [
            'reason' => 'Wrong bill',
            'restock' => true,
        ])->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('10.000', $item->fresh()->stock_qty);
        $this->assertEquals(0, $shift->fresh()->expected_cash);
    }

    public function test_cashier_cannot_apply_more_than_fifteen_percent_discount(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $user = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CASHIER,
        ]);
        Shift::create(['user_id' => $user->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Bakery', 'slug' => 'bakery']);
        $item = Item::create(['name' => 'Bread', 'price' => 100, 'stock_qty' => 5, 'category_id' => $category->id]);

        $this->actingAs($user)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 100,
            'discount' => 20,
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('5.000', $item->fresh()->stock_qty);
    }

    public function test_owner_can_raise_the_cashier_discount_limit(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier2@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CASHIER,
        ]);
        Shift::create(['user_id' => $cashier->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Mithai', 'slug' => 'mithai']);
        $item = Item::create(['name' => 'Barfi', 'price' => 100, 'stock_qty' => 5, 'category_id' => $category->id]);

        $this->actingAs($owner)->post('/settings/update', [
            'shop_name' => 'Sialkot Sweets',
            'phone_number' => '0300-0000000',
            'discount_limit_pct' => 25,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(25, (float) SystemConfig::first()->discount_limit_pct);

        $this->actingAs($cashier)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 80,
            'discount' => 20,
        ])->assertCreated()->assertJsonPath('order.grand_total', '80.00');
    }

    public function test_weighted_mithai_sale_accepts_fractional_kilograms(): void
    {
        $config = SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0, 'business_type' => 'sweets']);
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'mithai@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CASHIER,
        ]);
        Shift::create(['user_id' => $cashier->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Mithai', 'slug' => 'mithai']);
        $item = Item::create([
            'name' => 'Mix Mithai',
            'price' => 1200,
            'stock_qty' => 10,
            'uom' => 'KG',
            'category_id' => $category->id,
        ]);

        $this->actingAs($cashier)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 0.5]],
            'order_type' => 'COUNTER',
            'payment_method' => 'cash',
            'amount_tendered' => 1000,
        ])->assertCreated()
            ->assertJsonPath('order.subtotal', '600.00')
            ->assertJsonPath('order.grand_total', '600.00');

        $this->assertSame('9.500', $item->fresh()->stock_qty);
    }

    public function test_delivery_sale_stores_customer_name(): void
    {
        $config = SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0, 'business_type' => 'sweets']);
        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'delivery@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_CASHIER,
        ]);
        Shift::create(['user_id' => $cashier->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Savoury', 'slug' => 'savoury']);
        $item = Item::create(['name' => 'Samosa', 'price' => 70, 'stock_qty' => 10, 'category_id' => $category->id]);

        $payload = [
            'items' => [['id' => $item->id, 'quantity' => 2]],
            'order_type' => 'DELIVERY',
            'customer_name' => 'Ali Raza',
            'customer_phone' => '03001234567',
            'payment_method' => 'cash',
            'amount_tendered' => 200,
        ];

        $this->actingAs($cashier)->postJson('/orders/store', $payload)->assertStatus(422);

        $config->update(['delivery_enabled' => true]);
        $this->actingAs($cashier)->postJson('/orders/store', $payload)->assertCreated();

        $this->assertDatabaseHas('orders', [
            'order_type' => 'DELIVERY',
            'customer_name' => 'Ali Raza',
            'customer_phone' => '03001234567',
        ]);
    }

    public function test_non_cash_methods_require_configuration_and_a_bank_reference(): void
    {
        $config = SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $cashier = User::create([
            'name' => 'Cashier', 'email' => 'payments@example.com',
            'password' => Hash::make('secret123'), 'role' => User::ROLE_CASHIER,
        ]);
        Shift::create(['user_id' => $cashier->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Packaging', 'slug' => 'packaging']);
        $item = Item::create(['name' => 'Gift Box', 'price' => 75, 'stock_qty' => 10, 'category_id' => $category->id]);
        $payload = ['items' => [['id' => $item->id, 'quantity' => 1]], 'payment_method' => 'card'];

        $this->actingAs($cashier)->postJson('/orders/store', $payload)->assertStatus(422);
        $config->update(['card_enabled' => true]);
        $this->actingAs($cashier)->postJson('/orders/store', $payload)->assertStatus(422);
        $this->actingAs($cashier)->postJson('/orders/store', $payload + ['transaction_reference' => 'RRN-123'])->assertCreated();

        $this->assertDatabaseHas('orders', ['payment_method' => 'card', 'transaction_reference' => 'RRN-123', 'grand_total' => 75]);
    }

    public function test_sale_consumes_oldest_batch_and_cancellation_restores_it(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $manager = User::create(['name' => 'Manager', 'email' => 'batch@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Mithai', 'slug' => 'mithai-batches']);
        $item = Item::create(['name' => 'Mix Mithai', 'price' => 1000, 'stock_qty' => 0, 'uom' => 'KG', 'tracks_batches' => true, 'category_id' => $category->id]);

        $this->actingAs($manager)->post('/production', ['item_id' => $item->id, 'production_date' => today()->subDay()->toDateString(), 'produced_qty' => 1]);
        $oldest = ProductionBatch::firstOrFail();
        $this->actingAs($manager)->post('/production', ['item_id' => $item->id, 'production_date' => today()->toDateString(), 'produced_qty' => 1]);

        $sale = $this->actingAs($manager)->postJson('/orders/store', ['items' => [['id' => $item->id, 'quantity' => 1]], 'payment_method' => 'cash', 'amount_tendered' => 1000]);
        $sale->assertCreated();
        $this->assertSame('completed', $sale->json('order.status'));
        $this->assertEquals(0, $oldest->fresh()->remaining_qty);
        $this->assertNotNull($oldest->fresh()->sold_out_at);

        $this->actingAs($manager)->postJson('/api/orders/'.$sale->json('order.id').'/cancel', [
            'reason' => 'Wrong bill',
            'restock' => true,
        ])->assertOk();
        $this->assertEquals(1, $oldest->fresh()->remaining_qty);
        $this->assertNull($oldest->fresh()->sold_out_at);
    }

    public function test_batch_wastage_reduces_batch_and_finished_stock(): void
    {
        SystemConfig::create(['is_setup_completed' => true]);
        $manager = User::create(['name' => 'Manager', 'email' => 'waste@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        $category = Category::create(['name' => 'Mithai', 'slug' => 'waste-mithai']);
        $item = Item::create(['name' => 'Kalakand', 'price' => 1200, 'stock_qty' => 0, 'uom' => 'KG', 'tracks_batches' => true, 'category_id' => $category->id]);
        $this->actingAs($manager)->post('/production', ['item_id' => $item->id, 'production_date' => today()->toDateString(), 'produced_qty' => 2]);
        $batch = ProductionBatch::firstOrFail();

        $this->actingAs($manager)->post("/production/{$batch->id}/wastage", ['quantity' => 0.25, 'reason' => 'stale'])->assertRedirect();

        $this->assertEquals(1.75, $batch->fresh()->remaining_qty);
        $this->assertEquals(1.75, $item->fresh()->stock_qty);
        $this->assertDatabaseHas('batch_wastages', ['production_batch_id' => $batch->id, 'quantity' => 0.25, 'unit_retail_value' => 1200]);
    }

    public function test_csv_bulk_import_adds_ready_made_and_batch_products(): void
    {
        SystemConfig::create(['is_setup_completed' => true]);
        $manager = User::create(['name' => 'Manager', 'email' => 'import@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'status' => 'open']);
        $csv = "name,category,price,stock_qty,barcode,uom,stock_type\nBread,Packaged Breakfast,180,12,BR-1,pcs,ready_made\nMix Mithai,Mithai,1200,5,MM-1,kg,made_here\n";

        $this->actingAs($manager)->post('/menu/items/import', [
            'catalog' => UploadedFile::fake()->createWithContent('catalog.csv', $csv),
        ])->assertRedirect();

        $this->assertDatabaseHas('items', ['barcode' => 'BR-1', 'tracks_batches' => false, 'stock_qty' => 12]);
        $this->assertDatabaseHas('items', ['barcode' => 'MM-1', 'tracks_batches' => true, 'stock_qty' => 5]);
        $this->assertDatabaseHas('categories', ['name' => 'Packaged Breakfast']);
    }

    public function test_cashier_sees_only_own_sales_and_manager_cannot_open_owner_settings(): void
    {
        SystemConfig::create(['is_setup_completed' => true]);
        $cashier = User::create(['name' => 'Cashier A', 'email' => 'a@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_CASHIER]);
        $other = User::create(['name' => 'Cashier B', 'email' => 'b@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_CASHIER]);
        $manager = User::create(['name' => 'Manager', 'email' => 'role-manager@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        $cashierShift = Shift::create(['user_id' => $cashier->id, 'start_time' => now(), 'status' => 'open']);
        $otherShift = Shift::create(['user_id' => $other->id, 'start_time' => now(), 'status' => 'open']);
        Order::create(['order_number' => 'OWN-SALE', 'status' => 'completed', 'subtotal' => 100, 'tax' => 0, 'grand_total' => 100, 'shift_id' => $cashierShift->id]);
        Order::create(['order_number' => 'OTHER-SALE', 'status' => 'completed', 'subtotal' => 200, 'tax' => 0, 'grand_total' => 200, 'shift_id' => $otherShift->id]);

        $this->actingAs($cashier)->get('/orders')->assertOk()->assertSee('OWN-SALE')->assertDontSee('OTHER-SALE');
        $this->actingAs($manager)->get('/settings')->assertForbidden();
    }

    public function test_owner_sees_honest_counter_readiness_and_can_test_cash_drawer(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'cashier_printer_name' => 'RECEIPT']);
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN]);

        $this->actingAs($owner)->get('/settings?tab=hardware')
            ->assertOk()
            ->assertSee('Counter Readiness')
            ->assertSee('Configured — run a test below')
            ->assertSee('Cloud backup')
            ->assertSee('Not connected');

        $printer = $this->mock(PrintService::class);
        $printer->shouldReceive('openCashDrawer')->once()->andReturnTrue();

        $this->actingAs($owner)->post('/settings/test-cash-drawer')
            ->assertRedirect()
            ->assertSessionHas('success', 'Cash drawer pulse sent.');
    }

    public function test_mvp_rejects_a_fourth_production_role(): void
    {
        SystemConfig::create(['is_setup_completed' => true]);
        $owner = User::create(['name' => 'Owner', 'email' => 'mvp-owner@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN]);
        $legacyProduction = User::create(['name' => 'Legacy Production', 'email' => 'legacy-production@example.com', 'password' => Hash::make('secret123'), 'role' => 'production']);

        $this->actingAs($owner)->post('/settings/users', [
            'name' => 'New Production',
            'email' => 'new-production@example.com',
            'password' => 'secret123',
            'role' => 'production',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'new-production@example.com']);
        $this->actingAs($legacyProduction)->get('/production')->assertForbidden();
    }

    public function test_expired_ready_made_stock_is_blocked_and_can_be_recorded_as_loss(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $manager = User::create(['name' => 'Manager', 'email' => 'expiry@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Packaged', 'slug' => 'expiry-packaged']);
        $item = Item::create([
            'name' => 'Milk Pack',
            'price' => 250,
            'stock_qty' => 5,
            'uom' => 'Numbers, pieces, units',
            'tracks_batches' => false,
            'expiry_date' => today(),
            'category_id' => $category->id,
        ]);

        $this->actingAs($manager)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 250,
        ])->assertStatus(422);

        $this->actingAs($manager)->post("/menu/items/{$item->id}/loss", [
            'quantity' => 2,
            'reason' => 'expired',
        ])->assertRedirect();

        $this->assertSame('3.000', $item->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_losses', ['item_id' => $item->id, 'quantity' => 2, 'reason' => 'expired']);
    }

    public function test_bad_fresh_return_stays_out_of_stock_and_becomes_batch_waste(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $manager = User::create(['name' => 'Manager', 'email' => 'return@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'status' => 'open', 'expected_cash' => 0]);
        $category = Category::create(['name' => 'Mithai', 'slug' => 'return-mithai']);
        $item = Item::create(['name' => 'Barfi', 'price' => 1000, 'stock_qty' => 0, 'uom' => 'KG', 'tracks_batches' => true, 'category_id' => $category->id]);
        $this->actingAs($manager)->post('/production', ['item_id' => $item->id, 'production_date' => today()->toDateString(), 'produced_qty' => 1]);

        $sale = $this->actingAs($manager)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 1000,
        ])->assertCreated();

        $this->actingAs($manager)->postJson('/api/orders/'.$sale->json('order.id').'/cancel', [
            'reason' => 'Customer found it spoiled',
            'restock' => false,
        ])->assertOk();

        $this->assertSame('0.000', $item->fresh()->stock_qty);
        $this->assertDatabaseHas('batch_wastages', ['quantity' => 1, 'reason' => 'customer_return']);
        $this->assertEquals(0, Shift::firstOrFail()->fresh()->expected_cash);
    }

    public function test_card_refund_requires_provider_reference_before_sale_is_reversed(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0, 'card_enabled' => true]);
        $manager = User::create(['name' => 'Manager', 'email' => 'card-refund@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'status' => 'open']);
        $category = Category::create(['name' => 'Bakery', 'slug' => 'card-refund-bakery']);
        $item = Item::create(['name' => 'Bread', 'price' => 180, 'stock_qty' => 3, 'category_id' => $category->id]);

        $sale = $this->actingAs($manager)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 1]],
            'payment_method' => 'card',
            'transaction_reference' => 'SALE-RRN-1',
        ])->assertCreated();

        $refundUrl = '/api/orders/'.$sale->json('order.id').'/cancel';
        $this->actingAs($manager)->postJson($refundUrl, [
            'reason' => 'Customer returned sealed item',
            'restock' => true,
        ])->assertStatus(422);
        $this->assertSame('completed', Order::findOrFail($sale->json('order.id'))->status);
        $this->assertSame('2.000', $item->fresh()->stock_qty);

        $this->actingAs($manager)->postJson($refundUrl, [
            'reason' => 'Customer returned sealed item',
            'restock' => true,
            'refund_reference' => 'REFUND-RRN-1',
        ])->assertOk();

        $this->assertSame('cancelled', Order::findOrFail($sale->json('order.id'))->status);
        $this->assertSame('3.000', $item->fresh()->stock_qty);
    }

    public function test_checkout_token_prevents_duplicate_orders(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $cashier = User::create(['name' => 'Cashier', 'email' => 'idempotent@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_CASHIER]);
        Shift::create(['user_id' => $cashier->id, 'start_time' => now(), 'opening_balance' => 100, 'expected_cash' => 100, 'status' => 'open']);
        $category = Category::create(['name' => 'Sweets', 'slug' => 'idempotent-sweets']);
        $item = Item::create(['name' => 'Gulab Jamun', 'price' => 250, 'stock_qty' => 10, 'category_id' => $category->id]);

        $token = (string) Str::uuid();
        $payload = [
            'items' => [['id' => $item->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'amount_tendered' => 500,
            'checkout_token' => $token,
        ];

        // First submission
        $first = $this->actingAs($cashier)->postJson('/orders/store', $payload)->assertCreated();
        $orderId = $first->json('order.id');
        $this->assertSame('8.000', $item->fresh()->stock_qty);

        // Rapid second submission with identical token
        $second = $this->actingAs($cashier)->postJson('/orders/store', $payload)->assertOk();
        $this->assertTrue($second->json('duplicate'));
        $this->assertSame($orderId, $second->json('order.id'));
        $this->assertSame('8.000', $item->fresh()->stock_qty); // Stock not decremented twice
        $this->assertEquals(1, Order::where('checkout_token', $token)->count());
    }

    public function test_cash_refund_decrements_active_shift_expected_cash(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $manager = User::create(['name' => 'Manager', 'email' => 'cash-refund-shift@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        $shift = Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'opening_balance' => 1000, 'expected_cash' => 1000, 'status' => 'open']);
        $category = Category::create(['name' => 'Bakery', 'slug' => 'cash-refund-bakery']);
        $item = Item::create(['name' => 'Patties', 'price' => 120, 'stock_qty' => 5, 'category_id' => $category->id]);

        // Sale for 240 cash
        $sale = $this->actingAs($manager)->postJson('/orders/store', [
            'items' => [['id' => $item->id, 'quantity' => 2]],
            'payment_method' => 'cash',
            'amount_tendered' => 240,
        ])->assertCreated();

        $this->assertEquals(1240, $shift->fresh()->expected_cash);

        // Refund the 240 cash sale
        $this->actingAs($manager)->postJson('/api/orders/'.$sale->json('order.id').'/cancel', [
            'reason' => 'Customer changed mind',
            'restock' => true,
        ])->assertOk();

        // Active shift expected cash must decrement back to opening balance
        $this->assertEquals(1000, $shift->fresh()->expected_cash);

        // Close register with declared cash matching expected cash
        $closeRes = $this->actingAs($manager)->postJson('/shift/close', [
            'declared_cash' => 1000,
        ])->assertOk();

        $this->assertSame('closed', $shift->fresh()->status);
        $this->assertEquals(1000, $shift->fresh()->cash_collected_declared);
    }

    public function test_reports_exclude_cancelled_orders(): void
    {
        SystemConfig::create(['is_setup_completed' => true, 'tax_rate' => 0]);
        $manager = User::create(['name' => 'Manager', 'email' => 'reports-scope@example.com', 'password' => Hash::make('secret123'), 'role' => User::ROLE_MANAGER]);
        Shift::create(['user_id' => $manager->id, 'start_time' => now(), 'opening_balance' => 0, 'expected_cash' => 0, 'status' => 'open']);
        $category = Category::create(['name' => 'Sweets', 'slug' => 'report-sweets']);
        $itemA = Item::create(['name' => 'Laddoo', 'price' => 300, 'stock_qty' => 10, 'category_id' => $category->id]);
        $itemB = Item::create(['name' => 'Barfi', 'price' => 400, 'stock_qty' => 10, 'category_id' => $category->id]);

        // Completed sale of 300
        $this->actingAs($manager)->postJson('/orders/store', [
            'items' => [['id' => $itemA->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 300,
        ])->assertCreated();

        // Sale of 400 that will be cancelled
        $cancelledSale = $this->actingAs($manager)->postJson('/orders/store', [
            'items' => [['id' => $itemB->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_tendered' => 400,
        ])->assertCreated();

        $this->actingAs($manager)->postJson('/api/orders/'.$cancelledSale->json('order.id').'/cancel', [
            'reason' => 'Customer cancelled',
            'restock' => true,
        ])->assertOk();

        $response = $this->actingAs($manager)->get('/reports');
        $response->assertOk();
        $response->assertViewHas('todaySales', 300);
        $response->assertViewHas('todayOrders', 1);
        $response->assertViewHas('totalSales', 300);
    }
}
