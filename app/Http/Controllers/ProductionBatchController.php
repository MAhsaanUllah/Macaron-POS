<?php

namespace App\Http\Controllers;

use App\Models\BatchWastage;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\ProductionBatch;
use App\Models\StockLoss;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionBatchController extends Controller
{
    public function index()
    {
        $batches = ProductionBatch::with('item')->withSum('wastages', 'quantity')->latest('production_date')->latest()->paginate(25);
        $items = Item::where('tracks_batches', true)->orderBy('name')->get();
        $topSeller = OrderItem::with('item')
            ->whereHas('order', fn ($query) => $query->where('status', 'completed')->where('created_at', '>=', now()->subDays(7)))
            ->selectRaw('item_id, SUM(quantity) as sold_qty')
            ->groupBy('item_id')
            ->orderByDesc('sold_qty')
            ->first();
        $todayWasteValue = BatchWastage::whereDate('created_at', today())
            ->selectRaw('COALESCE(SUM(quantity * unit_retail_value), 0) as loss')
            ->value('loss')
            + StockLoss::whereDate('created_at', today())
                ->selectRaw('COALESCE(SUM(quantity * unit_retail_value), 0) as loss')
                ->value('loss');
        $settings = SystemSetting::first() ?: new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);

        return view('pos.batches', compact('batches', 'items', 'topSeller', 'todayWasteValue', 'settings', 'config'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'production_date' => 'required|date|before_or_equal:today',
            'produced_qty' => 'required|numeric|min:0.001',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $item = Item::lockForUpdate()->findOrFail($validated['item_id']);
            abort_unless($item->tracks_batches, 422, 'Enable daily batch tracking for this product first.');
            $quantity = round((float) $validated['produced_qty'], 3);

            ProductionBatch::create([
                ...$validated,
                'batch_code' => 'B-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
                'produced_qty' => $quantity,
                'remaining_qty' => $quantity,
                'created_by' => auth()->id(),
            ]);

            $item->increment('stock_qty', $quantity);
        });

        return back()->with('success', 'Production batch added to stock.');
    }

    public function storeWastage(Request $request, ProductionBatch $batch)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|in:stale,damaged,sample,staff_use,count_adjustment',
            'notes' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($batch, $validated) {
            $batch = ProductionBatch::with('item')->lockForUpdate()->findOrFail($batch->id);
            $quantity = round((float) $validated['quantity'], 3);
            abort_if($quantity > (float) $batch->remaining_qty, 422, 'Waste cannot exceed the batch quantity remaining.');

            $batch->wastages()->create([
                ...$validated,
                'quantity' => $quantity,
                'unit_retail_value' => $batch->item->price,
                'recorded_by' => auth()->id(),
            ]);
            $batch->decrement('remaining_qty', $quantity);
            $batch->item->decrement('stock_qty', $quantity);
        });

        return back()->with('success', 'Wastage recorded and stock adjusted.');
    }
}
