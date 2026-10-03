@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-y-auto p-6">
    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-3xl font-black text-on-surface">Today's Production</h1>
            <p class="text-sm text-on-surface-variant mt-1">Enter what was made today. Sold and remaining quantities update automatically after each sale.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
            <div class="bg-active-surface rounded-2xl p-4"><strong class="text-primary">1. Made</strong><p class="text-on-surface-variant mt-1">Enter the total quantity made for each fresh product.</p></div>
            <div class="bg-active-surface rounded-2xl p-4"><strong class="text-primary">2. Sold</strong><p class="text-on-surface-variant mt-1">Counter sales automatically consume the oldest available batch.</p></div>
            <div class="bg-active-surface rounded-2xl p-4"><strong class="text-primary">3. Wasted</strong><p class="text-on-surface-variant mt-1">Record stale, damaged, sampled, or missing stock as waste.</p></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-surface border border-outline rounded-2xl p-5">
                <p class="text-xs font-bold text-on-surface-variant">Pichlay 7 din ka sab se zyada bikne wala</p>
                <p class="text-xl font-black mt-2">{{ $topSeller?->item?->name ?? 'No sales yet' }}</p>
                @if($topSeller)<p class="text-sm text-primary mt-1">{{ number_format((float) $topSeller->sold_qty, 3) }} sold</p>@endif
            </div>
            <div class="bg-surface border border-outline rounded-2xl p-5">
                <p class="text-xs font-bold text-on-surface-variant">Today's waste at retail value</p>
                <p class="text-xl font-black text-error mt-2">{{ $settings->currency_symbol ?? 'Rs.' }} {{ number_format((float) $todayWasteValue, 2) }}</p>
                <p class="text-xs text-on-surface-variant mt-1">Retail value, not ingredient cost</p>
            </div>
            <div class="bg-surface border border-outline rounded-2xl p-5 text-sm text-on-surface-variant">
                <span class="font-bold text-on-surface">Seedhi baat:</span> jo mithai/bakery item shop mein banta hai woh yahan aaye ga. Branded/ready-made packet Products mein normal stock rahe ga.
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-primary/10 text-primary font-bold">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="p-4 rounded-xl bg-error/10 text-error font-bold">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('batches.store') }}" class="bg-surface border border-outline rounded-2xl p-5 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-on-surface-variant mb-2">Product made</label>
                <select name="item_id" required class="w-full h-12 rounded-xl bg-background border-outline text-on-surface">
                    <option value="">Select a fresh product</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" @selected(old('item_id') === $item->id)>{{ $item->name }} ({{ $item->uom === 'KG' ? 'kg' : 'pcs' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-on-surface-variant mb-2">Production date</label>
                <input type="date" name="production_date" required max="{{ today()->toDateString() }}" value="{{ old('production_date', today()->toDateString()) }}" class="w-full h-12 rounded-xl bg-background border-outline text-on-surface">
            </div>
            <div>
                <label class="block text-xs font-bold text-on-surface-variant mb-2">Quantity made</label>
                <input type="number" name="produced_qty" required min="0.001" step="0.001" inputmode="decimal" placeholder="0.000" class="w-full h-12 rounded-xl bg-background border-outline text-on-surface">
            </div>
            <button class="h-12 rounded-xl bg-primary text-on-primary font-black">Add to stock</button>
            <input type="text" name="notes" maxlength="255" placeholder="Optional: subah ka batch, special order, etc." class="md:col-span-5 h-12 rounded-xl bg-background border-outline text-on-surface">
        </form>

        <div class="bg-surface border border-outline rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-active-surface text-xs text-on-surface-variant">
                        <tr><th class="p-4">Product</th><th class="p-4">Made on</th><th class="p-4 text-right">Made</th><th class="p-4 text-right">Sold</th><th class="p-4 text-right">Wasted</th><th class="p-4 text-right">Remaining</th><th class="p-4">Status / Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-outline">
                        @forelse($batches as $batch)
                            @php
                                $wasted = (float) ($batch->wastages_sum_quantity ?? 0);
                                $sold = max(0, (float) $batch->produced_qty - (float) $batch->remaining_qty - $wasted);
                                $age = $batch->production_date->diffInDays(today()) + 1;
                                $sellDays = $batch->sold_out_at ? $batch->production_date->diffInDays($batch->sold_out_at->startOfDay()) + 1 : null;
                                $unit = $batch->item?->uom === 'KG' ? 'kg' : 'pcs';
                            @endphp
                            <tr class="text-sm">
                                <td class="p-4"><div class="font-bold">{{ $batch->item?->name ?? 'Deleted product' }}</div><div class="font-mono text-xs text-on-surface-variant">{{ $batch->batch_code }}</div>@if($batch->notes)<div class="text-xs text-on-surface-variant">{{ $batch->notes }}</div>@endif</td>
                                <td class="p-4">{{ $batch->production_date->format('d M Y') }}</td>
                                <td class="p-4 text-right">{{ number_format((float) $batch->produced_qty, 3) }} {{ $unit }}</td>
                                <td class="p-4 text-right font-bold">{{ number_format($sold, 3) }} {{ $unit }}</td>
                                <td class="p-4 text-right font-bold {{ $wasted > 0 ? 'text-error' : '' }}">{{ number_format($wasted, 3) }} {{ $unit }}</td>
                                <td class="p-4 text-right font-bold {{ $batch->remaining_qty > 0 ? 'text-error' : 'text-primary' }}">{{ number_format((float) $batch->remaining_qty, 3) }} {{ $unit }}</td>
                                <td class="p-4">
                                    @if($sellDays)
                                        <span class="px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">Closed in {{ $sellDays }} {{ Str::plural('day', $sellDays) }}</span>
                                    @elseif((float) $batch->remaining_qty <= 0)
                                        <span class="px-3 py-1 rounded-full bg-error/10 text-error text-xs font-bold">Closed with waste</span>
                                    @elseif($age > 2)
                                        <span class="px-3 py-1 rounded-full bg-error/10 text-error text-xs font-bold">Unsold • day {{ $age }}</span>
                                    @else
                                        <span class="px-3 py-1 rounded-full bg-active-surface text-on-surface-variant text-xs font-bold">Selling • day {{ $age }}</span>
                                    @endif
                                    @if((float) $batch->remaining_qty > 0)
                                        <details class="mt-3">
                                            <summary class="cursor-pointer text-xs font-bold text-error">Record waste or damage</summary>
                                            <form method="POST" action="{{ route('batches.wastage.store', $batch) }}" class="mt-2 space-y-2 min-w-52">
                                                @csrf
                                                <input type="number" name="quantity" required min="0.001" max="{{ $batch->remaining_qty }}" step="0.001" placeholder="Quantity" class="w-full h-10 rounded-lg bg-background border-outline text-on-surface text-xs">
                                                <select name="reason" required class="w-full h-10 rounded-lg bg-background border-outline text-on-surface text-xs">
                                                    <option value="stale">Stale / expired</option><option value="damaged">Damaged</option><option value="sample">Customer sample</option><option value="staff_use">Staff use</option><option value="count_adjustment">Stock count shortage</option>
                                                </select>
                                                <input type="text" name="notes" maxlength="255" placeholder="Optional note" class="w-full h-10 rounded-lg bg-background border-outline text-on-surface text-xs">
                                                <button class="w-full h-10 rounded-lg bg-error text-white text-xs font-bold">Save stock loss</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-10 text-center text-on-surface-variant">No fresh production has been entered. Mark an item as “Made in this shop” under Products, then enter today's quantity above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $batches->links() }}
    </div>
</div>
@endsection
