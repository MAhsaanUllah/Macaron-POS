<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BatchStockReconcileTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        SystemConfig::create(['is_setup_completed' => true]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);
        Shift::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'opening_balance' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);

        return $user;
    }

    private function batchLedger(Item $item): float
    {
        return (float) ProductionBatch::where('item_id', $item->id)->sum('remaining_qty');
    }

    public function test_item_created_with_initial_stock_is_backed_by_an_adjustment_batch(): void
    {
        $category = Category::create(['name' => 'Mithai', 'slug' => 'mithai']);

        $this->actingAs($this->owner())->post('/menu/items', [
            'name' => 'Gulab Jamun',
            'price' => 1200,
            'category_id' => $category->id,
            'stock_qty' => 5,
            'uom' => 'KG',
            'sale_type' => 'weight',
            'tracks_batches' => 1,
        ])->assertSessionHasNoErrors();

        $item = Item::firstOrFail();
        $this->assertSame(5.0, (float) $item->stock_qty);
        $this->assertSame(5.0, $this->batchLedger($item));
    }

    public function test_csv_reimport_resyncs_batch_ledger_both_directions(): void
    {
        $category = Category::create(['name' => 'Mithai', 'slug' => 'mithai']);
        $user = $this->owner();

        $this->actingAs($user)->post('/menu/items', [
            'name' => 'Rasgulla',
            'price' => 1100,
            'category_id' => $category->id,
            'stock_qty' => 2,
            'uom' => 'KG',
            'sale_type' => 'weight',
            'tracks_batches' => 1,
        ])->assertSessionHasNoErrors();

        $item = Item::firstOrFail();

        $csv = "name,category,price,stock_qty,barcode,uom,stock_type,expiry_date\n"
            ."Rasgulla,Mithai,1100,5,{$item->barcode},kg,made_here,\n";

        $this->actingAs($user)->post('/menu/items/import', [
            'catalog' => UploadedFile::fake()->createWithContent('catalog.csv', $csv),
        ])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(5.0, (float) $item->stock_qty);
        $this->assertSame(5.0, $this->batchLedger($item), 'batch ledger must gain the +3 CSV delta');

        $csvDown = "name,category,price,stock_qty,barcode,uom,stock_type,expiry_date\n"
            ."Rasgulla,Mithai,1100,1,{$item->barcode},kg,made_here,\n";

        $this->actingAs($user)->post('/menu/items/import', [
            'catalog' => UploadedFile::fake()->createWithContent('catalog.csv', $csvDown),
        ])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(1.0, (float) $item->stock_qty);
        $this->assertSame(1.0, $this->batchLedger($item), 'batch ledger must drain the -4 CSV delta');
    }
}
