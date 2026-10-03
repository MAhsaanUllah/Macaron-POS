<?php

namespace Database\Seeders;

use App\Models\BatchOrderItem;
use App\Models\BatchWastage;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionBatch;
use App\Models\Shift;
use App\Models\StockLoss;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Opt-in demo persona for portfolio screenshots and pilot walkthroughs:
 * Rehmat Sweets & Bakers, G.T. Road, Gujranwala — owner Ahmed Raza.
 *
 * Intended for a FRESH install right after the setup wizard; re-running it is
 * deterministic (it wipes and recreates only what it seeded itself). Prices are
 * made-up demo numbers, not current market rates.
 *
 * Run: php artisan db:seed --class=DemoCounterSeeder --force
 * Demo logins (password macaron123): cashier@rehmatsweets.pk, manager@rehmatsweets.pk
 */
class DemoCounterSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'macaron123';

    private const ORDER_PREFIX = 'ORD-DEMO';

    private const BATCH_MARKER = '-DEMO';

    /**
     * [category, name, price, uom, batch list (made_here) or stock qty (ready_made), image, expiry days (ready_made only)]
     * Batch list: [produced qty, days ago] newest batch listed last; history sales consume FIFO oldest-first.
     */
    private const CATALOG = [
        // Mithai — made daily, batch tracked by the kg
        ['Mithai', 'Gulab Jamun', 1150, 'KG', [[4, 2], [5, 1], [6, 0]], 'images/products/gulab-jamun.jpg'],
        ['Mithai', 'Rasgulla', 1000, 'KG', [[4, 1], [3, 0]], 'images/products/rasgulla.jpg'],
        ['Mithai', 'Kaju Barfi', 2400, 'KG', [[3, 2], [2, 0]], 'images/products/barfi.jpg'],
        ['Mithai', 'Milk Cake', 1600, 'KG', [[5, 1], [4, 0]], 'images/products/milk-cake.jpg'],
        ['Mithai', 'Patisa', 1250, 'KG', [[6, 3]], 'images/products/patisa.jpg'],
        ['Mithai', 'Besan Laddu', 1200, 'KG', [[5, 2], [4, 1]], 'images/products/besan-laddu.jpg'],
        ['Mithai', 'Cham Cham', 1300, 'KG', [[4, 0]], 'images/products/cham-cham.jpg'],
        ['Mithai', 'Motichoor Laddu', 1250, 'KG', [[5, 1]], 'images/products/motichoor-laddu.jpg'],
        ['Mithai', 'Chocolate Barfi', 2000, 'KG', [[2, 2]], 'images/products/chocolate-barfi.jpg'],
        ['Mithai', 'Mix Mithai', 1400, 'KG', [[5, 1], [6, 0]], 'images/products/mithai-box.jpg'],

        // Bakery — ready-made with expiry
        ['Bakery', 'Plain Cake Rusk', 700, 'KG', 12, 'images/products/cake-rusk.jpg', 20],
        ['Bakery', 'Almond Cake Rusk', 850, 'KG', 8, 'images/products/cake-rusk.jpg', 20],
        ['Bakery', 'Nan Khatai', 900, 'KG', 6, 'images/products/nan-khatai.jpg', 15],
        ['Bakery', 'Zeera Biscuits', 650, 'KG', 10, null, 30],
        ['Bakery', 'Butter Cookies', 1100, 'KG', 7, 'images/products/butter-cookies.jpg', 25],
        ['Bakery', 'Sandwich Bread', 180, 'Numbers, pieces, units', 20, 'images/products/sandwich-bread.jpg', 4],
        ['Bakery', 'Milk Bread', 160, 'Numbers, pieces, units', 15, null, 3],

        // Cakes
        ['Cakes', 'Chocolate Cake 2lb', 2800, 'Numbers, pieces, units', 5, 'images/products/chocolate-cake.jpg', 5],
        ['Cakes', 'Pound Cake 1lb', 1200, 'Numbers, pieces, units', 6, null, 7],
        ['Cakes', 'Fresh Cream Pastry', 250, 'Numbers, pieces, units', 12, 'images/products/fresh-cream-pastry.jpg', 2],
        ['Cakes', 'Fruit Cake 1.5lb', 2200, 'Numbers, pieces, units', 4, null, 10],

        // Snacks
        ['Snacks', 'Samosa', 60, 'Numbers, pieces, units', 60, 'images/products/samosa.jpg', 1],
        ['Snacks', 'Chicken Patty', 150, 'Numbers, pieces, units', 30, 'images/products/chicken-patty.jpg', 2],
        ['Snacks', 'Pakora (Mix)', 400, 'KG', 5, null, 1],
        ['Snacks', 'Chicken Spring Roll', 130, 'Numbers, pieces, units', 24, 'images/products/spring-roll.jpg', 2],

        // Drinks
        ['Drinks', 'Mineral Water 1.5L', 100, 'Numbers, pieces, units', 36, null, 180],
        ['Drinks', 'Soft Drink 1.5L', 200, 'Numbers, pieces, units', 24, 'images/products/soft-drink.jpg', 120],
        ['Drinks', 'Mango Juice 1L', 320, 'Numbers, pieces, units', 18, null, 90],
        ['Drinks', 'Lassi 1L', 250, 'Numbers, pieces, units', 10, 'images/products/lassi.jpg', 2],

        // Gift boxes
        ['Gift Boxes', 'Premium Mithai Box 1kg', 2000, 'Numbers, pieces, units', 10, 'images/products/mithai-box.jpg'],
        ['Gift Boxes', 'Mithai Assorted Box 500g', 1100, 'Numbers, pieces, units', 8, 'images/products/assorted-box-500g.jpg'],
        ['Gift Boxes', 'Kaju Mithai Gift Tin 2kg', 5200, 'Numbers, pieces, units', 5, 'images/products/gift-tin-2kg.jpg'],
    ];

    private const HS_CODES = [
        'Mithai' => '1905.90',
        'Bakery' => '1905.90',
        'Cakes' => '1905.90',
        'Snacks' => '1905.90',
        'Gift Boxes' => '1905.90',
        'Mineral Water 1.5L' => '2201.10',
        'Soft Drink 1.5L' => '2202.10',
        'Mango Juice 1L' => '2009.89',
        'Lassi 1L' => '0403.90',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $this->wipePreviousDemoRuns();
            $this->seedProfile();
            $owner = $this->seedStaff();
            $items = $this->seedCatalog($now);
            $this->seedHistory($now, $owner, $items);
        });

        $this->command?->info('Demo persona ready: Rehmat Sweets & Bakers, Gujranwala (32 products, 7 sales, 2 shifts).');
    }

    private function wipePreviousDemoRuns(): void
    {
        $demoOrders = Order::withTrashed()->where('order_number', 'like', self::ORDER_PREFIX.'%')->get();

        if ($demoOrders->isNotEmpty()) {
            $orderIds = $demoOrders->pluck('id');
            $shiftIds = $demoOrders->pluck('shift_id')->filter()->unique();
            $orderItemIds = OrderItem::withTrashed()->whereIn('order_id', $orderIds)->pluck('id');

            BatchOrderItem::whereIn('order_item_id', $orderItemIds)->delete();
            OrderItem::withTrashed()->whereIn('order_id', $orderIds)->get()->each->forceDelete();
            $demoOrders->each->forceDelete();

            Shift::withTrashed()->whereIn('id', $shiftIds)->get()->each->forceDelete();
        }

        $demoBatches = ProductionBatch::where('batch_code', 'like', '%'.self::BATCH_MARKER.'%')->get();
        if ($demoBatches->isNotEmpty()) {
            BatchWastage::whereIn('production_batch_id', $demoBatches->pluck('id'))->delete();
            ProductionBatch::whereIn('id', $demoBatches->pluck('id'))->delete();
        }

        $demoItemIds = Item::withTrashed()->whereIn('name', array_column(self::CATALOG, 1))->pluck('id');
        if ($demoItemIds->isNotEmpty()) {
            StockLoss::whereIn('item_id', $demoItemIds)->delete();
        }
    }

    private function seedProfile(): void
    {
        $settings = SystemSetting::first() ?? new SystemSetting;
        $settings->fill([
            'shop_name' => 'Rehmat Sweets & Bakers',
            'phone_number' => '055-3842710',
            'address' => 'G.T. Road, Gujranwala, Punjab',
            'currency_symbol' => 'Rs.',
        ])->save();

        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);
        $config->fill([
            'business_type' => 'sweets',
            'card_enabled' => true,
            'card_terminal_name' => 'HBL Card Machine',
            'raast_enabled' => true,
            'raast_qr_path' => 'payments/demo-raast-qr.svg',
        ])->save();

        $svg = (new Writer(new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd)))
            ->writeString('MACARON DEMO - sample QR for Rehmat Sweets & Bakers; not a payment link.');
        Storage::disk('public')->put('payments/demo-raast-qr.svg', $svg);
    }

    private function seedStaff(): User
    {
        $owner = User::where('role', User::ROLE_ADMIN)->orderBy('created_at')->first();

        if (! $owner) {
            $owner = User::updateOrCreate(
                ['email' => 'owner@rehmatsweets.pk'],
                ['name' => 'Ahmed Raza', 'password' => Hash::make(self::DEMO_PASSWORD), 'role' => User::ROLE_ADMIN],
            );
        }

        User::updateOrCreate(
            ['email' => 'cashier@rehmatsweets.pk'],
            ['name' => 'Ali Hassan', 'password' => Hash::make(self::DEMO_PASSWORD), 'role' => User::ROLE_CASHIER],
        );

        User::updateOrCreate(
            ['email' => 'manager@rehmatsweets.pk'],
            ['name' => 'Usman Tariq', 'password' => Hash::make(self::DEMO_PASSWORD), 'role' => User::ROLE_MANAGER],
        );

        return $owner;
    }

    /** @return Collection<string, Item> */
    private function seedCatalog(Carbon $now): Collection
    {
        // The setup wizard seeds a "Savoury" category; the demo copy calls it "Snacks"
        $savoury = Category::where('slug', 'savoury')->first();
        if ($savoury) {
            $savoury->update(['name' => 'Snacks', 'slug' => 'snacks']);
        }

        $categories = collect(['Mithai', 'Bakery', 'Cakes', 'Snacks', 'Drinks', 'Gift Boxes'])
            ->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(
                ['slug' => \Str::slug($name)],
                ['name' => $name],
            )]);

        $items = collect();
        $batchSeq = 0;

        foreach (self::CATALOG as $seq => $row) {
            [$category, $name, $price, $uom, $stockOrBatches, $image] = $row;
            $expiryDays = $row[6] ?? null;
            $isBatchTracked = is_array($stockOrBatches);

            $item = Item::withTrashed()->updateOrCreate(
                ['name' => $name],
                [
                    'price' => $price,
                    'category_id' => $categories[$category]->id,
                    'stock_qty' => 0,
                    'barcode' => sprintf('896401%07d', $seq + 1),
                    'hs_code' => self::HS_CODES[$name] ?? self::HS_CODES[$category] ?? null,
                    'image_path' => $image,
                    'uom' => $uom,
                    'sale_type' => 'Goods at standard rate (default)',
                    'tracks_batches' => $isBatchTracked,
                    'expiry_date' => $expiryDays ? $now->copy()->addDays($expiryDays)->toDateString() : null,
                    'deleted_at' => null,
                ],
            );

            if ($isBatchTracked) {
                $produced = 0;
                foreach ($stockOrBatches as [$qty, $daysAgo]) {
                    $producedOn = $now->copy()->subDays($daysAgo)->setTime(6, 0);
                    $batch = ProductionBatch::create([
                        'item_id' => $item->id,
                        'batch_code' => 'B-'.$producedOn->format('ymd').self::BATCH_MARKER.++$batchSeq,
                        'production_date' => $producedOn->toDateString(),
                        'produced_qty' => $qty,
                        'remaining_qty' => $qty,
                        'notes' => 'Demo seed batch',
                        'created_by' => null,
                    ]);
                    $batch->forceFill(['created_at' => $producedOn, 'updated_at' => $producedOn])->save();
                    $produced += $qty;
                }
                $item->update(['stock_qty' => $produced]);
            } else {
                $item->update(['stock_qty' => $stockOrBatches]);
            }

            $items[$name] = $item;
        }

        return $items;
    }

    private function seedHistory(Carbon $now, User $owner, Collection $items): void
    {
        $yStart = $now->copy()->subDay()->setTime(17, 0);
        $tStart = $now->copy()->subHours(2);

        // Yesterday — closed register, historically consistent float
        $yesterday = Shift::create([
            'user_id' => $owner->id,
            'start_time' => $yStart,
            'end_time' => $now->copy()->subDay()->setTime(21, 30),
            'opening_balance' => 5000,
            'expected_cash' => 5000,
            'status' => 'open',
        ]);
        $yesterday->forceFill(['created_at' => $yStart, 'updated_at' => $yStart])->save();

        $sales = [
            [self::ORDER_PREFIX.'0001', $yStart->copy()->addMinutes(15), 'cash', null, 1400, 'Bilal Ahmad', null, [['Gulab Jamun', 1], ['Samosa', 4]]],
            [self::ORDER_PREFIX.'0002', $yStart->copy()->addMinutes(100), 'card', 'RRN-882913', null, null, null, [['Mix Mithai', 0.5], ['Plain Cake Rusk', 0.5]]],
            [self::ORDER_PREFIX.'0003', $yStart->copy()->addMinutes(140), 'cash', null, 1100, null, null, [['Samosa', 6], ['Chicken Patty', 2], ['Soft Drink 1.5L', 2]]],
            [self::ORDER_PREFIX.'0004', $yStart->copy()->addMinutes(185), 'qr_digital', 'RAAST-4417F2', null, null, null, [['Rasgulla', 1], ['Kaju Barfi', 0.25]]],
        ];

        foreach ($sales as [$number, $at, $method, $reference, $tendered, $customer, $phone, $lines]) {
            $this->recordSale($yesterday, $owner, $number, $at, $method, $reference, $tendered, $customer, $phone, $lines, $items);
        }

        // Yesterday's waste: patisa went stale overnight
        $patisa = $items['Patisa'];
        $patisaBatch = ProductionBatch::where('item_id', $patisa->id)->orderBy('production_date')->firstOrFail();
        $wasteAt = $now->copy()->subMinutes(30);
        $wastage = $patisaBatch->wastages()->create([
            'quantity' => 1,
            'unit_retail_value' => $patisa->price,
            'reason' => 'stale',
            'notes' => 'Evening tray left over',
            'recorded_by' => $owner->id,
        ]);
        $wastage->forceFill(['created_at' => $wasteAt, 'updated_at' => $wasteAt])->save();
        $patisaBatch->decrement('remaining_qty', 1);
        $patisa->decrement('stock_qty', 1);

        // Close yesterday's register at a perfect count
        $yesterday->refresh();
        $yesterday->update([
            'closing_balance' => $yesterday->expected_cash,
            'cash_collected_declared' => $yesterday->expected_cash,
            'status' => 'closed',
        ]);

        // Today — open register, ready for the walkthrough
        $today = Shift::create([
            'user_id' => $owner->id,
            'start_time' => $tStart,
            'opening_balance' => 3000,
            'expected_cash' => 3000,
            'status' => 'open',
        ]);
        $today->forceFill(['created_at' => $tStart, 'updated_at' => $tStart])->save();

        $todaySales = [
            [self::ORDER_PREFIX.'0005', $tStart->copy()->addMinutes(10), 'cash', null, 3000, null, null, [['Chocolate Cake 2lb', 1]]],
            [self::ORDER_PREFIX.'0006', $tStart->copy()->addMinutes(30), 'cash', null, 1300, null, null, [['Milk Cake', 0.5], ['Nan Khatai', 0.5]]],
            [self::ORDER_PREFIX.'0007', $tStart->copy()->addMinutes(55), 'card', 'RRN-771902', null, 'Sana Malik', '0321-5566778', [['Mithai Assorted Box 500g', 1], ['Mineral Water 1.5L', 1]]],
        ];

        foreach ($todaySales as [$number, $at, $method, $reference, $tendered, $customer, $phone, $lines]) {
            $this->recordSale($today, $owner, $number, $at, $method, $reference, $tendered, $customer, $phone, $lines, $items);
        }

        // Today's loss: bread expired on the shelf
        $bread = $items['Sandwich Bread'];
        $loss = StockLoss::create([
            'item_id' => $bread->id,
            'quantity' => 2,
            'unit_retail_value' => $bread->price,
            'reason' => 'expired',
            'notes' => 'Pulled from the shelf this morning',
            'recorded_by' => $owner->id,
        ]);
        $loss->forceFill(['created_at' => $now->copy()->subMinutes(20), 'updated_at' => $now->copy()->subMinutes(20)])->save();
        $bread->decrement('stock_qty', 2);
    }

    /**
     * Mirrors OrderController::store stock/FIFO/expected-cash side effects.
     *
     * @param  array<int, array{0: string, 1: float}>  $lines
     */
    private function recordSale(Shift $shift, User $user, string $number, Carbon $at, string $method, ?string $reference, ?float $tendered, ?string $customer, ?string $phone, array $lines, Collection $items): Order
    {
        $subtotal = 0;
        foreach ($lines as [$name, $qty]) {
            $subtotal += round($items[$name]->price * $qty, 2);
        }

        $order = Order::create([
            'order_number' => $number,
            'checkout_token' => 'demo-seed-'.substr($number, -4),
            'order_type' => 'COUNTER',
            'status' => 'completed',
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => 0,
            'grand_total' => $subtotal,
            'payment_method' => $method,
            'amount_tendered' => $method === 'cash' ? $tendered : 0,
            'change_amount' => $method === 'cash' ? round($tendered - $subtotal, 2) : 0,
            'transaction_reference' => $reference,
            'customer_name' => $customer,
            'customer_phone' => $phone,
            'points_earned' => 0,
            'points_redeemed' => 0,
            'shift_id' => $shift->id,
            'fbr_status' => 'not_configured',
        ]);
        $order->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        foreach ($lines as [$name, $qty]) {
            $item = $items[$name];
            $orderItem = $order->items()->create([
                'item_id' => $item->id,
                'quantity' => $qty,
                'price' => $item->price,
                'total' => round($item->price * $qty, 2),
            ]);

            if ($item->tracks_batches) {
                $this->consumeBatches($item, (float) $qty, $orderItem);
            }
            $item->decrement('stock_qty', $qty);
        }

        if ($method === 'cash') {
            $shift->increment('expected_cash', $subtotal);
        }

        return $order;
    }

    /** FIFO allocation identical to OrderController::store. */
    private function consumeBatches(Item $item, float $quantity, OrderItem $orderItem): void
    {
        $unallocated = $quantity;
        $batches = ProductionBatch::where('item_id', $item->id)
            ->where('remaining_qty', '>', 0)
            ->orderBy('production_date')
            ->orderBy('created_at')
            ->get();

        foreach ($batches as $batch) {
            if ($unallocated <= 0) {
                break;
            }
            $used = round(min($unallocated, (float) $batch->remaining_qty), 3);
            $remaining = round((float) $batch->remaining_qty - $used, 3);
            $batch->update([
                'remaining_qty' => $remaining,
                'sold_out_at' => $remaining <= 0 ? now() : null,
            ]);
            BatchOrderItem::create([
                'production_batch_id' => $batch->id,
                'order_item_id' => $orderItem->id,
                'quantity' => $used,
            ]);
            $unallocated = round($unallocated - $used, 3);
        }
    }
}
