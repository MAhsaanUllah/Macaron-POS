<?php

namespace App\Http\Controllers;

use App\Helpers\AuditLogger;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Item;
use App\Models\Order;
use App\Models\ProductionBatch;
use App\Models\Shift;
use App\Models\StockLoss;
use App\Models\SystemConfig;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FBRIntegrationService;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index()
    {
        $orders = Order::with('items.item')
            ->when(auth()->user()->isCashier(), fn ($query) => $query->whereHas('shift', fn ($shift) => $shift->where('user_id', auth()->id())))
            ->latest()
            ->paginate(15);
        $settings = SystemSetting::first() ?: new SystemSetting;
        $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);

        return view('pos.orders', compact('orders', 'settings', 'config'));
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_type' => 'nullable|string|in:COUNTER,PICKUP,DELIVERY',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'discount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,qr_digital',
            'amount_tendered' => 'nullable|numeric',
            'change_amount' => 'nullable|numeric',
            'transaction_reference' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_id' => 'nullable|exists:customers,id',
            'points_redeemed' => 'nullable|numeric|min:0',
            'checkout_token' => 'nullable|string|max:64',
        ]);

        $validated['checkout_token'] = ! empty($validated['checkout_token'])
            ? (string) $validated['checkout_token']
            : (string) Str::uuid();

        if ($existing = Order::with('items.item')->where('checkout_token', $validated['checkout_token'])->first()) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'message' => 'This sale was already saved.',
                'order' => $existing,
                'receipt_printed' => true,
            ]);
        }

        try {
            $result = DB::transaction(function () use ($validated) {
                $activeShift = Shift::where('user_id', auth()->id())
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->latest()
                    ->first();
                if (! $activeShift) {
                    throw new \RuntimeException('No active shift found. Please open a shift first.');
                }

                $subtotal = 0;
                $orderItemsData = [];
                $config = SystemConfig::first() ?? SystemConfig::create(['business_type' => 'sweets']);
                $orderType = $validated['order_type'] ?? 'COUNTER';
                if ($orderType === 'PICKUP' && ! $config->pickup_enabled) {
                    throw new \RuntimeException('Pickup orders are disabled in Settings.');
                }
                if ($orderType === 'DELIVERY' && ! $config->delivery_enabled) {
                    throw new \RuntimeException('Delivery orders are disabled in Settings.');
                }
                if ($validated['payment_method'] === 'card' && ! $config->card_enabled) {
                    throw new \RuntimeException('Card payments are disabled in Settings.');
                }
                if ($validated['payment_method'] === 'qr_digital' && (! $config->raast_enabled || ! $config->raast_qr_path)) {
                    throw new \RuntimeException('Raast QR payments are not configured in Settings.');
                }
                if ($validated['payment_method'] !== 'cash' && empty(trim($validated['transaction_reference'] ?? ''))) {
                    throw new \RuntimeException('Enter the bank transaction reference before completing payment.');
                }
                $strictTracking = $config->strict_ingredient_tracking ?? false;
                $stockWarnings = [];

                foreach ($validated['items'] as $itemData) {
                    $item = Item::lockForUpdate()->find($itemData['id']);

                    // Check stock
                    if ($item->stock_qty < $itemData['quantity']) {
                        throw new \Exception("Insufficient stock for item: {$item->name}");
                    }
                    if (! $item->tracks_batches && $item->expiry_date?->lte(today())) {
                        throw new \RuntimeException("{$item->name} is expired and cannot be sold. Record it as expired stock.");
                    }

                    $itemTotal = round((float) $item->price * (float) $itemData['quantity'], 2);
                    $subtotal = round($subtotal + $itemTotal, 2);

                    $orderItemsData[] = [
                        'item_id' => $item->id,
                        'quantity' => $itemData['quantity'],
                        'price' => $item->price,
                        'total' => $itemTotal,
                    ];

                    // Deduct stock
                    $item->decrement('stock_qty', $itemData['quantity']);

                    // --- Ingredient / Recipe Tracking ---
                    $recipeItems = $item->recipeItems()->get();

                    if ($recipeItems->isNotEmpty()) {
                        $orderedQty = $itemData['quantity'];

                        foreach ($recipeItems as $recipeItem) {
                            $totalIngredientQty = $recipeItem->qty_required * $orderedQty;
                            $ingredient = Ingredient::lockForUpdate()->find($recipeItem->ingredient_id);

                            $ingredient->decrement('current_stock', $totalIngredientQty);

                            if ($ingredient->current_stock < 0) {
                                $msg = "Ingredient '{$ingredient->name}' stock fell to {$ingredient->current_stock} {$ingredient->unit} "
                                     ."(ordered {$orderedQty}x {$item->name}, required {$recipeItem->qty_required} {$ingredient->unit} each)";

                                if ($strictTracking) {
                                    throw new \Exception("Insufficient ingredient stock: {$msg}");
                                }
                                $stockWarnings[] = $msg;
                                Log::warning("Recipe stock over-deducted: {$msg}");
                            }

                            // Check minimum alert threshold
                            if ($ingredient->current_stock <= $ingredient->minimum_alert_stock) {
                                Log::notice("Ingredient '{$ingredient->name}' at or below minimum alert stock. "
                                    ."Current: {$ingredient->current_stock} {$ingredient->unit}, "
                                    ."Threshold: {$ingredient->minimum_alert_stock} {$ingredient->unit}");
                            }
                        }
                    }
                }

                $taxRate = ((float) ($config->tax_rate ?? 0)) / 100;
                $tax = round($subtotal * $taxRate, 2);
                $discount = round((float) ($validated['discount'] ?? 0), 2);
                if ($discount > $subtotal + $tax) {
                    throw new \RuntimeException('Discount cannot exceed the bill total.');
                }
                $discountLimitPct = (float) ($config->discount_limit_pct ?? 15);
                if ($subtotal > 0 && ($discount / $subtotal) * 100 > $discountLimitPct && ! auth()->user()->isAdmin() && ! auth()->user()->isManager()) {
                    throw new \RuntimeException("Discounts above {$discountLimitPct}% require a manager or administrator.");
                }
                $grandTotal = round(($subtotal + $tax) - $discount, 2);
                $discountExceedsThreshold = false;

                // --- Audit: check discount threshold before order creation ---
                if ($discount > 0) {
                    $activeUser = auth()->user();
                    if ($activeUser && ($activeUser->role === User::ROLE_MANAGER || $activeUser->role === User::ROLE_CASHIER)) {
                        $discountPct = $subtotal > 0 ? ($discount / $subtotal) * 100 : 0;
                        $discountExceedsThreshold = $discountPct > $discountLimitPct;
                    }
                }

                // --- Loyalty Points ---
                $pointsEarned = 0;
                $pointsRedeemed = (float) ($validated['points_redeemed'] ?? 0);
                $customer = null;

                if ($pointsRedeemed > 0 && empty($validated['customer_id'])) {
                    throw new \RuntimeException('A customer is required to redeem loyalty points.');
                }

                if (! empty($validated['customer_id'])) {
                    $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                    if (! $customer) {
                        throw new \Exception('Customer not found.');
                    }

                    if ($pointsRedeemed > 0) {
                        if ($pointsRedeemed > $customer->total_points_balance) {
                            throw new \Exception(
                                "Insufficient points balance. Available: {$customer->total_points_balance}, Requested: {$pointsRedeemed}"
                            );
                        }
                        $grandTotal = round(max(0, $grandTotal - $pointsRedeemed), 2);
                    }

                    $pointsEarned = floor($grandTotal * 0.05);

                    $customer->decrement('total_points_balance', $pointsRedeemed);
                    $customer->increment('total_points_balance', $pointsEarned);
                }

                $amountTendered = $validated['payment_method'] === 'cash'
                    ? (float) ($validated['amount_tendered'] ?? 0)
                    : $grandTotal;
                if ($validated['payment_method'] === 'cash' && $amountTendered < $grandTotal) {
                    throw new \RuntimeException('Cash received cannot be less than the bill total.');
                }
                $changeAmount = $validated['payment_method'] === 'cash'
                    ? round($amountTendered - $grandTotal, 2)
                    : 0;

                $order = Order::create([
                    'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                    'checkout_token' => $validated['checkout_token'],
                    'order_type' => $orderType,
                    'status' => 'completed',
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'grand_total' => $grandTotal,
                    'payment_method' => $validated['payment_method'],
                    'amount_tendered' => $amountTendered,
                    'change_amount' => $changeAmount,
                    'transaction_reference' => $validated['transaction_reference'] ?? null,
                    'customer_name' => $validated['customer_name'] ?? null,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'points_earned' => $pointsEarned,
                    'points_redeemed' => $pointsRedeemed,
                    'shift_id' => $activeShift->id,
                    'fbr_status' => $config->fbr_enabled ? 'pending' : 'not_configured',
                ]);

                foreach ($orderItemsData as $data) {
                    $orderItem = $order->items()->create($data);
                    $unallocated = (float) $data['quantity'];
                    $batches = ProductionBatch::where('item_id', $data['item_id'])
                        ->where('remaining_qty', '>', 0)
                        ->orderBy('production_date')
                        ->orderBy('created_at')
                        ->lockForUpdate()
                        ->when(! $orderItem->item->tracks_batches, fn ($query) => $query->whereRaw('1 = 0'))
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
                        $orderItem->batchAllocations()->create([
                            'production_batch_id' => $batch->id,
                            'quantity' => $used,
                        ]);
                        $unallocated = round($unallocated - $used, 3);
                    }
                }

                // Update expected cash in shift if payment is cash
                if ($validated['payment_method'] === 'cash') {
                    $activeShift->increment('expected_cash', $grandTotal);
                }

                // --- Audit: Log discount threshold breach ---
                if ($discountExceedsThreshold) {
                    $activeUser = $activeShift->user;
                    $username = $activeUser?->name ?? 'System';
                    $currency = optional(SystemSetting::first())->currency_symbol ?? 'Rs.';
                    AuditLogger::log(
                        'discount_exceeded_threshold',
                        "{$username} authorized a {$currency}".number_format($discount, 2)
                            .' manual discount ('.round(($subtotal > 0 ? $discount / $subtotal : 0) * 100)."%) on Order #{$order->id}",
                        $order->id
                    );
                }

                $response = [
                    'success' => true,
                    'message' => 'Order created successfully',
                    'order' => $order->load('items.item'),
                ];

                if (! empty($stockWarnings)) {
                    $response['warnings'] = $stockWarnings;
                }

                return [$order, $response];
            });

            [$order, $response] = $result;
            try {
                (new FBRIntegrationService)->submitInvoice($order);
                $response['fbr_qr'] = FBRIntegrationService::qrDataUri($order->fbr_invoice_number);
                $receiptPrinted = (new PrintService)->printReceipt($order);
                $response['receipt_printed'] = $receiptPrinted;
            } catch (\Throwable $integrationException) {
                Log::error('Post-sale integration error: '.$integrationException->getMessage());
                $response['receipt_printed'] = false;
                $response['fbr_qr'] = null;
            }

            return response()->json($response, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'restock' => 'required|boolean',
            'refund_reference' => 'nullable|string|max:255',
        ]);

        try {
            $order = DB::transaction(function () use ($id, $validated) {
                $order = Order::with(['items.item.recipeItems.ingredient', 'items.batchAllocations.batch', 'customer', 'shift'])
                    ->lockForUpdate()
                    ->findOrFail($id);
                if ($order->status === 'cancelled') {
                    throw new \RuntimeException('Order is already cancelled.');
                }
                if ($order->fbr_status === 'submitted') {
                    throw new \RuntimeException('FBR-submitted invoices require a credit/debit note; they cannot be silently cancelled.');
                }

                if ($order->payment_method !== 'cash' && empty(trim($validated['refund_reference'] ?? ''))) {
                    throw new \RuntimeException('Refund on the bank terminal/provider first, then enter its refund reference.');
                }

                $oldStatus = $order->status;
                foreach ($order->items as $orderItem) {
                    $item = $orderItem->item;
                    if (! $item) {
                        continue;
                    }
                    if ($validated['restock']) {
                        $item->increment('stock_qty', $orderItem->quantity);
                        foreach ($item->recipeItems as $recipeItem) {
                            $recipeItem->ingredient?->increment('current_stock', $recipeItem->qty_required * $orderItem->quantity);
                        }
                    }
                    foreach ($orderItem->batchAllocations as $allocation) {
                        if ($allocation->batch && $validated['restock']) {
                            $allocation->batch->increment('remaining_qty', (float) $allocation->quantity);
                            $allocation->batch->update(['sold_out_at' => null]);
                        } elseif ($allocation->batch) {
                            $allocation->batch->wastages()->create([
                                'quantity' => $allocation->quantity,
                                'unit_retail_value' => $orderItem->price,
                                'reason' => 'customer_return',
                                'notes' => $validated['reason'],
                                'recorded_by' => auth()->id(),
                            ]);
                        }
                    }
                    if (! $validated['restock'] && ! $item->tracks_batches) {
                        StockLoss::create([
                            'item_id' => $item->id,
                            'quantity' => $orderItem->quantity,
                            'unit_retail_value' => $orderItem->price,
                            'reason' => 'customer_return',
                            'notes' => $validated['reason'],
                            'recorded_by' => auth()->id(),
                        ]);
                    }
                }
                if ($order->customer) {
                    $order->customer->decrement('total_points_balance', $order->points_earned);
                    $order->customer->increment('total_points_balance', $order->points_redeemed);
                }
                if ($order->payment_method === 'cash') {
                    $refundShift = Shift::where('user_id', auth()->id())->where('status', 'open')->lockForUpdate()->latest()->first();
                    if (! $refundShift) {
                        throw new \RuntimeException('Open your register before paying a cash refund.');
                    }
                    $refundShift->decrement('expected_cash', $order->grand_total);
                }
                $order->update(['status' => 'cancelled']);
                AuditLogger::logStatusChange($oldStatus, 'cancelled', $order->id);
                AuditLogger::log(
                    'sale_refunded',
                    'Full refund by '.auth()->user()->name
                        .'; stock '.($validated['restock'] ? 'returned to saleable stock' : 'kept out of stock')
                        .'; reason: '.$validated['reason']
                        .(($validated['refund_reference'] ?? null) ? '; refund ref: '.$validated['refund_reference'] : ''),
                    $order->id
                );

                return $order;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale refunded and stock handled as selected.',
        ]);
    }
}
