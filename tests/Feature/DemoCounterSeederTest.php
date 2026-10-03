<?php

namespace Tests\Feature;

use App\Models\BatchWastage;
use App\Models\Item;
use App\Models\Order;
use App\Models\ProductionBatch;
use App\Models\Shift;
use App\Models\StockLoss;
use Database\Seeders\DemoCounterSeeder;
use Database\Seeders\SweetShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCounterSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        // Real fresh-install order: the setup wizard seeds SweetShopSeeder first
        $this->seed(SweetShopSeeder::class);
        $this->seed(DemoCounterSeeder::class);
    }

    public function test_demo_persona_loads_catalog_staff_and_history(): void
    {
        $this->seedDemo();

        $this->assertDatabaseCount('items', 32);
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseHas('categories', ['slug' => 'snacks', 'name' => 'Snacks']);
        $this->assertDatabaseMissing('categories', ['slug' => 'savoury']);
        $this->assertDatabaseHas('users', ['email' => 'cashier@rehmatsweets.pk', 'name' => 'Ali Hassan', 'role' => 'cashier']);
        $this->assertDatabaseHas('users', ['email' => 'manager@rehmatsweets.pk', 'name' => 'Usman Tariq', 'role' => 'manager']);
        $this->assertDatabaseHas('system_settings', ['shop_name' => 'Rehmat Sweets & Bakers', 'phone_number' => '055-3842710']);
        $this->assertDatabaseCount('orders', 7);
        $this->assertSame(1, Shift::where('status', 'closed')->count());
        $this->assertSame(1, Shift::where('status', 'open')->count());
        $this->assertSame(1, BatchWastage::count());
        $this->assertSame(1, StockLoss::count());
    }

    public function test_batch_ledger_matches_stock_for_every_batch_item(): void
    {
        $this->seedDemo();

        $batchItems = Item::where('tracks_batches', true)->get();
        $this->assertCount(10, $batchItems);

        foreach ($batchItems as $item) {
            $ledger = (float) ProductionBatch::where('item_id', $item->id)->sum('remaining_qty');
            $this->assertEqualsWithDelta((float) $item->stock_qty, $ledger, 0.001, "{$item->name}: stock must equal batch remaining");
        }
    }

    public function test_history_is_consistent_with_shift_cash_and_fifo(): void
    {
        $this->seedDemo();

        foreach (Shift::all() as $shift) {
            $cashSales = (float) Order::where('shift_id', $shift->id)
                ->where('payment_method', 'cash')
                ->where('status', 'completed')
                ->sum('grand_total');
            $expected = (float) $shift->opening_balance + $cashSales;
            $this->assertEqualsWithDelta($expected, (float) $shift->expected_cash, 0.001,
                "shift ({$shift->status}): expected cash must equal opening float + cash sales");
        }

        $closed = Shift::where('status', 'closed')->firstOrFail();
        $this->assertEqualsWithDelta((float) $closed->expected_cash, (float) $closed->cash_collected_declared, 0.001,
            'the closed shift must hold a declared count equal to the expected cash');

        foreach (Order::with('items')->get() as $order) {
            foreach ($order->items as $line) {
                $item = Item::find($line->item_id);
                if (! $item->tracks_batches) {
                    continue;
                }
                $allocated = (float) $line->batchAllocations()->sum('quantity');
                $this->assertEqualsWithDelta((float) $line->quantity, $allocated, 0.001,
                    "{$order->order_number}: every batch-tracked unit sold must be allocated to a production batch");
            }
        }
    }

    public function test_reseeding_is_deterministic_and_not_duplicated(): void
    {
        $this->seedDemo();
        $stockSnapshot = Item::orderBy('name')->pluck('stock_qty')->all();

        $this->seed(DemoCounterSeeder::class);

        $this->assertDatabaseCount('items', 32);
        $this->assertDatabaseCount('orders', 7);
        $this->assertSame(1, Shift::where('status', 'open')->count());
        $this->assertSame(1, Shift::where('status', 'closed')->count());
        $this->assertSame($stockSnapshot, Item::orderBy('name')->pluck('stock_qty')->all());
    }
}
