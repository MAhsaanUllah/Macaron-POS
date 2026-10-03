<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionBatch;
use App\Models\Shift;
use App\Models\StockLoss;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    /**
     * Display the POS dashboard.
     */
    public function index()
    {
        $activeShift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();
        if (! $activeShift) {
            return redirect()->route('shift.open');
        }

        $categories = Category::all();
        $items = Item::with('category')->get()->each(function (Item $item) {
            $expired = ! $item->tracks_batches && $item->expiry_date?->lte(today());
            $item->setAttribute('is_saleable', $item->stock_qty > 0 && ! $expired);
            $item->setAttribute('stock_status', $expired ? 'Expired' : ($item->stock_qty > 0 ? 'In Stock' : 'Out of Stock'));
        });
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);
        $settings = SystemSetting::first() ?: new SystemSetting;

        return view('pos.dashboard', compact('categories', 'items', 'config', 'settings'));
    }

    public function menu()
    {
        $activeShift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();
        if (! $activeShift) {
            return redirect()->route('shift.open');
        }

        $categories = Category::withCount('items')->get();
        $items = Item::with('category')->paginate(20);
        $settings = SystemSetting::first() ?: new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);

        return view('pos.menu', compact('categories', 'items', 'settings', 'config'));
    }

    public function reports(Request $request)
    {
        $settings = SystemSetting::first() ?: new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);

        // Date filter: default to today
        $dateFrom = $request->query('date_from', today()->toDateString());
        $dateTo = $request->query('date_to', today()->toDateString());

        // --- Gross Sales (completed orders) ---
        $grossSales = Order::where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->sum('grand_total');

        // --- Material Food Cost (recipe x ingredients) ---
        $materialCost = OrderItem::whereHas('order', function ($q) use ($dateFrom, $dateTo) {
            $q->where('status', 'completed')
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo);
        })
            ->join('recipe_items', 'order_items.item_id', '=', 'recipe_items.item_id')
            ->join('ingredients', 'recipe_items.ingredient_id', '=', 'ingredients.id')
            ->selectRaw('COALESCE(SUM(order_items.quantity * recipe_items.qty_required * ingredients.unit_cost), 0) as total_cost')
            ->value('total_cost') ?? 0;

        // --- Operational Expenses ---
        $opEx = Expense::whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->sum('amount');

        // --- Net True Profit ---
        $netProfit = $grossSales - $materialCost - $opEx;

        // --- Daily P&L breakdown for chart (within the date window) ---
        $dailyRevenue = Order::where('status', 'completed')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $dailyMaterialCost = OrderItem::whereHas('order', function ($q) use ($dateFrom, $dateTo) {
            $q->where('status', 'completed')
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo);
        })
            ->join('recipe_items', 'order_items.item_id', '=', 'recipe_items.item_id')
            ->join('ingredients', 'recipe_items.ingredient_id', '=', 'ingredients.id')
            ->selectRaw('DATE(order_items.created_at) as date, SUM(order_items.quantity * recipe_items.qty_required * ingredients.unit_cost) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $dailyExpenses = Expense::whereDate('expense_date', '>=', $dateFrom)
            ->whereDate('expense_date', '<=', $dateTo)
            ->selectRaw('expense_date as date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->pluck('total', 'date');

        // Build a date-ordered array of labels (all days in range)
        $dateLabels = [];
        $revenueSeries = [];
        $costSeries = [];
        $profitSeries = [];

        $period = new \DatePeriod(
            new \DateTime($dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($dateTo))->modify('+1 day')
        );

        foreach ($period as $dt) {
            $key = $dt->format('Y-m-d');
            $label = $dt->format('M d');
            $rev = (float) ($dailyRevenue[$key] ?? 0);
            $cost = (float) ($dailyMaterialCost[$key] ?? 0) + (float) ($dailyExpenses[$key] ?? 0);
            $dateLabels[] = $label;
            $revenueSeries[] = $rev;
            $costSeries[] = $cost;
            $profitSeries[] = $rev - $cost;
        }

        // Basic stats
        $todaySales = Order::where('status', 'completed')->whereDate('created_at', today())->sum('grand_total');
        $todayOrders = Order::where('status', 'completed')->whereDate('created_at', today())->count();
        $totalSales = Order::where('status', 'completed')->sum('grand_total');

        // Hourly sales for today (Chart data)
        $hourlySales = Order::where('status', 'completed')->whereDate('created_at', today())
            ->selectRaw('strftime("%H", created_at) as hour, SUM(grand_total) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total', 'hour')
            ->toArray();

        $chartData = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $chartData[] = $hourlySales[$hour] ?? 0;
        }

        // Top Selling Items
        $topItems = OrderItem::with('item')
            ->whereHas('order', fn ($query) => $query->where('status', 'completed'))
            ->selectRaw('item_id, SUM(quantity) as total_qty, SUM(total) as total_revenue')
            ->groupBy('item_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        // Recent Activity
        $recentOrders = Order::with('items.item')->where('status', 'completed')->latest()->take(5)->get();

        // Audit Logs
        $auditLogs = AuditLog::with('user', 'order')
            ->latest()
            ->take(20)
            ->get();

        return view('pos.reports', compact(
            'settings',
            'config',
            'todaySales',
            'todayOrders',
            'totalSales',
            'chartData',
            'topItems',
            'recentOrders',
            'auditLogs',
            'dateFrom',
            'dateTo',
            'grossSales',
            'materialCost',
            'opEx',
            'netProfit',
            'dateLabels',
            'revenueSeries',
            'costSeries',
            'profitSeries'
        ));
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return back()->with('success', 'Category added successfully.');
    }

    public function storeItem(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'stock_qty' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'hs_code' => 'nullable|string|max:30',
            'uom' => 'required|string|max:100',
            'sale_type' => 'required|string|max:150',
            'tracks_batches' => 'nullable|boolean',
            'expiry_date' => 'nullable|date',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            // Relative so the URL survives the desktop shell's per-launch port changes.
            $imagePath = 'storage/'.$request->file('image')->store('items', 'public');
        }

        DB::transaction(function () use ($request, $imagePath) {
            $item = Item::create([
                'name' => $request->name,
                'price' => $request->price,
                'category_id' => $request->category_id,
                'stock_qty' => 0,
                'image_path' => $imagePath,
                'barcode' => Str::random(10),
                'hs_code' => $request->hs_code,
                'uom' => $request->uom,
                'sale_type' => $request->sale_type,
                'tracks_batches' => $request->boolean('tracks_batches'),
                'expiry_date' => $request->expiry_date,
            ]);

            $this->reconcileBatchStock($item, (float) $request->stock_qty, 'Initial stock (item created)');
        });

        return back()->with('success', 'Menu Item added successfully.');
    }

    public function storeStockLoss(Request $request, Item $item)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|in:expired,damaged,shortage,customer_return',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($item, $validated) {
            $item = Item::lockForUpdate()->findOrFail($item->id);
            abort_if($item->tracks_batches, 422, 'Use Daily Batches to record waste for shop-made products.');
            $quantity = round((float) $validated['quantity'], 3);
            abort_if($quantity > (float) $item->stock_qty, 422, 'Loss cannot exceed current stock.');

            $item->decrement('stock_qty', $quantity);
            StockLoss::create([
                ...$validated,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'unit_retail_value' => $item->price,
                'recorded_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Ready-made stock loss recorded.');
    }

    public function importItems(Request $request)
    {
        $request->validate(['catalog' => 'required|file|mimes:csv,txt|max:5120']);
        $handle = fopen($request->file('catalog')->getRealPath(), 'r');
        $headers = array_map(fn ($value) => Str::snake(trim((string) $value)), fgetcsv($handle) ?: []);
        $required = ['name', 'category', 'price', 'stock_qty'];
        abort_unless(empty(array_diff($required, $headers)), 422, 'CSV needs name, category, price and stock_qty columns.');

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($handle, $headers, &$created, &$updated) {
            while (($values = fgetcsv($handle)) !== false) {
                if (count($values) !== count($headers) || ! array_filter($values, fn ($value) => trim((string) $value) !== '')) {
                    continue;
                }
                $row = array_combine($headers, array_map('trim', $values));
                abort_if($row['name'] === '' || $row['category'] === '', 422, 'Every CSV row needs a product name and category.');
                abort_if(! is_numeric($row['price']) || (float) $row['price'] < 0, 422, "Invalid price for {$row['name']}.");
                abort_if(! is_numeric($row['stock_qty']) || (float) $row['stock_qty'] < 0, 422, "Invalid stock for {$row['name']}.");

                $category = Category::firstOrCreate(
                    ['name' => $row['category']],
                    ['slug' => Str::slug($row['category'])]
                );
                $barcode = $row['barcode'] ?? null;
                $item = $barcode ? Item::where('barcode', $barcode)->first() : null;
                $stockQty = round((float) $row['stock_qty'], 3);
                $data = [
                    'name' => $row['name'],
                    'category_id' => $category->id,
                    'price' => round((float) $row['price'], 2),
                    'barcode' => $barcode ?: Str::random(10),
                    'uom' => strtolower($row['uom'] ?? 'pcs') === 'kg' ? 'KG' : 'Numbers, pieces, units',
                    'sale_type' => 'Goods at standard rate (default)',
                    'tracks_batches' => strtolower($row['stock_type'] ?? 'ready_made') === 'made_here',
                    'expiry_date' => ($row['expiry_date'] ?? '') !== '' ? $row['expiry_date'] : null,
                ];
                validator(
                    ['expiry_date' => $data['expiry_date']],
                    ['expiry_date' => 'nullable|date_format:Y-m-d']
                )->validate();

                if ($item) {
                    $item->update($data);
                    $this->reconcileBatchStock($item, $stockQty, 'CSV import stock sync');
                    $updated++;
                } else {
                    $item = Item::create($data + ['stock_qty' => 0]);
                    $this->reconcileBatchStock($item, $stockQty, 'Initial stock (CSV import)');
                    $created++;
                }
            }
        });
        fclose($handle);

        return back()->with('success', "Catalog imported: {$created} added, {$updated} updated by barcode.");
    }

    /**
     * Keep production_batches in step with a bulk stock_qty write (item create / CSV import).
     * Without this, the batch ledger drifts: FIFO sales allocate fewer units than were sold.
     */
    private function reconcileBatchStock(Item $item, float $newStock, string $note): void
    {
        $newStock = round($newStock, 3);

        if ($item->tracks_batches) {
            $ledger = round((float) ProductionBatch::where('item_id', $item->id)->sum('remaining_qty'), 3);
            $delta = round($newStock - $ledger, 3);

            if ($delta > 0) {
                ProductionBatch::create([
                    'item_id' => $item->id,
                    'batch_code' => 'B-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
                    'production_date' => now()->toDateString(),
                    'produced_qty' => $delta,
                    'remaining_qty' => $delta,
                    'notes' => $note,
                    'created_by' => auth()->id(),
                ]);
            } elseif ($delta < 0) {
                $toDrain = abs($delta);
                $batches = ProductionBatch::where('item_id', $item->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('production_date')
                    ->orderBy('created_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($batches as $batch) {
                    if ($toDrain <= 0) {
                        break;
                    }
                    $used = round(min($toDrain, (float) $batch->remaining_qty), 3);
                    $remaining = round((float) $batch->remaining_qty - $used, 3);
                    $batch->update([
                        'remaining_qty' => $remaining,
                        'sold_out_at' => $remaining <= 0 ? now() : null,
                    ]);
                    $toDrain = round($toDrain - $used, 3);
                }
            }
        }

        $item->update(['stock_qty' => $newStock]);
    }

    public function catalogTemplate()
    {
        $csv = "name,category,price,stock_qty,barcode,uom,stock_type,expiry_date\n"
            ."Sandwich Bread,Packaged Breakfast,0,0,,pcs,ready_made,2026-12-31\n"
            ."Milk 1L,Dairy & Drinks,0,0,,pcs,ready_made,2026-12-31\n"
            ."Butter 200g,Packaged Breakfast,0,0,,pcs,ready_made,2026-12-31\n"
            ."Mix Mithai,Mithai,0,0,,kg,made_here,\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="macaron-catalog-template.csv"',
        ]);
    }
}
